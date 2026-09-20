<?php

namespace App\Filament\Pages;

use App\Models\Cabang;
use App\Models\Kelas;
use App\Models\PresensiSiswa;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use App\Models\Siswa;
use Carbon\CarbonPeriod;

class LaporanPresensiSiswa extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Laporan Presensi Siswa';
    protected static ?string $title = 'Laporan Presensi Siswa';
    protected static string $view = 'filament.pages.laporan-presensi-siswa';

    public string $dateStart;
    public string $dateEnd;
    public ?int $cabangId = null;
    public ?int $kelasId = null;

    public string $statusSiswa = 'aktif';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin_cabang', 'koordinator_cabang', 'super_admin', 'guru']) ?? false;
    }

    public function mount(): void
    {
        $this->dateStart = now()->startOfMonth()->toDateString();
        $this->dateEnd = now()->endOfMonth()->toDateString();

        if (! auth()->user()->hasRole('super_admin')) {
            $this->cabangId = auth()->user()->cabang->first()?->id;
        }
    }

    public function updatedCabangId(): void
    {
        $this->kelasId = null;
    }

    public function isSuperAdmin(): bool
    {
        return auth()->user()->hasRole('super_admin');
    }

    public function isGuru(): bool
    {
        return auth()->user()->hasRole('guru') && ! auth()->user()->hasAnyRole(['admin_cabang', 'koordinator_cabang', 'super_admin']);
    }

    public function getCabangOptions(): Collection
    {
        if (! $this->isSuperAdmin()) {
            return collect();
        }

        return Cabang::orderBy('nama_cabang')->pluck('nama_cabang', 'id');
    }

    public function getKelasOptions(): Collection
    {
        if ($this->isGuru()) {
            $guruId = auth()->user()->guru?->id;

            return Kelas::query()
                ->whereHas('jadwal', fn ($q) => $q->where('guru_id', $guruId))
                ->orderBy('nama_kelas')
                ->pluck('nama_kelas', 'id');
        }

        $cabangId = $this->isSuperAdmin() ? $this->cabangId : auth()->user()->cabang->first()?->id;

        return Kelas::query()
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('nama_kelas')
            ->pluck('nama_kelas', 'id');
    }

    public function getDateRange(): array
    {
        return collect(CarbonPeriod::create($this->dateStart, $this->dateEnd))
            ->map(fn ($date) => $date->toDateString())
            ->all();
    }

    public function getRekap(): Collection
    {
        $cabangIds = $this->isSuperAdmin()
            ? ($this->cabangId ? [$this->cabangId] : null)
            : auth()->user()->cabang->pluck('id')->all();

        $kelasIdsGuru = null;

        if ($this->isGuru()) {
            $guruId = auth()->user()->guru?->id;
            $kelasIdsGuru = Kelas::whereHas('jadwal', fn ($q) => $q->where('guru_id', $guruId))->pluck('id')->all();
        }

        $siswaQuery = Siswa::query()
            ->when($cabangIds, fn ($q, $ids) => $q->whereIn('cabang_id', $ids))
            ->when($kelasIdsGuru !== null, fn ($q) => $q->whereHas('kelasList', fn ($q2) => $q2->whereIn('kelas.id', $kelasIdsGuru)))
            ->when($this->kelasId, fn ($q) => $q->whereHas('kelasList', fn ($q2) => $q2->where('kelas.id', $this->kelasId)))
            ->when($this->statusSiswa === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($this->statusSiswa === 'tidak_aktif', fn ($q) => $q->where('is_active', false));

        $siswaList = $siswaQuery->orderBy('nama')->get();

        $presensiPerSiswa = PresensiSiswa::query()
            ->whereBetween('tanggal', [$this->dateStart, $this->dateEnd])
            ->whereIn('siswa_id', $siswaList->pluck('id'))
            ->when($cabangIds, fn ($q, $ids) => $q->whereIn('cabang_id', $ids))
            ->when($this->kelasId, fn ($q) => $q->where('kelas_id', $this->kelasId))
            ->when($this->kelasId === null && $kelasIdsGuru !== null, fn ($q) => $q->whereIn('kelas_id', $kelasIdsGuru))
            ->when($this->kelasId === null && $kelasIdsGuru === null, fn ($q) => $q->whereNotNull('kelas_id'))
            ->get()
            ->groupBy('siswa_id');

        return $siswaList->map(function ($siswa) use ($presensiPerSiswa) {
            $records = $presensiPerSiswa->get($siswa->id, collect())
                ->keyBy(fn ($r) => \Carbon\Carbon::parse($r->tanggal)->toDateString());

            $harian = [];
            foreach ($this->getDateRange() as $tanggal) {
                $harian[$tanggal] = $records->get($tanggal)?->status;
            }

            return (object) [
                'siswa' => $siswa,
                'harian' => $harian,
                'hadir' => $records->where('status', 'hadir')->count(),
                'izin' => $records->where('status', 'izin')->count(),
                'sakit' => $records->where('status', 'sakit')->count(),
                'alpa' => $records->where('status', 'alpa')->count(),
            ];
        })->values();
    }
}