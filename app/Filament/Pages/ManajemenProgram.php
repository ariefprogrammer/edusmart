<?php

namespace App\Filament\Pages;

use App\Models\Kelas;
use App\Models\KategoriNilai;
use App\Models\NilaiSiswa;
use App\Models\Periode;
use App\Models\PresensiSiswa;
use App\Models\ProgressSiswa;
use App\Models\Rpp;
use App\Models\RppUlasan;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;

class ManajemenProgram extends Page
{
    use WithFileUploads;

    protected static string $view = 'filament.pages.manajemen-program';
    protected static ?string $slug = 'manajemen-program/{kelas}';
    protected static bool $shouldRegisterNavigation = false;

    public Kelas $kelas;
    public string $activeTab = 'presensi';
    public string $tanggal;
    public bool $isGuruPengampu = false;
    public bool $tampilkanSemuaSiswa = false;

    // Periode tipe "bulan" — dipakai di tab Presensi & Progress, mengikuti tanggal yang dipilih
    public ?int $periodeId = null;

    // Periode tipe "semester" — dipakai di tab Nilai & RPP, tidak terikat input tanggal
    public ?int $periodeSemesterId = null;

    public array $presensiData = [];
    public array $progressData = [];
    public array $nilaiData = [];
    public string $namaKategoriBaru = '';

