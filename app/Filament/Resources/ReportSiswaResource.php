<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ReportSiswaResource\Pages;
use App\Models\Cabang;
use App\Models\Kelas;
use App\Models\Periode;
use App\Models\ReportSiswa;
use App\Models\Siswa;
use App\Services\ReportSiswaService;
use App\Support\ReportSiswaScope;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Arsip Report Siswa (snapshot). Report dibuat lewat modal "Buat Report" di halaman
 * daftar; setelah itu Lihat dan Unduh PDF hanya membaca ReportSiswa::$data.
 */
class ReportSiswaResource extends Resource
{
    protected static ?string $model = ReportSiswa::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-chart-bar';

    protected static ?string $navigationLabel = 'Report Siswa';

    protected static ?string $modelLabel = 'Report Siswa';

    protected static ?string $pluralModelLabel = 'Report Siswa';
    
    /** Batas siswa per sekali "Buat Report" (proses berjalan sinkron). */
    public const BATCH_MAKS = 100;

    /** Batas report per PDF gabungan (dompdf memakai banyak memori). */
    public const PDF_GABUNGAN_MAKS = 50;

    // ------------------------------------------------------------------ query

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();

        $user = auth()->user();

        if ($user && ! ReportSiswaScope::isSuperAdmin($user)) {
            $query->whereIn('cabang_id', ReportSiswaScope::cabangIds($user));

            // Guru hanya melihat report yang dia buat sendiri.
            if (ReportSiswaScope::isGuruOnly($user)) {
                $query->where('generated_by', $user->id);
            }
        }

