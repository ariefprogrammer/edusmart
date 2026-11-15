<?php

namespace App\Filament\Pages;

use App\Exports\LaporanPresensiSiswaExport;
use App\Models\Cabang;
use App\Models\Kelas;
use App\Models\PresensiSiswa;
use App\Models\Siswa;
use App\Services\PenjadwalanSiswa;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

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

    protected const HURUF_STATUS = ['hadir' => 'H', 'izin' => 'I', 'sakit' => 'S', 'alpa' => 'A'];
    protected const NAMA_STATUS = ['hadir' => 'Hadir', 'izin' => 'Izin', 'sakit' => 'Sakit', 'alpa' => 'Alpa'];

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

        // Super admin tanpa filter cabang melihat program dari semua cabang: sertakan nama cabang
        $sertakanCabang = $this->isSuperAdmin() && ! $this->cabangId;

        return Kelas::query()
            ->with('cabang')
            ->when($cabangId, fn ($q) => $q->where('cabang_id', $cabangId))
            ->orderBy('nama_kelas')
            ->get()
            ->mapWithKeys(fn (Kelas $kelas) => [
                $kelas->id => $sertakanCabang
                    ? $kelas->nama_kelas . ' — ' . ($kelas->cabang?->nama_cabang ?? '-')
                    : $kelas->nama_kelas,
            ]);
    }

    /**
     * Program yang dipilih, setelah divalidasi: harus termasuk pilihan yang boleh dilihat user ini.
     * Rekap hanya boleh untuk satu program, jadi tanpa program yang valid tidak ada data.
     */
    public function kelasTerpilih(): ?int
    {
        if (! $this->kelasId) {
            return null;
        }

        return $this->getKelasOptions()->has($this->kelasId) ? $this->kelasId : null;
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

        $kelasId = $this->kelasTerpilih();

        if (! $kelasId) {
            Notification::make()->warning()->title('Pilih Kelas / Program terlebih dahulu')->send();
            return;
        }

        // Semua sesi hari itu untuk siswa ini di program yang dipilih (satu baris per sesi)
        $records = PresensiSiswa::query()
            ->with('jadwal.guru')
            ->where('siswa_id', $siswaId)
            ->whereDate('tanggal', $tanggal)
            ->whereIn('cabang_id', $this->cabangIdsUser())
            ->where('kelas_id', $kelasId)
            ->whereIn('status', self::STATUS_VALID)
            ->orderBy('id')
            ->get();

        if ($records->isEmpty()) {
            Notification::make()->warning()->title('Data presensi tidak ditemukan')->send();
            return;
        }

        $this->editSiswaId = $siswaId;
        $this->editTanggal = $tanggal;
        $this->editNamaSiswa = Siswa::find($siswaId)?->nama ?? '-';
        $this->editRows = $records->mapWithKeys(fn (PresensiSiswa $r) => [
            $r->id => [
                'label' => $r->jadwal
                    ? PenjadwalanSiswa::labelJam($r->jadwal) . ' · Guru ' . ($r->jadwal->guru?->nama ?? '-')
                    : 'Sesi tanpa jadwal (data lama)',
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

    /**
     * Kode kehadiran satu siswa dalam satu hari dari daftar status tiap sesi.
     *
     * Status sama: huruf + akhiran jumlah sesi (H, HD = 2x, HT = 3x, 4x atau lebih = angka, mis. H4).
     * Status berbeda: tiap bagian ditulis dengan aturan yang sama dan digabung dengan "/"
     * dalam urutan tetap H, I, S, A (mis. H/A, HD/A).
     */
    protected static function kodePresensi(iterable $statuses): ?array
    {
        $hitung = collect($statuses)->countBy();

        $bagian = [];
        $ringkas = [];
        $statusAda = [];
        $jumlah = 0;

        foreach (self::HURUF_STATUS as $status => $huruf) {
            $n = (int) $hitung->get($status, 0);

            if ($n === 0) {
                continue;
            }

            $akhiran = match (true) {
                $n === 1 => '',
                $n === 2 => 'D',
                $n === 3 => 'T',
                default => (string) $n,
            };

            $bagian[] = $huruf . $akhiran;
            $ringkas[] = self::NAMA_STATUS[$status] . " {$n}x";
            $statusAda[] = $status;
            $jumlah += $n;
        }

        if ($bagian === []) {
            return null;
        }

        return [
            'kode' => implode('/', $bagian),
            // Satu jenis status memakai warna statusnya; campuran memakai warna netral
            'warna' => count($statusAda) === 1 ? $statusAda[0] : 'campuran',
            // Status pertama, untuk pemakai lama yang hanya mengenal satu status per hari (export Excel)
            'status' => $statusAda[0],
            'jumlah' => $jumlah,
            'ringkas' => $jumlah . ' sesi: ' . implode(', ', $ringkas),
        ];
    }

    public function getRekap(): Collection
    {
        $kelasId = $this->kelasTerpilih();

        // Rekap hanya untuk satu program: tanpa program yang dipilih tidak ada data
        if (! $kelasId) {
            return collect();
        }

        $cabangIds = $this->isSuperAdmin()
            ? ($this->cabangId ? [$this->cabangId] : null)
            : auth()->user()->cabang->pluck('id')->all();

        $siswaList = Siswa::query()
            ->when($cabangIds, fn ($q, $ids) => $q->whereIn('cabang_id', $ids))
            ->whereHas('kelasList', fn ($q) => $q->where('kelas.id', $kelasId))
            ->when($this->statusSiswa === 'aktif', fn ($q) => $q->where('is_active', true))
            ->when($this->statusSiswa === 'tidak_aktif', fn ($q) => $q->where('is_active', false))
            ->orderBy('nama')
            ->get();

        $presensiPerSiswa = PresensiSiswa::query()
            ->whereBetween('tanggal', [$this->dateStart, $this->dateEnd])
            ->whereIn('siswa_id', $siswaList->pluck('id'))
            ->when($cabangIds, fn ($q, $ids) => $q->whereIn('cabang_id', $ids))
            ->where('kelas_id', $kelasId)
            ->whereIn('status', self::STATUS_VALID)
            ->get()
            ->groupBy('siswa_id');

        $tanggalList = $this->getDateRange();

        return $siswaList->map(function ($siswa) use ($presensiPerSiswa, $tanggalList) {
            // Satu baris per sesi: satu siswa bisa punya beberapa baris di hari yang sama
            $semua = $presensiPerSiswa->get($siswa->id, collect());

            $perTanggal = $semua->groupBy(fn ($r) => Carbon::parse($r->tanggal)->toDateString());

            $kode = [];
            $harian = [];

            foreach ($tanggalList as $tanggal) {
                $info = self::kodePresensi($perTanggal->get($tanggal, collect())->pluck('status'));

                $kode[$tanggal] = $info;
                $harian[$tanggal] = $info['status'] ?? null;
            }

            return (object) [
                'siswa' => $siswa,
                'kode' => $kode,
                'harian' => $harian,
                // Rekap menghitung sesi, bukan hari: HD dihitung 2, HT dihitung 3
                'hadir' => $semua->where('status', 'hadir')->count(),
                'izin' => $semua->where('status', 'izin')->count(),
                'sakit' => $semua->where('status', 'sakit')->count(),
                'alpa' => $semua->where('status', 'alpa')->count(),
            ];
        })->values();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->extraAttributes([
                    'style' => 'background-color: #2563eb; color: #ffffff; border-color: #2563eb;',
                ])
                ->action(function () {
                    if (! $this->kelasTerpilih()) {
                        Notification::make()->warning()->title('Pilih Kelas / Program terlebih dahulu')->send();
                        return;
                    }

                    $rekap = $this->getRekap();

                    if ($rekap->isEmpty()) {
                        Notification::make()->warning()->title('Tidak ada data untuk diexport')->send();
                        return;
                    }

                    $spreadsheet = (new LaporanPresensiSiswaExport())
                        ->build($rekap, $this->getDateRange(), $this->metaExport());

                    return response()->streamDownload(function () use ($spreadsheet) {
                        (new Xlsx($spreadsheet))->save('php://output');
                    }, 'laporan-presensi-siswa-' . now()->format('Y-m-d_His') . '.xlsx');
                }),
        ];
    }

    protected function metaExport(): array
    {
        // Sama dengan logika getRekap(): null = semua cabang (hanya super_admin)
        $cabangIds = $this->isSuperAdmin()
            ? ($this->cabangId ? [$this->cabangId] : null)
            : $this->cabangIdsUser();

        $namaCabang = Cabang::query()
            ->when($cabangIds, fn ($q, $ids) => $q->whereIn('id', $ids))
            ->pluck('nama_cabang', 'id');

        return [
            'periode' => Carbon::parse($this->dateStart)->translatedFormat('d F Y')
                . ' s/d ' . Carbon::parse($this->dateEnd)->translatedFormat('d F Y'),
            'cabang' => $cabangIds === null ? 'Semua Cabang' : $namaCabang->implode(', '),
            'program' => Kelas::find($this->kelasTerpilih())?->nama_kelas ?? '-',
            'namaCabang' => $namaCabang->all(),
        ];
    }
}