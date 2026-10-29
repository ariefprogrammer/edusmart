<?php

namespace App\Filament\Pages;

use App\Models\JadwalPengganti;
use App\Models\Periode;
use App\Models\PresensiSiswa;
use App\Models\ProgressSiswa;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ManajemenJadwalPengganti extends Page
{
    protected static string $view = 'filament.pages.manajemen-jadwal-pengganti';
    protected static ?string $slug = 'manajemen-jadwal-pengganti/{jadwalPengganti}';
    protected static bool $shouldRegisterNavigation = false;

    public JadwalPengganti $jadwalPengganti;
    public string $activeTab = 'presensi';
    public bool $isGuruPengampu = false;

    public array $presensiData = [];
    public array $progressData = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['guru', 'admin_cabang', 'koordinator_cabang', 'super_admin']) ?? false;
    }

    public function mount(JadwalPengganti $jadwalPengganti): void
    {
        $user = auth()->user();
        $guruId = $user->guru?->id;

        $isGuruPengampu = $guruId && (int) $jadwalPengganti->guru_id === (int) $guruId;
        $isAdminCabang = $user->hasAnyRole(['admin_cabang', 'koordinator_cabang'])
            && $user->cabang->pluck('id')->contains($jadwalPengganti->cabang_id);
        $isSuperAdmin = $user->hasRole('super_admin');

        abort_unless($isGuruPengampu || $isAdminCabang || $isSuperAdmin, 403);
        abort_unless($jadwalPengganti->kelas_id, 404);

        $this->isGuruPengampu = (bool) $isGuruPengampu;
        $this->jadwalPengganti = $jadwalPengganti->load(['kelas', 'guru', 'cabang']);

        $this->loadPresensi();
        $this->loadProgress();
    }

    // =========================================================
    // Status & hak input
    // =========================================================

    public function masihTerjadwal(): bool
    {
        return $this->jadwalPengganti->status === JadwalPengganti::STATUS_TERJADWAL;
    }

    public function isTanggalPengganti(): bool
    {
        return $this->jadwalPengganti->tanggal_pengganti->isToday();
    }

    public function bisaInput(): bool
    {
        return $this->isGuruPengampu && $this->masihTerjadwal() && $this->isTanggalPengganti();
    }

    /** Boleh pada tanggal pengganti atau sesudahnya, tidak sebelumnya. */
    public function bisaTandaiSelesai(): bool
    {
        return $this->isGuruPengampu
            && $this->masihTerjadwal()
            && ! $this->jadwalPengganti->tanggal_pengganti->gt(today());
    }

    // =========================================================
    // Data siswa
    // =========================================================

    /** Siswa terdaftar di jadwal pengganti ini yang masih aktif (siswa dan pivot kelas). */
    protected function siswaAktifIds(): array
    {
        $terdaftar = $this->jadwalPengganti->siswa()->pluck('siswa.id');

        return $this->jadwalPengganti->kelas->siswa()
            ->wherePivot('is_active', true)
            ->where('siswa.is_active', true)
            ->whereIn('siswa.id', $terdaftar)
            ->pluck('siswa.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public function getSiswaList(): Collection
    {
        return $this->jadwalPengganti->siswa()
            ->whereIn('siswa.id', $this->siswaAktifIds())
            ->orderBy('siswa.nama')
            ->get();
    }

    protected function barisPresensi(): Collection
    {
        return PresensiSiswa::where('kelas_id', $this->jadwalPengganti->kelas_id)
            ->whereDate('tanggal', $this->jadwalPengganti->tanggal_seharusnya->toDateString())
            ->get()
            ->keyBy('siswa_id');
    }

    protected function barisProgress(): Collection
    {
        return ProgressSiswa::where('kelas_id', $this->jadwalPengganti->kelas_id)
            ->whereDate('tanggal', $this->jadwalPengganti->tanggal_seharusnya->toDateString())
            ->get()
            ->keyBy('siswa_id');
    }

    protected function periodeBulanTanggalSeharusnya(): ?int
    {
        $tanggal = $this->jadwalPengganti->tanggal_seharusnya->toDateString();

        return Periode::where('cabang_id', $this->jadwalPengganti->cabang_id)
            ->where('tipe', 'bulan')
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->value('id');
    }

    /** Baris yang sudah punya periode mempertahankannya; baris baru memakai periode tanggal seharusnya. */
    protected function periodeSiap(Collection $siswaList, Collection $existing, ?int $periodeTanggal): bool
    {
        if ($periodeTanggal || ! $siswaList->contains(fn ($s) => ! $existing->get($s->id)?->periode_id)) {
            return true;
        }

        Notification::make()->danger()
            ->title('Periode bulan untuk tanggal seharusnya belum dibuat')
            ->body('Hubungi admin untuk membuat periode terlebih dahulu.')
            ->send();

        return false;
    }

    // =========================================================
    // Presensi (memperbarui baris di tanggal seharusnya)
    // =========================================================

    public function loadPresensi(): void
    {
        $existing = $this->barisPresensi();

        $this->presensiData = $this->getSiswaList()->mapWithKeys(function ($siswa) use ($existing) {
            $row = $existing->get($siswa->id);
            $sudahDisimpan = filled($siswa->pivot->presensi_disimpan_pada) && $row;

            return [
                $siswa->id => [
                    'status' => $sudahDisimpan ? $row->status : 'null',
                    'keterangan' => $sudahDisimpan ? JadwalPengganti::ambilCatatan($row->keterangan_pengganti) : '',
                ],
            ];
        })->toArray();
    }

    public function submitPresensi(): void
    {
        abort_unless($this->bisaInput(), 403);

        $siswaList = $this->getSiswaList();

        foreach ($siswaList as $siswa) {
            $status = $this->presensiData[$siswa->id]['status'] ?? null;

            if (! in_array($status, ['hadir', 'izin', 'sakit', 'alpa'], true)) {
                Notification::make()->danger()
                    ->title('Status presensi belum lengkap')
                    ->body('Pilih status untuk semua siswa sebelum menyimpan.')
                    ->send();
                return;
            }
        }

        $existing = $this->barisPresensi();
        $periodeTanggal = $this->periodeBulanTanggalSeharusnya();

        if (! $this->periodeSiap($siswaList, $existing, $periodeTanggal)) {
            return;
        }

        $tanggal = $this->jadwalPengganti->tanggal_seharusnya->toDateString();
        $guruId = auth()->user()->guru?->id;

        DB::transaction(function () use ($siswaList, $existing, $periodeTanggal, $tanggal, $guruId) {
            foreach ($siswaList as $siswa) {
                $data = $this->presensiData[$siswa->id];

                PresensiSiswa::updateOrCreate(
                    [
                        'kelas_id' => $this->jadwalPengganti->kelas_id,
                        'siswa_id' => $siswa->id,
                        'tanggal' => $tanggal,
                    ],
                    [
                        'cabang_id' => $this->jadwalPengganti->cabang_id,
                        'guru_id' => $guruId,
                        'periode_id' => $existing->get($siswa->id)?->periode_id ?? $periodeTanggal,
                        'status' => $data['status'],
                        'keterangan_pengganti' => JadwalPengganti::gabungKeterangan($data['keterangan'] ?? ''),
                    ],
                );

                $this->jadwalPengganti->siswa()->updateExistingPivot($siswa->id, [
                    'presensi_disimpan_pada' => now(),
                ]);
            }
        });

        $this->loadPresensi();

        Notification::make()->success()->title('Presensi berhasil disimpan')->send();
    }

    // =========================================================
    // Progress (memperbarui baris di tanggal seharusnya)
    // =========================================================

    public function loadProgress(): void
    {
        $existing = $this->barisProgress();

        $this->progressData = $this->getSiswaList()->mapWithKeys(fn ($siswa) => [
            $siswa->id => JadwalPengganti::ambilCatatan($existing->get($siswa->id)?->keterangan_pengganti),
        ])->toArray();
    }

    public function submitProgress(): void
    {
        abort_unless($this->bisaInput(), 403);

        $siswaList = $this->getSiswaList();
        $existing = $this->barisProgress();
        $periodeTanggal = $this->periodeBulanTanggalSeharusnya();

        if (! $this->periodeSiap($siswaList, $existing, $periodeTanggal)) {
            return;
        }

        $tanggal = $this->jadwalPengganti->tanggal_seharusnya->toDateString();
        $guruId = auth()->user()->guru?->id;

        DB::transaction(function () use ($siswaList, $existing, $periodeTanggal, $tanggal, $guruId) {
            foreach ($siswaList as $siswa) {
                ProgressSiswa::updateOrCreate(
                    [
                        'kelas_id' => $this->jadwalPengganti->kelas_id,
                        'siswa_id' => $siswa->id,
                        'tanggal' => $tanggal,
                    ],
                    [
                        'cabang_id' => $this->jadwalPengganti->cabang_id,
                        'guru_id' => $guruId,
                        'periode_id' => $existing->get($siswa->id)?->periode_id ?? $periodeTanggal,
                        'keterangan_pengganti' => JadwalPengganti::gabungKeterangan($this->progressData[$siswa->id] ?? ''),
                    ],
                );
            }
        });

        $this->loadProgress();

        Notification::make()->success()->title('Progress siswa berhasil disimpan')->send();
    }

    // =========================================================
    // Tandai selesai
    // =========================================================

    public function tandaiSelesai(): void
    {
        abort_unless($this->bisaTandaiSelesai(), 403);

        $belum = $this->jadwalPengganti->siswa()
            ->whereIn('siswa.id', $this->siswaAktifIds())
            ->wherePivotNull('presensi_disimpan_pada')
            ->orderBy('siswa.nama')
            ->pluck('siswa.nama');

        if ($belum->isNotEmpty()) {
            Notification::make()->danger()
                ->title('Belum bisa ditandai selesai')
                ->body('Presensi belum disimpan untuk: ' . $belum->implode(', ') . '.')
                ->send();
            return;
        }

        $this->jadwalPengganti->update([
            'status' => JadwalPengganti::STATUS_SELESAI,
            'selesai_pada' => now(),
        ]);

        Notification::make()->success()->title('Jadwal pengganti ditandai selesai')->send();
    }
}