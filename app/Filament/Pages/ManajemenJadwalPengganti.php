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

    /**
     * Baris sesi yang digantikan (jadwal yang digantikan pada tanggal seharusnya), dengan kunci id siswa.
     * Baris lama yang dibuat sebelum ada jadwal_id (kosong) untuk program dan tanggal yang sama
     * ikut dikenali, dan diberi jadwal_id saat disimpan.
     */
    protected function barisSesiDigantikan(string $model): Collection
    {
        $jadwalId = $this->jadwalPengganti->jadwal_id;

        return $model::query()
            ->where('kelas_id', $this->jadwalPengganti->kelas_id)
            ->whereDate('tanggal', $this->jadwalPengganti->tanggal_seharusnya->toDateString())
            ->where(fn ($q) => $q->where('jadwal_id', $jadwalId)->orWhereNull('jadwal_id'))
            ->get()
            ->groupBy('siswa_id')
            // Baris dengan jadwal_id yang sesuai didahulukan daripada baris lama
            ->map(fn (Collection $baris) => $baris->first(
                fn ($b) => $b->jadwal_id !== null && (int) $b->jadwal_id === (int) $jadwalId
            ) ?? $baris->first());
    }

    protected function barisPresensi(): Collection
    {
        return $this->barisSesiDigantikan(PresensiSiswa::class);
    }

    protected function barisProgress(): Collection
    {
        return $this->barisSesiDigantikan(ProgressSiswa::class);
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
        $jadwalId = $this->jadwalPengganti->jadwal_id;

        DB::transaction(function () use ($siswaList, $existing, $periodeTanggal, $tanggal, $guruId, $jadwalId) {
            foreach ($siswaList as $siswa) {
                $data = $this->presensiData[$siswa->id];
                $row = $existing->get($siswa->id);

                $nilai = [
                    'jadwal_id' => $jadwalId,
                    'cabang_id' => $this->jadwalPengganti->cabang_id,
                    'guru_id' => $guruId,
                    'periode_id' => $row?->periode_id ?? $periodeTanggal,
                    'status' => $data['status'],
                    'keterangan_pengganti' => JadwalPengganti::gabungKeterangan($data['keterangan'] ?? ''),
                ];

                if ($row) {
                    // Memperbarui baris sesi yang digantikan; baris lama tanpa jadwal_id ikut diberi jadwal_id
                    $row->update($nilai);
                } else {
                    PresensiSiswa::create($nilai + [
                        'kelas_id' => $this->jadwalPengganti->kelas_id,
                        'siswa_id' => $siswa->id,
                        'tanggal' => $tanggal,
                    ]);
                }

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
        $jadwalId = $this->jadwalPengganti->jadwal_id;

        DB::transaction(function () use ($siswaList, $existing, $periodeTanggal, $tanggal, $guruId, $jadwalId) {
            foreach ($siswaList as $siswa) {
                $row = $existing->get($siswa->id);

                $nilai = [
                    'jadwal_id' => $jadwalId,
                    'cabang_id' => $this->jadwalPengganti->cabang_id,
                    'guru_id' => $guruId,
                    'periode_id' => $row?->periode_id ?? $periodeTanggal,
                    'keterangan_pengganti' => JadwalPengganti::gabungKeterangan($this->progressData[$siswa->id] ?? ''),
                ];

                if ($row) {
                    // Catatan progress reguler (kolom catatan) tidak disentuh
                    $row->update($nilai);
                } else {
                    ProgressSiswa::create($nilai + [
                        'kelas_id' => $this->jadwalPengganti->kelas_id,
                        'siswa_id' => $siswa->id,
                        'tanggal' => $tanggal,
                    ]);
                }
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