    public string $rppJudul = '';
    public $rppFile = null;
    public array $ulasanBaru = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['guru', 'admin_cabang', 'koordinator_cabang', 'super_admin']) ?? false;
    }

    public function mount(Kelas $kelas): void
    {
        $user = auth()->user();
        $guruId = $user->guru?->id;

        $isGuruPengampu = $guruId && $kelas->jadwal()->where('guru_id', $guruId)->exists();
        $isCabangUser = $user->cabang->pluck('id')->contains($kelas->cabang_id);
        $isSuperAdmin = $user->hasRole('super_admin');

        abort_unless($isGuruPengampu || $isCabangUser || $isSuperAdmin, 403);

        $this->isGuruPengampu = $isGuruPengampu;
        $this->kelas = $kelas->load(['cabang', 'siswa' => fn ($q) => $q->orderBy('nama')]);
        $this->tanggal = today()->toDateString();
        $this->activeTab = 'presensi';

        $this->periodeId = $this->cariPeriodeUntukTanggal($this->tanggal, 'bulan');
        $this->periodeSemesterId = $this->cariPeriodeUntukTanggal(today()->toDateString(), 'semester');

        $this->loadPresensi();
        $this->loadProgress();
        $this->loadNilai();
    }

    public function updatedTanggal(): void
    {
        $this->periodeId = $this->cariPeriodeUntukTanggal($this->tanggal, 'bulan');

        $this->loadPresensi();
        $this->loadProgress();
    }

    public function updatedPeriodeSemesterId(): void
    {
        $this->loadNilai();
    }

    protected function cariPeriodeUntukTanggal(string $tanggal, string $tipe): ?int
    {
        return Periode::where('cabang_id', $this->kelas->cabang_id)
            ->where('tipe', $tipe)
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->value('id');
    }

    public function getPeriodeOptions()
    {
        return Periode::where('cabang_id', $this->kelas->cabang_id)
            ->where('tipe', 'bulan')
            ->orderByDesc('tanggal_mulai')
            ->pluck('nama_periode', 'id');
    }

    public function getPeriodeSemesterOptions()
    {
        return Periode::where('cabang_id', $this->kelas->cabang_id)
            ->where('tipe', 'semester')
            ->orderByDesc('tanggal_mulai')
            ->pluck('nama_periode', 'id');
    }

    /**
     * Daftar siswa yang ditampilkan di tabel — default hanya yang is_active,
     * kecuali toggle "tampilkan semua" dinyalakan.
     */
    public function getSiswaList()
    {
        $siswa = $this->kelas->siswa->unique('id');

        return $this->tampilkanSemuaSiswa
            ? $siswa->values()
            : $siswa->filter(fn ($s) => $s->is_active && $s->pivot->is_active)->values();
    }

    /**
     * Dipakai untuk menyaring proses simpan — siswa non-aktif tidak boleh
     * diubah datanya sama sekali, apapun isi form yang terkirim.
     */
    protected function siswaAktifIds(): array
    {
        return $this->kelas->siswa
            ->filter(fn ($s) => $s->is_active && $s->pivot->is_active)
            ->pluck('id')
            ->all();
    }

    // =========================================================
    // PRESENSI SISWA (periode tipe: bulan)
    // =========================================================

    public function loadPresensi(): void
    {
        $existing = PresensiSiswa::where('kelas_id', $this->kelas->id)
            ->whereDate('tanggal', $this->tanggal)
            ->get()
            ->keyBy('siswa_id');

        $this->presensiData = $this->kelas->siswa->mapWithKeys(function ($siswa) use ($existing) {
            $row = $existing->get($siswa->id);

            return [
                $siswa->id => [
                    'status' => $row?->status ?? 'null',
                    'keterangan' => $row?->keterangan ?? '',
                ],
            ];
        })->toArray();
    }

    public function submitPresensi(): void
    {
        abort_unless($this->isGuruPengampu, 403);

        if (! $this->periodeId) {
            Notification::make()->danger()->title('Pilih periode terlebih dahulu')->send();
            return;
        }

        $guruId = auth()->user()->guru?->id;
        $aktifIds = $this->siswaAktifIds();

        foreach ($this->presensiData as $siswaId => $data) {
            if (! in_array($siswaId, $aktifIds)) {
                continue;
            }

            PresensiSiswa::updateOrCreate(
                [
                    'kelas_id' => $this->kelas->id,
                    'siswa_id' => $siswaId,
                    'tanggal' => $this->tanggal,
                ],
                [
                    'cabang_id' => $this->kelas->cabang_id,
                    'guru_id' => $guruId,
                    'periode_id' => $this->periodeId,
                    'status' => $data['status'],
                    'keterangan' => $data['keterangan'],
                ],
            );
        }

        Notification::make()->success()->title('Presensi berhasil disimpan')->send();
    }

    // =========================================================
    // PROGRESS SISWA (periode tipe: bulan)
    // =========================================================

    public function loadProgress(): void
    {
        $existing = ProgressSiswa::where('kelas_id', $this->kelas->id)
            ->whereDate('tanggal', $this->tanggal)
            ->get()
            ->keyBy('siswa_id');

        $this->progressData = $this->kelas->siswa->mapWithKeys(function ($siswa) use ($existing) {
            return [$siswa->id => $existing->get($siswa->id)?->catatan ?? ''];
        })->toArray();
    }

    public function submitProgress(): void
    {
        abort_unless($this->isGuruPengampu, 403);

        if (! $this->periodeId) {
            Notification::make()->danger()->title('Pilih periode terlebih dahulu')->send();
            return;
        }

        $guruId = auth()->user()->guru?->id;
        $aktifIds = $this->siswaAktifIds();

        foreach ($this->progressData as $siswaId => $catatan) {
            if (! in_array($siswaId, $aktifIds)) {
                continue;
            }

            ProgressSiswa::updateOrCreate(
                [
                    'kelas_id' => $this->kelas->id,
                    'siswa_id' => $siswaId,
                    'tanggal' => $this->tanggal,
                ],
                [
                    'cabang_id' => $this->kelas->cabang_id,
                    'guru_id' => $guruId,
                    'periode_id' => $this->periodeId,
                    'catatan' => $catatan,
                ],
            );
        }

        Notification::make()->success()->title('Progress siswa berhasil disimpan')->send();
    }

    // =========================================================
    // NILAI SISWA (periode tipe: semester)
    // =========================================================

    public function getKategoriList()
    {
        return KategoriNilai::where('kelas_id', $this->kelas->id)
            ->orderBy('urutan')
            ->orderBy('id')
            ->get();
    }

    public function tambahKategori(): void
    {
        abort_unless($this->isGuruPengampu, 403);

        $this->validate([
            'namaKategoriBaru' => 'required|string|max:100',
        ]);

        $urutanTerakhir = KategoriNilai::where('kelas_id', $this->kelas->id)->max('urutan') ?? 0;

        KategoriNilai::create([
            'kelas_id' => $this->kelas->id,
            'nama_kategori' => $this->namaKategoriBaru,
            'urutan' => $urutanTerakhir + 1,
        ]);

        $this->namaKategoriBaru = '';
        $this->loadNilai();

        Notification::make()->success()->title('Kategori nilai ditambahkan')->send();
    }

    public function hapusKategori(int $kategoriId): void
    {
        abort_unless($this->isGuruPengampu, 403);

        KategoriNilai::where('kelas_id', $this->kelas->id)->where('id', $kategoriId)->delete();

        $this->loadNilai();

        Notification::make()->success()->title('Kategori nilai dihapus')->send();
    }

    public function loadNilai(): void
    {
        $kategoriList = $this->getKategoriList();

        $existing = NilaiSiswa::where('kelas_id', $this->kelas->id)
            ->where('periode_id', $this->periodeSemesterId)
            ->get()
            ->groupBy('siswa_id');

        $this->nilaiData = $this->kelas->siswa->mapWithKeys(function ($siswa) use ($existing, $kategoriList) {
            $nilaiPerKategori = $existing->get($siswa->id, collect())->keyBy('kategori_nilai_id');

            $row = $kategoriList->mapWithKeys(fn ($kategori) => [
                $kategori->id => $nilaiPerKategori->get($kategori->id)?->nilai,
            ])->toArray();

            return [$siswa->id => $row];
        })->toArray();
    }

    public function submitNilai(): void
    {
        abort_unless($this->isGuruPengampu, 403);

        if (! $this->periodeSemesterId) {
            Notification::make()->danger()->title('Pilih periode terlebih dahulu')->send();
            return;
        }

        $guruId = auth()->user()->guru?->id;
        $aktifIds = $this->siswaAktifIds();

        foreach ($this->nilaiData as $siswaId => $kategoriValues) {
            if (! in_array($siswaId, $aktifIds)) {
                continue;
            }

            foreach ($kategoriValues as $kategoriId => $nilai) {
                if ($nilai === null || $nilai === '') {
                    continue;
                }

                NilaiSiswa::updateOrCreate(
                    [
                        'kelas_id' => $this->kelas->id,
                        'siswa_id' => $siswaId,
                        'kategori_nilai_id' => $kategoriId,
                        'periode_id' => $this->periodeSemesterId,
                    ],
                    [
                        'cabang_id' => $this->kelas->cabang_id,
                        'guru_id' => $guruId,
                        'nilai' => $nilai,
                    ],
                );
            }
        }

        Notification::make()->success()->title('Nilai siswa berhasil disimpan')->send();
    }

    // =========================================================
    // RPP (periode tipe: semester)
    // =========================================================

    public function getRppList()
    {
        return Rpp::where('kelas_id', $this->kelas->id)
            ->where('periode_id', $this->periodeSemesterId)
            ->with(['guru', 'ulasan.user'])
            ->latest()
            ->get();
    }

    public function uploadRpp(): void
    {
        abort_unless($this->isGuruPengampu, 403);

        if (! $this->periodeSemesterId) {
            Notification::make()->danger()->title('Pilih periode terlebih dahulu')->send();
            return;
        }

        $this->validate([
            'rppJudul' => 'required|string|max:255',
            'rppFile' => 'required|file|mimes:pdf|max:10240',
        ]);

        $path = $this->rppFile->store('rpp', 'public');

        Rpp::create([
            'cabang_id' => $this->kelas->cabang_id,
            'kelas_id' => $this->kelas->id,
            'guru_id' => auth()->user()->guru?->id,
            'periode_id' => $this->periodeSemesterId,
            'judul' => $this->rppJudul,
            'file_path' => $path,
            'file_name' => $this->rppFile->getClientOriginalName(),
        ]);

        $this->reset(['rppJudul', 'rppFile']);

        Notification::make()->success()->title('RPP berhasil diupload')->send();
    }

    public function submitUlasan(int $rppId): void
    {
        $teks = trim($this->ulasanBaru[$rppId] ?? '');

        if ($teks === '') {
            return;
        }

        RppUlasan::create([
            'rpp_id' => $rppId,
            'user_id' => auth()->id(),
            'ulasan' => $teks,
        ]);

        $this->ulasanBaru[$rppId] = '';

        Notification::make()->success()->title('Ulasan ditambahkan')->send();
    }
}