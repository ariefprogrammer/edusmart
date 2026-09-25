<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProgressSiswaResource\Pages;
use App\Filament\Resources\ProgressSiswaResource\RelationManagers;
use App\Models\ProgressSiswa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use App\Exports\ProgressSiswaReportExport;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Concerns\ScopedToCabang;

class ProgressSiswaResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = ProgressSiswa::class;

    protected static ?string $navigationIcon = 'heroicon-o-presentation-chart-line';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('cabang_id')
                    ->relationship(
                        'cabang',
                        'nama_cabang',
                        modifyQueryUsing: fn (Builder $query) => static::isCabangRestricted()
                            ? $query->whereIn('id', static::getScopedCabangIds())
                            : $query,
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->default(fn () => static::isCabangRestricted() && count(static::getScopedCabangIds()) === 1
                        ? static::getScopedCabangIds()[0]
                        : null)
                    ->disabled(fn () => static::isCabangRestricted() && count(static::getScopedCabangIds()) === 1)
                    ->dehydrated(),
                Forms\Components\Select::make('kelas_id')
                    ->relationship('kelas', 'nama_kelas')
                    ->searchable()
                    ->preload()
                    ->default(null),
                Forms\Components\Select::make('siswa_id')
                    ->relationship('siswa', 'nama')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\Select::make('guru_id')
                    ->relationship('guru', 'nama')
                    ->searchable()
                    ->preload()
                    ->required(),
                Forms\Components\DatePicker::make('tanggal')
                    ->required(),
                Forms\Components\Textarea::make('catatan')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Ambil hanya 1 baris per siswa: catatan progress terbarunya (berdasarkan id terbesar).
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn(
                'id',
                ProgressSiswa::query()->selectRaw('MAX(id)')->groupBy('siswa_id')
            ))
            ->columns([
                Tables\Columns\TextColumn::make('cabang.nama_cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Siswa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kelas.nama_kelas')
                    ->label('Program Terakhir')
                    ->searchable(),
                Tables\Columns\TextColumn::make('guru.nama')
                    ->label('Guru Terakhir'),
                Tables\Columns\TextColumn::make('tanggal')
                    ->label('Tanggal Terakhir')
                    ->date('d M Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('catatan')
                    ->label('Catatan Terakhir')
                    ->limit(50)
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => ! static::isCabangRestricted()),

                SelectFilter::make('kelas_id')
                    ->label('Program')
                    ->relationship('kelas', 'nama_kelas')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('guru_id')
                    ->label('Guru')
                    ->relationship('guru', 'nama')
                    ->searchable()
                    ->preload(),

                Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_dari')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('tanggal_sampai')
                            ->label('Sampai Tanggal'),
                    ])
                    ->columns(2)
                    ->columnSpan(2)
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal_dari'],
                                fn (Builder $query, $date) => $query->whereDate('tanggal', '>=', $date),
                            )
                            ->when(
                                $data['tanggal_sampai'],
                                fn (Builder $query, $date) => $query->whereDate('tanggal', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['tanggal_dari'] ?? null) {
                            $indicators[] = 'Dari ' . \Carbon\Carbon::parse($data['tanggal_dari'])->format('d M Y');
                        }

                        if ($data['tanggal_sampai'] ?? null) {
                            $indicators[] = 'Sampai ' . \Carbon\Carbon::parse($data['tanggal_sampai'])->format('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                Tables\Actions\Action::make('exportLaporan')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->extraAttributes([
                            'style' => 'background-color: #2563eb; color: #ffffff; border-color: #2563eb;',
                        ])
                    ->action(function ($livewire) {
                        $siswaIds = $livewire->getFilteredSortedTableQuery()->pluck('siswa_id');

                        $filters = $livewire->tableFilters ?? [];

                        $cabangId = $filters['cabang_id']['value'] ?? null;
                        $kelasId = $filters['kelas_id']['value'] ?? null;
                        $guruId = $filters['guru_id']['value'] ?? null;
                        $tanggalDari = $filters['tanggal']['tanggal_dari'] ?? null;
                        $tanggalSampai = $filters['tanggal']['tanggal_sampai'] ?? null;

                        $rows = ProgressSiswa::query()
                            ->whereIn('siswa_id', $siswaIds)
                            ->whereNotNull('kelas_id')
                            ->with(['siswa', 'kelas', 'cabang'])
                            ->when(
                                static::isCabangRestricted(),
                                fn ($q) => $q->whereIn('cabang_id', static::getScopedCabangIds()),
                            )
                            ->when($cabangId, fn ($q, $value) => $q->where('cabang_id', $value))
                            ->when($kelasId, fn ($q, $value) => $q->where('kelas_id', $value))
                            ->when($guruId, fn ($q, $value) => $q->where('guru_id', $value))
                            ->when($tanggalDari, fn ($q, $date) => $q->whereDate('tanggal', '>=', $date))
                            ->when($tanggalSampai, fn ($q, $date) => $q->whereDate('tanggal', '<=', $date))
                            ->orderBy('cabang_id')
                            ->orderBy('siswa_id')
                            ->orderBy('tanggal')
                            ->get()
                            ->filter(fn ($item) => $item->kelas !== null);

                        $spreadsheet = (new ProgressSiswaReportExport())->build($rows);

                        return response()->streamDownload(function () use ($spreadsheet) {
                            (new Xlsx($spreadsheet))->save('php://output');
                        }, 'laporan-progress-siswa-' . now()->format('Y-m-d_His') . '.xlsx');
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (ProgressSiswa $record) => 'Detail Progress — ' . ($record->siswa?->nama ?? '-'))
                    ->modalContent(function (ProgressSiswa $record, $livewire) {
                        $filters = $livewire->tableFilters ?? [];

                        $cabangId = $filters['cabang_id']['value'] ?? null;
                        $kelasId = $filters['kelas_id']['value'] ?? null;
                        $guruId = $filters['guru_id']['value'] ?? null;
                        $tanggalDari = $filters['tanggal']['tanggal_dari'] ?? null;
                        $tanggalSampai = $filters['tanggal']['tanggal_sampai'] ?? null;

                        $programs = ProgressSiswa::query()
                            ->where('siswa_id', $record->siswa_id)
                            ->whereNotNull('kelas_id')
                            ->with('kelas')
                            ->when(
                                static::isCabangRestricted(),
                                fn ($q) => $q->whereIn('cabang_id', static::getScopedCabangIds()),
                            )
                            ->when($cabangId, fn ($q, $value) => $q->where('cabang_id', $value))
                            ->when($kelasId, fn ($q, $value) => $q->where('kelas_id', $value))
                            ->when($guruId, fn ($q, $value) => $q->where('guru_id', $value))
                            ->when($tanggalDari, fn ($q, $date) => $q->whereDate('tanggal', '>=', $date))
                            ->when($tanggalSampai, fn ($q, $date) => $q->whereDate('tanggal', '<=', $date))
                            ->orderBy('tanggal')
                            ->get()
                            ->filter(fn ($item) => $item->kelas !== null)
                            ->groupBy(fn ($item) => $item->kelas->nama_kelas);

                        return view('filament.resources.progress-siswa.detail-modal', [
                            'programs' => $programs,
                        ]);
                    })
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl'),

                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProgressSiswas::route('/'),
            'create' => Pages\CreateProgressSiswa::route('/create'),
            'edit' => Pages\EditProgressSiswa::route('/{record}/edit'),
        ];
    }
}