        return $query;
    }

    /** Snapshot tidak bisa diedit. */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

        // ------------------------------------------------------- form "Buat Report"

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    /** Skema form modal "Buat Report" (satu siswa, beberapa siswa, atau satu program). */
    public static function formSchema(): array
    {
        return [
            Forms\Components\Select::make('cabang_id')
                ->label('Cabang')
                ->options(fn () => static::cabangOptions())
                ->default(fn () => array_key_first(static::cabangOptions()))
                ->live()
                ->afterStateUpdated(function (Set $set) {
                    static::resetSiswa($set);
                    $set('periode_bulan_ids', []);
                    $set('periode_semester_ids', []);
                })
                ->required()
                ->visible(fn () => count(static::cabangOptions()) > 1)
                ->columnSpanFull(),

            Forms\Components\Radio::make('mode')
                ->label('Siswa yang dibuatkan report')
                ->options([
                    'manual' => 'Pilih siswa satu per satu',
                    'program' => 'Semua siswa dalam satu program',
                ])
                ->default('manual')
                ->inline()
                ->live()
                ->afterStateUpdated(fn (Set $set) => static::resetSiswa($set))
                ->columnSpanFull(),

            Forms\Components\Select::make('siswa_ids')
                ->label('Siswa')
                ->helperText('Bisa pilih lebih dari satu siswa (maks. '.static::BATCH_MAKS.'). Tiap siswa mendapat report sendiri.')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (Get $get): array => ReportSiswaScope::siswaQuery(
                    auth()->user(),
                    static::resolveCabangId(static::stateDari($get)),
                )
                    ->orderBy('nama')
                    ->limit(50)
                    ->pluck('nama', 'id')
                    ->all())
                ->live()
                ->maxItems(static::BATCH_MAKS)
                ->getSearchResultsUsing(fn (string $search, Get $get): array => ReportSiswaScope::siswaQuery(
                    auth()->user(),
                    static::resolveCabangId(static::stateDari($get)),
                )
                    ->where('nama', 'like', '%'.$search.'%')
                    ->orderBy('nama')
                    ->limit(50)
                    ->pluck('nama', 'id')
                    ->all())
                ->getOptionLabelsUsing(fn (array $values): array => Siswa::withTrashed()
                    ->whereIn('id', $values)
                    ->pluck('nama', 'id')
                    ->all())
                ->afterStateUpdated(fn (Set $set) => $set('kelas_id', null))
                ->visible(fn (Get $get) => $get('mode') !== 'program')
                ->required()
                ->columnSpanFull(),

            Forms\Components\Select::make('kelas_id')
                ->label(fn (Get $get) => $get('mode') === 'program' ? 'Program' : 'Batasi ke program (opsional)')
                ->placeholder(fn (Get $get) => $get('mode') === 'program' ? 'Pilih program' : 'Semua program')
                ->helperText(function (Get $get): string {
                    $state = static::stateDari($get);

                    if (($state['mode'] ?? 'manual') !== 'program') {
                        return 'Kosongkan untuk memasukkan semua program milik tiap siswa.';
                    }

                    if (blank($state['kelas_id'])) {
                        return 'Report berisi data program ini saja.';
                    }

                    return static::rosterQuery($state)->count().' siswa akan dibuatkan report (maks. '.static::BATCH_MAKS.'). Report berisi data program ini saja.';
                })
                ->options(fn (Get $get) => static::kelasOptions(static::stateDari($get)))
                ->searchable()
                ->live()
                ->required(fn (Get $get) => $get('mode') === 'program')
                ->columnSpanFull(),

            Forms\Components\Toggle::make('hanya_aktif')
                ->label('Hanya siswa aktif')
                ->default(true)
                ->live()
                ->visible(fn (Get $get) => $get('mode') === 'program')
                ->columnSpanFull(),

            Forms\Components\CheckboxList::make('bagian')
                ->label('Bagian yang dimasukkan')
                ->options(ReportSiswa::BAGIAN)
                ->default(array_keys(ReportSiswa::BAGIAN))
                ->required()
                ->minItems(1)
                ->live()
                ->columns(3)
                ->columnSpanFull(),

            Forms\Components\Select::make('periode_bulan_ids')
                ->label('Periode Bulan (Presensi & Progress)')
                ->helperText('Bisa pilih lebih dari satu periode.')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (Get $get) => static::periodeOptions(static::resolveCabangId(static::stateDari($get)), 'bulan'))
                ->visible(fn (Get $get) => (bool) array_intersect((array) $get('bagian'), ['presensi', 'progress']))
                ->required()
                ->columnSpanFull(),

            Forms\Components\Select::make('periode_semester_ids')
                ->label('Periode Semester (Nilai)')
                ->helperText('Bisa pilih lebih dari satu periode.')
                ->multiple()
                ->searchable()
                ->preload()
                ->options(fn (Get $get) => static::periodeOptions(static::resolveCabangId(static::stateDari($get)), 'semester'))
                ->visible(fn (Get $get) => in_array('nilai', (array) $get('bagian'), true))
                ->required()
                ->columnSpanFull(),

            Forms\Components\Toggle::make('lewati_kosong')
                ->label('Lewati siswa yang tidak punya data')
                ->helperText('Siswa tanpa presensi, progress, atau nilai pada periode terpilih tidak dibuatkan report.')
                ->default(true)
                ->visible(fn (Get $get) => $get('mode') === 'program' || count((array) $get('siswa_ids')) > 1)
                ->columnSpanFull(),

            Forms\Components\Textarea::make('catatan')
                ->label('Catatan (opsional)')
                ->helperText('Berikan label pada setiap raport agar lebih mudah untuk pencarian. Dicantumkan di semua report yang dibuat.')
                ->rows(2)
                ->maxLength(1000)
                ->columnSpanFull(),
        ];
    }

    protected static function resetSiswa(Set $set): void
    {
        $set('siswa_ids', []);
        $set('kelas_id', null);
    }

    /** Ambil nilai form yang dibutuhkan helper opsi dari $get. */
    protected static function stateDari(Get $get): array
    {
        return [
            'mode' => $get('mode') ?? 'manual',
            'cabang_id' => $get('cabang_id'),
            'siswa_ids' => (array) $get('siswa_ids'),
            'kelas_id' => $get('kelas_id'),
            'hanya_aktif' => $get('hanya_aktif') ?? true,
        ];
    }

    protected static function cabangOptions(): array
    {
        $user = auth()->user();
        $ids = ReportSiswaScope::cabangIds($user);

        return Cabang::query()
            ->when($ids !== null, fn ($q) => $q->whereIn('id', $ids))
            ->orderBy('nama_cabang')
            ->pluck('nama_cabang', 'id')
            ->all();
    }

    /** Cabang yang dipilih di form; bila hanya ada satu cabang yang boleh diakses, otomatis itu. */
    protected static function resolveCabangId(array $state): ?int
    {
        if (filled($state['cabang_id'] ?? null)) {
            return (int) $state['cabang_id'];
        }

        $opsi = static::cabangOptions();

        return count($opsi) === 1 ? (int) array_key_first($opsi) : null;
    }

    protected static function periodeOptions(?int $cabangId, string $tipe): array
    {
        if (! $cabangId) {
            return [];
        }

        return Periode::query()
            ->where('cabang_id', $cabangId)
            ->where('tipe', $tipe)
            ->orderByDesc('tanggal_mulai')
            ->pluck('nama_periode', 'id')
            ->all();
    }

    protected static function kelasOptions(array $state): array
    {
        $kelasGuru = ReportSiswaScope::guruKelasIds(auth()->user());

        if (($state['mode'] ?? 'manual') === 'program') {
            $cabangId = static::resolveCabangId($state);

            if (! $cabangId) {
                return [];
            }

            $query = Kelas::query()->where('cabang_id', $cabangId);
        } else {
            $siswaIds = array_filter((array) ($state['siswa_ids'] ?? []));

            if ($siswaIds === []) {
                return [];
            }

            $query = Kelas::query()->whereIn(
                'id',
                DB::table('kelas_siswa')->whereIn('siswa_id', $siswaIds)->select('kelas_id')
            );
        }

        return $query
            ->when($kelasGuru !== null, fn ($q) => $q->whereIn('id', $kelasGuru))
            ->orderBy('nama_kelas')
            ->pluck('nama_kelas', 'id')
            ->all();
    }

    /** Daftar siswa untuk mode "semua siswa dalam satu program". */
    protected static function rosterQuery(array $state): Builder
    {
        $aktif = (bool) ($state['hanya_aktif'] ?? true);

        return ReportSiswaScope::siswaQuery(auth()->user(), static::resolveCabangId($state))
            ->whereHas('kelasList', fn ($q) => $q
                ->where('kelas.id', (int) ($state['kelas_id'] ?? 0))
                ->when($aktif, fn ($q2) => $q2->where('kelas_siswa.is_active', true)))
            ->when($aktif, fn ($q) => $q->where('siswa.is_active', true))
            ->orderBy('nama');
    }

    protected static function daftarSiswa(array $data): Collection
    {
        if (($data['mode'] ?? 'manual') === 'program') {
            return static::rosterQuery($data)->limit(static::BATCH_MAKS + 1)->get();
        }

        return Siswa::withTrashed()
            ->whereIn('id', (array) ($data['siswa_ids'] ?? []))
            ->orderBy('nama')
            ->get();
    }

    // ---------------------------------------------------------- proses "Buat Report"

    /**
     * Dipanggil oleh aksi "Buat Report". Satu siswa -> satu report; banyak siswa -> satu
     * snapshot per siswa dalam satu batch.
     *
     * @return array{url: ?string}|null  null = gagal (modal tetap terbuka)
     */
    public static function buatDariForm(array $data): ?array
    {
        $siswaList = static::daftarSiswa($data);

        if ($siswaList->isEmpty()) {
            Notification::make()->warning()->title('Tidak ada siswa yang cocok')
                ->body('Periksa pilihan siswa, program, atau opsi "Hanya siswa aktif".')->send();

            return null;
        }

        if ($siswaList->count() > static::BATCH_MAKS) {
            Notification::make()->danger()->title('Terlalu banyak siswa')
                ->body('Maksimal '.static::BATCH_MAKS.' siswa sekali proses. Pilih program lain atau pilih siswa manual.')->send();

            return null;
        }

        $parameter = [
            'bagian' => $data['bagian'] ?? [],
            'periode_bulan_ids' => $data['periode_bulan_ids'] ?? [],
            'periode_semester_ids' => $data['periode_semester_ids'] ?? [],
            'kelas_id' => $data['kelas_id'] ?? null,
            'catatan' => $data['catatan'] ?? null,
            'lewati_kosong' => (bool) ($data['lewati_kosong'] ?? false),
        ];

        if ($siswaList->count() === 1) {
            // Satu siswa: pengguna memang meminta siswa itu, jadi jangan dilewati walau kosong.
            $report = static::jalankanGenerate($siswaList->first(), ['lewati_kosong' => false] + $parameter);

            return $report
                ? ['url' => static::getUrl('view', ['record' => $report])]
                : null;
        }

        try {
            $hasil = app(ReportSiswaService::class)->generateBatch($siswaList, $parameter, auth()->user());
        } catch (ValidationException $e) {
            Notification::make()->danger()->title('Report gagal dibuat')
                ->body(collect($e->errors())->flatten()->first() ?? 'Data report tidak valid.')->send();

            return null;
        }

        return static::laporkanBatch($hasil);
    }

    /** @return array{url: ?string}|null */
    protected static function laporkanBatch(array $hasil): ?array
    {
        $jumlah = $hasil['berhasil']->count();
        $dilewati = $hasil['dilewati'];
        $gagal = $hasil['gagal'];

        $bagian = [];

        if ($dilewati !== []) {
            $bagian[] = count($dilewati).' siswa dilewati karena tidak punya data ('.static::ringkasNama($dilewati).').';
        }

        if ($gagal !== []) {
            $bagian[] = count($gagal).' gagal: '.collect($gagal)
                ->take(5)
                ->map(fn (array $g) => $g['nama'].' — '.$g['pesan'])
                ->implode('; ').(count($gagal) > 5 ? '; dan lainnya.' : '.');
        }

        if ($jumlah === 0) {
            Notification::make()->warning()->title('Tidak ada report yang dibuat')
                ->body(implode(' ', $bagian))->persistent()->send();

            return null;
        }

        $notif = Notification::make()
            ->title($jumlah.' report berhasil dibuat')
            ->body(implode(' ', $bagian) ?: null)
            ->actions([
                NotificationAction::make('lihat')
                    ->label('Lihat batch ini')
                    ->button()
                    ->url(static::getUrl('index', ['tableFilters' => ['batch' => ['value' => $hasil['batch_uuid']]]])),
            ]);

        ($bagian === [] ? $notif->success() : $notif->warning()->persistent())->send();

        return ['url' => null];
    }

    protected static function ringkasNama(array $nama, int $maks = 5): string
    {
        $tampil = implode(', ', array_slice($nama, 0, $maks));

        return count($nama) > $maks ? $tampil.', dan lainnya' : $tampil;
    }

    // -------------------------------------------------------------- aksi bersama

    /** Jalankan service dan ubah error jadi notifikasi. null = gagal. */
    public static function jalankanGenerate(Siswa $siswa, array $parameter): ?ReportSiswa
    {
        try {
            return app(ReportSiswaService::class)->generate($siswa, $parameter, auth()->user());
        } catch (ValidationException $e) {
            $pesan = collect($e->errors())->flatten()->first() ?? 'Data report tidak valid.';
        } catch (AuthorizationException $e) {
            $pesan = $e->getMessage() ?: 'Anda tidak berhak membuat report ini.';
        }

        Notification::make()->danger()->title('Report gagal dibuat')->body($pesan)->send();

        return null;
    }

    /** Buat snapshot baru dengan filter yang sama (data terbaru). Snapshot lama tetap ada. */
    public static function buatUlang(ReportSiswa $record): ?ReportSiswa
    {
        $siswa = $record->siswa;

        if (! $siswa) {
            Notification::make()
                ->danger()
                ->title('Tidak bisa dibuat ulang')
                ->body('Data siswa sudah dihapus permanen.')
                ->send();

            return null;
        }

        $baru = static::jalankanGenerate($siswa, ($record->parameter ?? []) + ['catatan' => $record->catatan]);

        if ($baru) {
            Notification::make()->success()->title('Report baru berhasil dibuat')->send();
        }

        return $baru;
    }

    public static function pdfResponse(ReportSiswa $record): StreamedResponse
    {
        $pdf = Pdf::loadView('reports.report-siswa-pdf', [
            'report' => $record,
            'data' => $record->data,
        ])->setPaper('a4');

        $nama = 'Report-'.Str::slug($record->siswa_nama).'-'.$record->generated_at->format('Ymd').'.pdf';

        return response()->streamDownload(fn () => print ($pdf->output()), $nama);
    }

        /** Satu PDF berisi banyak report; tiap report dimulai di halaman baru, dirender dari snapshot. */
    public static function pdfGabunganResponse(Collection $records): ?StreamedResponse
    {
        if ($records->count() > static::PDF_GABUNGAN_MAKS) {
            Notification::make()->danger()->title('Terlalu banyak report untuk satu PDF')
                ->body('Maksimal '.static::PDF_GABUNGAN_MAKS.' report per PDF gabungan. Pilih lebih sedikit, atau unduh per siswa.')->send();

            return null;
        }

        @ini_set('memory_limit', '512M');
        @set_time_limit(300);

        $pdf = Pdf::loadView('reports.report-siswa-pdf-gabungan', [
            'reports' => $records->sortBy('siswa_nama')->values(),
        ])->setPaper('a4');

        return response()->streamDownload(
            fn () => print ($pdf->output()),
            'Report-Siswa-Gabungan-'.now()->format('Ymd-His').'.pdf'
        );
    }

    /** Opsi filter batch: 20 batch terbaru yang boleh dilihat pengguna. */
    protected static function batchOptions(): array
    {
        return static::getEloquentQuery()
            ->whereNotNull('batch_uuid')
            ->selectRaw('batch_uuid, count(*) as jumlah, max(generated_at) as waktu')
            ->groupBy('batch_uuid')
            ->orderByDesc('waktu')
            ->limit(20)
            ->get()
            ->mapWithKeys(fn ($b) => [
                $b->batch_uuid => \Carbon\Carbon::parse($b->waktu)->format('d M Y H:i').' · '.$b->jumlah.' report',
            ])
            ->all();
    }

    // ------------------------------------------------------------------- daftar

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('siswa_nama')
                    ->label('Siswa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cabang_nama')
                    ->label('Cabang')
                    ->searchable()
                    ->visible(fn () => ReportSiswaScope::isSuperAdmin(auth()->user())),

                Tables\Columns\TextColumn::make('bagian')
                    ->label('Bagian')
                    ->badge()
                    ->state(fn (ReportSiswa $record): array => collect($record->parameter['bagian'] ?? [])
                        ->map(fn ($b) => ReportSiswa::BAGIAN[$b] ?? $b)
                        ->all()),

                Tables\Columns\TextColumn::make('generated_by_nama')
                    ->label('Dibuat oleh')
                    ->toggleable(),
                
                Tables\Columns\TextColumn::make('catatan')
                    ->label('Catatan')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('generated_at')
                    ->label('Dibuat pada')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
                
            ])
            ->defaultSort('generated_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->options(fn () => static::cabangOptions())
                    ->visible(fn () => ReportSiswaScope::isSuperAdmin(auth()->user())),

                Tables\Filters\SelectFilter::make('batch')
                    ->label('Batch')
                    ->options(fn () => static::batchOptions())
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->where('batch_uuid', $data['value'])
                        : $query),

                Tables\Filters\Filter::make('generated_at')
                    ->form([
                        Forms\Components\DatePicker::make('dari')->label('Dibuat dari'),
                        Forms\Components\DatePicker::make('sampai')->label('Dibuat sampai'),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['dari'] ?? null, fn ($q, $v) => $q->whereDate('generated_at', '>=', $v))
                        ->when($data['sampai'] ?? null, fn ($q, $v) => $q->whereDate('generated_at', '<=', $v))),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('Lihat'),

                Tables\Actions\Action::make('unduhPdf')
                    ->label('PDF')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(fn (ReportSiswa $record) => static::pdfResponse($record)),

                Tables\Actions\Action::make('buatUlang')
                    ->label('Buat Ulang')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->modalDescription('Report baru dibuat dengan filter yang sama memakai data terbaru. Report ini tetap disimpan.')
                    ->visible(fn () => static::canCreate())
                    ->action(function (ReportSiswa $record): void {
                        static::buatUlang($record);
                    }),

                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('unduhGabungan')
                        ->label('Unduh PDF Gabungan')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->action(fn (Collection $records) => static::pdfGabunganResponse($records))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    // -------------------------------------------------------------------- lihat

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\ViewEntry::make('data')
                ->hiddenLabel()
                ->view('reports.partials.report-siswa-screen')
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListReportSiswas::route('/'),
            'view' => Pages\ViewReportSiswa::route('/{record}'),
        ];
    }
}
