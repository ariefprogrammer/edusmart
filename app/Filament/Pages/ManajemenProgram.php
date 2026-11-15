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
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use App\Models\Jadwal;
use App\Services\PenjadwalanSiswa;

class ManajemenProgram extends Page
{
    use WithFileUploads;

    protected static string $view = 'filament.pages.manajemen-program';
    protected static ?string $slug = 'manajemen-program/{kelas}';
    protected static bool $shouldRegisterNavigation = false;

    public Kelas $kelas;
    public string $activeTab = 'presensi';
    public string $tanggal;

    // Jadwal (sesi jam belajar) yang sedang dibuka. Divalidasi ulang di setiap pemakaian,
    // karena properti publik Livewire bisa diubah dari browser.
    public ?int $jadwalId = null;
    protected ?Collection $siswaJadwalCache = null;
    protected ?Jadwal $jadwalCache = null;

    #[Locked]
    public bool $isGuruPengampu = false;

    public bool $tampilkanSemuaSiswa = false;
    protected ?Collection $siswaCache = null;

    // Periode tipe "bulan" — dipakai di tab Presensi & Progress, mengikuti tanggal yang dipilih
    public ?int $periodeId = null;

    // Periode tipe "bulan" — dipakai di tab Nilai, dipilih lewat dropdown (tidak mengikuti tanggal)
    public ?int $periodeNilaiId = null;

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
        $this->kelas = $kelas->load('cabang');
        $this->tanggal = today()->toDateString();
        $this->activeTab = 'presensi';
        $this->jadwalId = (int) request()->query('jadwal') ?: null;

        // Guru wajib masuk lewat salah satu jadwalnya sendiri
        if ($isGuruPengampu && ! $this->jadwalDipilih()) {
            Notification::make()
                ->warning()
                ->title('Pilih jadwal terlebih dahulu')
                ->body('Masuk ke program lewat tombol Masuk di halaman Program Saya, lalu pilih jadwalnya.')
                ->send();

            $this->redirect(ProgramSaya::getUrl());

            return;
        }

        $this->periodeId = $this->cariPeriodeUntukTanggal($this->tanggal, 'bulan');
        $this->periodeNilaiId = $this->periodeId;
        $this->periodeSemesterId = $this->cariPeriodeUntukTanggal(today()->toDateString(), 'semester');

