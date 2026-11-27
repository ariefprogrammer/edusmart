<?php

namespace App\Filament\Pages;

use App\Models\Cabang;
use App\Models\Kelas;
use App\Services\LaporanSiswaAktifService;
use Carbon\CarbonImmutable;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\WithPagination;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Exports\LaporanSiswaAktifExport;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class LaporanSiswaAktif extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Manajemen Siswa';
    protected static ?int $navigationSort = 13;
    protected static ?string $navigationLabel = 'Laporan Siswa Aktif';
    protected static ?string $title = 'Laporan Siswa Aktif';

    protected static string $view = 'filament.pages.laporan-siswa-aktif';

    public ?array $data = [];
    public string $filterStatus = 'semua';
    public string $cari = '';

    use WithPagination;
    public int $perPage = 20;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['admin_cabang', 'koordinator_cabang', 'super_admin']) ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'bulan' => now()->format('Y-m'),
            'cabang_id' => null,
            'kelas_id' => null,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make(['default' => 1, 'md' => 3])->schema([
                    Select::make('bulan')
                        ->label('Bulan')
                        ->options(fn () => $this->opsiBulan())
                        ->selectablePlaceholder(false)
                        ->required()
                        ->live(),

                    Select::make('cabang_id')
                        ->label('Cabang')
                        ->options(fn () => $this->opsiCabang())
                        ->placeholder('Semua cabang')
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('kelas_id', null))
                        ->visible(fn () => $this->isSuperAdmin() || count($this->opsiCabang()) > 1),

                    Select::make('kelas_id')
                        ->label('Program')
                        ->options(fn (Get $get) => $this->opsiKelas($get('cabang_id')))
                        ->placeholder('Semua program')
                        ->searchable()
                        ->live(),
                ]),
            ])
            ->statePath('data');
    }

    #[Computed]
    public function laporan(): array
    {
        $kelasId = $this->data['kelas_id'] ?? null;

        return app(LaporanSiswaAktifService::class)->hitung(
            $this->bulanTerpilih(),
            $this->cabangIdsTerbatas($this->data['cabang_id'] ?? null),
            $kelasId ? (int) $kelasId : null,
        );
    }

    #[Computed]
    public function siswaTampil(): Collection
    {
        $kata = mb_strtolower(trim($this->cari));

        return collect($this->laporan['siswa'])
            ->when($this->filterStatus !== 'semua', fn ($c) => $c->where('status', $this->filterStatus))
            ->when($kata !== '', fn ($c) => $c->filter(fn ($s) => str_contains(mb_strtolower($s['nama']), $kata)))
            ->values();
    }

    #[Computed]
    public function ringkasanProgram(): array
    {
        $akhir = CarbonImmutable::parse($this->laporan['akhir'])->toDateString();
        $kelasId = $this->data['kelas_id'] ?? null;
        $cabangIds = $this->cabangIdsTerbatas($this->data['cabang_id'] ?? null);

        // Satu baris kelas_siswa = satu siswa di satu program, yang aktif pada tanggal posisi laporan
        $aktif = DB::table('kelas_siswa as ks')
            ->join('siswa as s', 's.id', '=', 'ks.siswa_id')
            ->whereNull('s.deleted_at')
            ->when($cabangIds !== null, fn ($q) => $q->whereIn('s.cabang_id', $cabangIds))
            ->when($kelasId, fn ($q) => $q->where('ks.kelas_id', (int) $kelasId))
            ->whereRaw(
                "(select d.status from kelas_siswa_detail as d
                where d.kelas_siswa_id = ks.id and date(d.tanggal) <= ?
                order by d.tanggal desc, d.id desc limit 1) = 'aktif'",
                [$akhir],
            );

        $jumlahProgram = (clone $aktif)->count();
        $jumlahSiswa = (clone $aktif)->distinct()->count('ks.siswa_id');

        return [
            'jumlah' => $jumlahProgram,
            'siswa' => $jumlahSiswa,
            'rasio' => $jumlahSiswa > 0 ? round($jumlahProgram / $jumlahSiswa, 1) : 0,
        ];
    }

    public function updatingFilterStatus(): void
    {
        $this->resetPage();
    }

    public function updatingCari(): void
    {
        $this->resetPage();
    }

    public function updatingPerPage(): void
    {
        $this->resetPage();
    }

    public function getSiswaHalamanProperty(): LengthAwarePaginator
    {
        $semua = $this->siswaTampil;
        $halaman = $this->getPage();

        return new LengthAwarePaginator(
            $semua->forPage($halaman, $this->perPage)->values(),
            $semua->count(),
            $this->perPage,
            $halaman,
            ['path' => request()->url()]
        );
    }

    protected function isSuperAdmin(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    /** null = tanpa pembatasan cabang (hanya super_admin tanpa pilihan cabang). */
    protected function cabangIdsTerbatas($pilihan = null): ?array
    {
        $pilihan = $pilihan ? (int) $pilihan : null;

        if ($this->isSuperAdmin()) {
            return $pilihan ? [$pilihan] : null;
        }

        $milikUser = auth()->user()->cabang->pluck('id')->map(fn ($id) => (int) $id)->all();

        // Pilihan dari browser hanya diterima jika termasuk cabang milik pengguna
        return $pilihan && in_array($pilihan, $milikUser, true) ? [$pilihan] : $milikUser;
    }

    protected function opsiCabang(): array
    {
        $ids = $this->cabangIdsTerbatas();

        return Cabang::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('nama_cabang')
            ->pluck('nama_cabang', 'id')
            ->all();
    }

    protected function opsiKelas($cabangId = null): array
    {
        $ids = $this->cabangIdsTerbatas($cabangId);

        return Kelas::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('cabang_id', $ids))
            ->orderBy('nama_kelas')
            ->pluck('nama_kelas', 'id')
            ->all();
    }

    protected function opsiBulan(): array
    {
        $sekarang = CarbonImmutable::now()->startOfMonth();
        $pertama = DB::table('kelas_siswa_detail')->min('tanggal');

        $mulai = $pertama ? CarbonImmutable::parse($pertama)->startOfMonth() : $sekarang;

        if ($mulai->greaterThan($sekarang)) {
            $mulai = $sekarang;
        }

        $opsi = [];

        for ($bulan = $sekarang; $bulan->greaterThanOrEqualTo($mulai); $bulan = $bulan->subMonthNoOverflow()) {
            $opsi[$bulan->format('Y-m')] = $bulan->locale('id')->translatedFormat('F Y');
        }

        return $opsi;
    }

    protected function bulanTerpilih(): string
    {
        $bulan = (string) ($this->data['bulan'] ?? '');

        return array_key_exists($bulan, $this->opsiBulan()) ? $bulan : now()->format('Y-m');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportExcel')
                ->label('Export Excel')
                ->extraAttributes([
                    'style' => 'background-color: #2563eb; color: #ffffff; border-color: #2563eb;',
                ])
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $siswaList = $this->siswaTampil;

                    if ($siswaList->isEmpty()) {
                        Notification::make()->warning()->title('Tidak ada data untuk diexport')->send();

                        return;
                    }

                    $spreadsheet = (new LaporanSiswaAktifExport())->build(
                        $this->laporan,
                        $this->ringkasanProgram,
                        $siswaList,
                        $this->metaExport(),
                    );

                    return response()->streamDownload(function () use ($spreadsheet) {
                        (new Xlsx($spreadsheet))->save('php://output');
                    }, 'laporan-siswa-aktif-' . $this->bulanTerpilih() . '.xlsx');
                }),
        ];
    }

    protected function metaExport(): array
    {
        $cabangIds = $this->cabangIdsTerbatas($this->data['cabang_id'] ?? null);
        $kelasId = $this->data['kelas_id'] ?? null;

        return [
            'bulan' => CarbonImmutable::parse($this->bulanTerpilih() . '-01')->locale('id')->translatedFormat('F Y'),
            'cabang' => $cabangIds === null
                ? 'Semua Cabang'
                : Cabang::whereIn('id', $cabangIds)->orderBy('nama_cabang')->pluck('nama_cabang')->implode(', '),
            'program' => $kelasId ? (Kelas::find($kelasId)?->nama_kelas ?? '-') : 'Semua Program',
            'status' => match ($this->filterStatus) {
                'aktif' => 'Aktif',
                'tidak_aktif' => 'Tidak aktif',
                default => 'Semua status',
            },
            'pencarian' => filled($this->cari) ? $this->cari : '-',
        ];
    }
}