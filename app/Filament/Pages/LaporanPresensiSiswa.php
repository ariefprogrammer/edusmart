<?php

namespace App\Filament\Pages;

use App\Models\Cabang;
use App\Models\Kelas;
use App\Models\PresensiSiswa;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use App\Models\Siswa;
use Carbon\CarbonPeriod;
use Filament\Notifications\Notification;
use Livewire\Attributes\Locked;

class LaporanPresensiSiswa extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static ?string $navigationLabel = 'Laporan Presensi Siswa';
    protected static ?string $title = 'Laporan Presensi Siswa';
    protected static string $view = 'filament.pages.laporan-presensi-siswa';
    protected static ?string $navigationGroup = 'Manajemen Siswa';
    protected static ?int $navigationSort = 16;

    public string $dateStart;
    public string $dateEnd;
    public ?int $cabangId = null;
    public ?int $kelasId = null;

    public string $statusSiswa = 'aktif';
    protected const STATUS_VALID = ['hadir', 'izin', 'sakit', 'alpa'];

    #[Locked]
    public ?int $editSiswaId = null;

    #[Locked]
    public ?string $editTanggal = null;

    #[Locked]
    public string $editNamaSiswa = '';

    public array $editRows = [];

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

    public function bisaUbah(): bool
    {
        return auth()->user()?->hasAnyRole(['koordinator_cabang', 'admin_cabang']) ?? false;
    }

    protected function cabangIdsUser(): array
    {
        return auth()->user()->cabang->pluck('id')->all();
    }

    public function bukaUbah(int $siswaId, string $tanggal): void
    {
        abort_unless($this->bisaUbah(), 403);
        abort_unless(preg_match('/^\d{4}-\d{2}-\d{2}$/', $tanggal) === 1, 422);

        // Isinya sama dengan yang dihitung di sel tabel (termasuk filter program)
        $records = PresensiSiswa::query()
            ->where('siswa_id', $siswaId)
            ->whereDate('tanggal', $tanggal)
            ->whereIn('cabang_id', $this->cabangIdsUser())
            ->whereNotNull('kelas_id')
            ->whereIn('status', self::STATUS_VALID)
            ->when($this->kelasId, fn ($q) => $q->where('kelas_id', $this->kelasId))
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            Notification::make()->warning()->title('Data presensi tidak ditemukan')->send();
            return;
        }

        $namaKelas = Kelas::whereIn('id', $records->pluck('kelas_id'))->pluck('nama_kelas', 'id');

        $this->editSiswaId = $siswaId;
        $this->editTanggal = $tanggal;
        $this->editNamaSiswa = Siswa::find($siswaId)?->nama ?? '-';
        $this->editRows = $records->mapWithKeys(fn ($r) => [
            $r->id => [
                'kelas' => $namaKelas[$r->kelas_id] ?? '-',
                'status' => $r->status,
                'keterangan' => $r->keterangan,
            ],
        ])->all();

        $this->dispatch('open-modal', id: 'ubah-presensi');
    }

    public function simpanUbah(): void
    {
        abort_unless($this->bisaUbah(), 403);

        if (! $this->editSiswaId || ! $this->editTanggal) {
            return;
        }

        $this->validate([
            'editRows.*.status' => ['required', 'in:' . implode(',', self::STATUS_VALID)],
        ], [
            'editRows.*.status.required' => 'Status wajib dipilih.',
            'editRows.*.status.in' => 'Status tidak valid.',
        ]);

        // Ambil ulang dari database: hanya data milik siswa, tanggal, dan cabang yang sah
        $records = PresensiSiswa::query()
            ->whereIn('id', array_map('intval', array_keys($this->editRows)))
            ->where('siswa_id', $this->editSiswaId)
            ->whereDate('tanggal', $this->editTanggal)
            ->whereIn('cabang_id', $this->cabangIdsUser())
            ->get();

        $jumlahBerubah = 0;

        foreach ($records as $record) {
            $statusBaru = $this->editRows[$record->id]['status'];

            if ($record->status !== $statusBaru) {
                $record->status = $statusBaru;
                $record->save();
                $jumlahBerubah++;
            }
        }

        $this->dispatch('close-modal', id: 'ubah-presensi');

        $this->reset(['editSiswaId', 'editTanggal', 'editNamaSiswa', 'editRows']);

        Notification::make()
            ->success()
            ->title($jumlahBerubah > 0 ? 'Kehadiran berhasil diubah' : 'Tidak ada perubahan')
            ->send();
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