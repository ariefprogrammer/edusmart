<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JadwalResource\Pages;
use App\Filament\Resources\JadwalResource\RelationManagers;
use App\Models\Jadwal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use App\Models\Concerns\ScopedToCabang;

class JadwalResource extends Resource
{
    use ScopedToCabang;
    protected static ?string $model = Jadwal::class;
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    // Menu
    protected static ?string $navigationLabel = 'Jadwal Kelas / Program';

    // Label
    protected static ?string $pluralModelLabel = 'Jadwal Kelas / Program';

    // Tombol create
    protected static ?string $modelLabel = 'Jadwal';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('cabang_id')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->live()
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->default(fn () => auth()->user()->cabang->first()?->id)
                    ->dehydrated(),

                Select::make('kelas_id')
                    ->label('Kelas / Program')
                    ->relationship(
                        'kelas',
                        'nama_kelas',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                            ->where('cabang_id', $get('cabang_id') ?? auth()->user()->cabang->first()?->id),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('guru_id')
                    ->relationship(
                        'guru',
                        'nama',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                            ->where('cabang_id', $get('cabang_id') ?? auth()->user()->cabang->first()?->id),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Select::make('hari')
                    ->options([
                        'Senin' => 'Senin', 'Selasa' => 'Selasa', 'Rabu' => 'Rabu',
                        'Kamis' => 'Kamis', 'Jumat' => 'Jumat', 'Sabtu' => 'Sabtu', 'Minggu' => 'Minggu',
                    ])
                    ->required(),

                Forms\Components\TimePicker::make('jam_mulai')
                    ->seconds(false)
                    ->required(),

                Forms\Components\TimePicker::make('jam_selesai')
                    ->seconds(false)
                    ->required()
                    ->after('jam_mulai'), // validasi: jam selesai harus setelah jam mulai

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Satu baris per kelas/program — ambil jadwal dengan id terbesar sebagai representasi.
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn(
                'id',
                Jadwal::query()->selectRaw('MAX(id)')->groupBy('kelas_id')
            ))
            ->recordUrl(null)
            ->recordAction('detail')
            ->columns([
                Tables\Columns\TextColumn::make('cabang.nama_cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kelas.nama_kelas')
                    ->label('Kelas / Program')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jumlah_jadwal')
                    ->label('Jumlah Hari Terjadwal')
                    ->getStateUsing(
                        fn (Jadwal $record) => Jadwal::query()
                            ->where('kelas_id', $record->kelas_id)
                            ->count()
                    ),
                Tables\Columns\IconColumn::make('ada_aktif')
                    ->label('Ada Jadwal Aktif')
                    ->boolean()
                    ->getStateUsing(
                        fn (Jadwal $record) => Jadwal::query()
                            ->where('kelas_id', $record->kelas_id)
                            ->where('is_active', true)
                            ->exists()
                    ),
            ])
            ->filters([
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
            ])
            ->actions([
                Tables\Actions\Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Jadwal $record) => 'Detail Jadwal — ' . ($record->kelas?->nama_kelas ?? '-'))
                    ->modalContent(fn (Jadwal $record) => view('filament.resources.jadwal.detail-modal', [
                        'kelasId' => $record->kelas_id,
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
            'index' => Pages\ListJadwals::route('/'),
            'create' => Pages\CreateJadwal::route('/create'),
            'edit' => Pages\EditJadwal::route('/{record}/edit'),
        ];
    }
}
