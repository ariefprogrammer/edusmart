<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PresensiJadwalResource\Pages;
use App\Filament\Resources\PresensiJadwalResource\RelationManagers;
use App\Models\PresensiJadwal;
use App\Models\Kelas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Concerns\ScopedToCabang;

class PresensiJadwalResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = PresensiJadwal::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';
    protected static ?string $navigationGroup = 'Penugasan & Presensi';
    protected static ?int $navigationSort = 8;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('cabang_id')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->default(fn () => auth()->user()->cabang->first()?->id)
                    ->dehydrated(),

                Forms\Components\Select::make('jadwal_id')
                    ->label('Jadwal')
                    ->relationship(
                        'jadwal',
                        'id',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                            ->where('cabang_id', $get('cabang_id') ?? auth()->user()->cabang->first()?->id),
                    )
                    ->getOptionLabelFromRecordUsing(
                        fn ($record) => trim(
                            ($record->kelas?->nama_kelas ?? '-') . ' — ' . $record->hari .
                            ' (' . \Carbon\Carbon::parse($record->jam_mulai)->format('H:i') . ')'
                        )
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('guru_id')
                    ->relationship(
                        'guru',
                        'nama',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                            ->where('cabang_id', $get('cabang_id') ?? auth()->user()->cabang->first()?->id),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\DatePicker::make('tanggal')
                    ->required(),
                Forms\Components\DateTimePicker::make('check_in'),
                Forms\Components\TextInput::make('check_in_lat')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('check_in_lng')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('check_in_accuracy')
                    ->numeric()
                    ->default(null),
                Forms\Components\DateTimePicker::make('check_out'),
                Forms\Components\TextInput::make('check_out_lat')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('check_out_lng')
                    ->numeric()
                    ->default(null),
                Forms\Components\TextInput::make('check_out_accuracy')
                    ->numeric()
                    ->default(null),
                Forms\Components\Select::make('status_masuk')
                    ->options([
                        'hadir' => 'Hadir',
                        'terlambat' => 'Terlambat',
                    ]),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Satu baris per kelas/program — ambil presensi dengan id terbesar sebagai representasi.
            ->modifyQueryUsing(function (Builder $query) {
                $latestIds = PresensiJadwal::query()
                    ->join('jadwal', 'jadwal.id', '=', 'presensi_jadwal.jadwal_id')
                    ->groupBy('jadwal.kelas_id')
                    ->selectRaw('MAX(presensi_jadwal.id) as id');

                return $query->whereIn('id', $latestIds);
            })
            ->recordUrl(null)
            ->recordAction('detail')
            ->columns([
                Tables\Columns\TextColumn::make('cabang.nama_cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jadwal.kelas.nama_kelas')
                    ->label('Kelas / Program')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jumlah_presensi')
                    ->label('Jumlah Presensi')
                    ->getStateUsing(
                        fn (PresensiJadwal $record) => PresensiJadwal::query()
                            ->whereHas('jadwal', fn ($q) => $q->where('kelas_id', $record->jadwal?->kelas_id))
                            ->count()
                    ),
                Tables\Columns\IconColumn::make('kehadiran')
                    ->label('Kehadiran')
                    ->getStateUsing(
                        fn (PresensiJadwal $record) => ! PresensiJadwal::query()
                            ->whereHas('jadwal', fn ($q) => $q->where('kelas_id', $record->jadwal?->kelas_id))
                            ->where('status_masuk', 'terlambat')
                            ->exists()
                    )
                    ->icon(fn (bool $state) => $state
                        ? 'heroicon-o-check-circle'
                        : 'heroicon-o-exclamation-triangle')
                    ->color(fn (bool $state) => $state ? 'success' : 'warning'),
            ])
            ->filters([
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),

                SelectFilter::make('kelas_id')
                    ->label('Kelas / Program')
                    ->options(fn () => Kelas::query()->pluck('nama_kelas', 'id'))
                    ->searchable()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $q, $value) => $q->whereHas('jadwal', fn ($jq) => $jq->where('kelas_id', $value)),
                    )),

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
            ->actions([
                Tables\Actions\Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (PresensiJadwal $record) => 'Detail Presensi — ' . ($record->jadwal?->kelas?->nama_kelas ?? '-'))
                    ->modalContent(fn (PresensiJadwal $record) => view('filament.resources.presensi-jadwal.detail-modal', [
                        'kelasId' => $record->jadwal?->kelas_id,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl'),
            ])
            ->bulkActions([
                //
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
            'index' => Pages\ListPresensiJadwals::route('/'),
            'create' => Pages\CreatePresensiJadwal::route('/create'),
            'edit' => Pages\EditPresensiJadwal::route('/{record}/edit'),
        ];
    }
}