        $this->loadPresensi();
        $this->loadProgress();
        $this->loadNilai();
    }

    /** Dipakai admin saat memilih jadwal lewat dropdown. */
    public function updatedJadwalId(): void
    {
        $this->jadwalCache = null;
        $this->siswaJadwalCache = null;

        $this->loadPresensi();
        $this->loadProgress();
    }

    public function updatedTanggal(): void
    {
        $this->periodeId = $this->cariPeriodeUntukTanggal($this->tanggal, 'bulan');

        $this->loadPresensi();
        $this->loadProgress();
    }

    public function updatedPeriodeNilaiId(): void
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
     * Semua siswa program yang boleh dilihat user ini.
     * Guru pengampu hanya melihat siswa yang di-assign ke jadwal aktif miliknya
     * di program ini. Admin, koordinator, dan super_admin melihat semua siswa.
     */
    protected function semuaSiswa(): Collection
    {
        if ($this->siswaCache !== null) {
            return $this->siswaCache;
        }

        $guruId = $this->isGuruPengampu ? auth()->user()->guru?->id : null;

        return $this->siswaCache = $this->kelas->siswa()
            ->orderBy('siswa.nama')
            ->when($this->isGuruPengampu, fn ($q) => $q->whereIn('siswa.id', fn ($sub) => $sub
                ->select('jadwal_siswa.siswa_id')
                ->from('jadwal_siswa')
                ->join('jadwal', 'jadwal.id', '=', 'jadwal_siswa.jadwal_id')
                ->where('jadwal.kelas_id', $this->kelas->id)
                ->where('jadwal.guru_id', $guruId)
                ->where('jadwal.is_active', true)))
            ->get();
    }

    /**
     * Jadwal yang dibuka, setelah divalidasi: harus jadwal aktif di program ini,
     * dan untuk guru pengampu harus milik guru itu sendiri.
     */
    protected function jadwalDipilih(): ?Jadwal
    {
        if (! $this->jadwalId) {
            return null;
        }

        if ($this->jadwalCache?->getKey() === $this->jadwalId) {
            return $this->jadwalCache;
        }

        $guruId = $this->isGuruPengampu ? auth()->user()->guru?->id : null;

        return $this->jadwalCache = Jadwal::query()
            ->with('guru')
            ->where('kelas_id', $this->kelas->id)
            ->where('is_active', true)
            ->when($this->isGuruPengampu, fn ($q) => $q->where('guru_id', $guruId))
            ->find($this->jadwalId);
    }

    public function getJadwalTerpilih(): ?Jadwal
    {
        return $this->jadwalDipilih();
    }

    /** Pilihan jadwal untuk dropdown admin, dikelompokkan per guru. */
    public function getJadwalOptions(): array
    {
        $hari = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        return Jadwal::query()
            ->with('guru')
            ->where('kelas_id', $this->kelas->id)
            ->where('is_active', true)
            ->get()
            ->sortBy(fn (Jadwal $j) => [$j->guru?->nama, array_search($j->hari, $hari), $j->jam_mulai])
            ->groupBy(fn (Jadwal $j) => 'Guru ' . ($j->guru?->nama ?? '-'))
            ->map(fn (Collection $g) => $g->mapWithKeys(fn (Jadwal $j) => [$j->id => PenjadwalanSiswa::labelJam($j)])->all())
            ->all();
    }

    /** Siswa yang di-assign ke jadwal yang sedang dibuka (dipakai tab Presensi dan Progress). */
    protected function siswaJadwal(): Collection
    {
        if ($this->siswaJadwalCache !== null) {
            return $this->siswaJadwalCache;
        }

        $jadwal = $this->jadwalDipilih();

        if (! $jadwal) {
            return $this->siswaJadwalCache = collect();
        }

        return $this->siswaJadwalCache = $this->kelas->siswa()
            ->orderBy('siswa.nama')
            ->whereIn('siswa.id', fn ($sub) => $sub
                ->select('jadwal_siswa.siswa_id')
                ->from('jadwal_siswa')
                ->where('jadwal_siswa.jadwal_id', $jadwal->id))
            ->get();
    }

    public function getSiswaList()
    {
        // Presensi dan Progress per jadwal; Nilai per program (semua siswa guru ini)
        $sumber = in_array($this->activeTab, ['presensi', 'progress'], true)
            ? $this->siswaJadwal()
            : $this->semuaSiswa();

        $siswa = $sumber->unique('id');

        return $this->tampilkanSemuaSiswa
            ? $siswa->values()
            : $siswa->filter(fn ($s) => $s->is_active && $s->pivot->is_active)->values();
    }

    /**
     * Dipakai untuk menyaring proses simpan — siswa non-aktif atau di luar $sumber
     * tidak boleh diubah datanya sama sekali, apapun isi form yang terkirim.
     */
    protected function siswaAktifIds(Collection $sumber): array
    {
        return $sumber
            ->filter(fn ($s) => $s->is_active && $s->pivot->is_active)
            ->pluck('id')
            ->all();
    }

    public function isTanggalHariIni(): bool
    {
        return $this->tanggal === today()->toDateString();
    }

    // =========================================================
    // PRESENSI SISWA 
    // =========================================================

    public function loadPresensi(): void
    {
        $jadwal = $this->jadwalDipilih();

        $existing = $jadwal
            ? PresensiSiswa::where('jadwal_id', $jadwal->id)
                ->whereDate('tanggal', $this->tanggal)
                ->get()
                ->keyBy('siswa_id')
            : collect();

        $this->presensiData = $this->siswaJadwal()->mapWithKeys(function ($siswa) use ($existing) {
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

        $jadwal = $this->jadwalDipilih();

        if (! $jadwal) {
            Notification::make()->danger()
                ->title('Jadwal tidak valid')
                ->body('Kembali ke Program Saya dan pilih jadwal terlebih dahulu.')
                ->send();
            return;
        }

        $hariIni = today()->toDateString();

        if ($this->tanggal !== $hariIni) {
            Notification::make()->danger()
                ->title('Presensi hanya dapat diinput pada tanggal hari ini')
                ->body('Jika tanggal sudah berganti, muat ulang halaman.')
                ->send();
            return;
        }

        // Periode ditentukan server dari tanggal hari ini, bukan dari pilihan di browser
        $periodeHariIni = $this->cariPeriodeUntukTanggal($hariIni, 'bulan');

        if (! $periodeHariIni) {
            Notification::make()->danger()
                ->title('Periode bulan ini belum dibuat')
                ->body('Hubungi admin untuk membuat periode terlebih dahulu.')
                ->send();
            return;
        }

        $guruId = auth()->user()->guru?->id;
        $aktifIds = $this->siswaAktifIds($this->siswaJadwal());

        foreach ($this->presensiData as $siswaId => $data) {
            if (! in_array($siswaId, $aktifIds)) {
                continue;
            }

            PresensiSiswa::updateOrCreate(
                [
                    'jadwal_id' => $jadwal->id,
                    'siswa_id' => $siswaId,
                    'tanggal' => $hariIni,
                ],
                [
                    'cabang_id' => $this->kelas->cabang_id,
                    'kelas_id' => $this->kelas->id,
                    'guru_id' => $guruId,
                    'periode_id' => $periodeHariIni,
                    'status' => $data['status'],
                    'keterangan' => $data['keterangan'],
                ],
            );
        }

        Notification::make()->success()
            ->title('Presensi berhasil disimpan')
            ->body('Jadwal ' . PenjadwalanSiswa::labelJam($jadwal) . '.')
            ->send();
    }

    public function loadProgress(): void
    {
        $jadwal = $this->jadwalDipilih();

        $existing = $jadwal
            ? ProgressSiswa::where('jadwal_id', $jadwal->id)
                ->whereDate('tanggal', $this->tanggal)
                ->get()
                ->keyBy('siswa_id')
            : collect();

        $this->progressData = $this->siswaJadwal()->mapWithKeys(function ($siswa) use ($existing) {
            return [$siswa->id => $existing->get($siswa->id)?->catatan ?? ''];
        })->toArray();
    }

    public function submitProgress(): void
    {
        abort_unless($this->isGuruPengampu, 403);

        $jadwal = $this->jadwalDipilih();

        if (! $jadwal) {
            Notification::make()->danger()
                ->title('Jadwal tidak valid')
                ->body('Kembali ke Program Saya dan pilih jadwal terlebih dahulu.')
                ->send();
            return;
        }

        $hariIni = today()->toDateString();

        if ($this->tanggal !== $hariIni) {
            Notification::make()->danger()
                ->title('Progress hanya dapat diinput pada tanggal hari ini')
                ->body('Jika tanggal sudah berganti, muat ulang halaman.')
                ->send();
            return;
        }

        $periodeHariIni = $this->cariPeriodeUntukTanggal($hariIni, 'bulan');

        if (! $periodeHariIni) {
            Notification::make()->danger()
                ->title('Periode bulan ini belum dibuat')
                ->body('Hubungi admin untuk membuat periode terlebih dahulu.')
                ->send();
            return;
        }

        $guruId = auth()->user()->guru?->id;
        $aktifIds = $this->siswaAktifIds($this->siswaJadwal());

        foreach ($this->progressData as $siswaId => $catatan) {
            if (! in_array($siswaId, $aktifIds)) {
                continue;
            }

            ProgressSiswa::updateOrCreate(
                [
                    'jadwal_id' => $jadwal->id,
                    'siswa_id' => $siswaId,
                    'tanggal' => $hariIni,
                ],
                [
                    'cabang_id' => $this->kelas->cabang_id,
                    'kelas_id' => $this->kelas->id,
                    'guru_id' => $guruId,
                    'periode_id' => $periodeHariIni,
                    'catatan' => $catatan,
                ],
            );
        }

        Notification::make()->success()
            ->title('Progress siswa berhasil disimpan')
            ->body('Jadwal ' . PenjadwalanSiswa::labelJam($jadwal) . '.')
            ->send();
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
            ->where('periode_id', $this->periodeNilaiId)
            ->get()
            ->groupBy('siswa_id');

        $this->nilaiData = $this->semuaSiswa()->mapWithKeys(function ($siswa) use ($existing, $kategoriList) {
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

        if (! $this->periodeNilaiId) {
            Notification::make()->danger()->title('Pilih periode terlebih dahulu')->send();
            return;
        }

        $guruId = auth()->user()->guru?->id;
        $aktifIds = $this->siswaAktifIds($this->semuaSiswa());

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
                        'periode_id' => $this->periodeNilaiId,
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