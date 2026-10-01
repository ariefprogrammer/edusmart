<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NilaiSiswaResource\Pages;
use App\Filament\Resources\NilaiSiswaResource\RelationManagers;
use App\Models\NilaiSiswa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Components\Select;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Concerns\ScopedToCabang;

class NilaiSiswaResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = NilaiSiswa::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Manajemen Siswa';
    protected static ?int $navigationSort = 14;

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

                Select::make('siswa_id')
                    ->relationship(
                        'siswa',
                        'nama',
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

                Select::make('kategori_nilai_id')
                    ->label('Kategori Nilai')
                    ->relationship('kategoriNilai', 'nama_kategori')
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\TextInput::make('nilai')
                    ->numeric()
                    ->required(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Satu baris per siswa — ambil nilai dengan id terbesar sebagai representasi.
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn(
                'id',
                NilaiSiswa::query()->selectRaw('MAX(id)')->groupBy('siswa_id')
            ))
            ->recordUrl(null)
            ->recordAction('detail')
            ->columns([
                Tables\Columns\TextColumn::make('cabang.nama_cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('siswa.nama')
                    ->label('Siswa')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jumlah_nilai')
                    ->label('Jumlah Catatan')
                    ->getStateUsing(
                        fn (NilaiSiswa $record) => NilaiSiswa::query()
                            ->where('siswa_id', $record->siswa_id)
                            ->count()
                    ),
                Tables\Columns\TextColumn::make('rata_rata')
                    ->label('Rata-rata Nilai')
                    ->getStateUsing(
                        fn (NilaiSiswa $record) => round(
                            NilaiSiswa::query()
                                ->where('siswa_id', $record->siswa_id)
                                ->avg('nilai') ?? 0,
                            1
                        )
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
                    ->modalHeading(fn (NilaiSiswa $record) => 'Detail Nilai — ' . ($record->siswa?->nama ?? '-'))
                    ->modalContent(fn (NilaiSiswa $record) => view('filament.resources.nilai-siswa.detail-modal', [
                        'siswaId' => $record->siswa_id,
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
            'index' => Pages\ListNilaiSiswas::route('/'),
            'create' => Pages\CreateNilaiSiswa::route('/create'),
            'edit' => Pages\EditNilaiSiswa::route('/{record}/edit'),
        ];
    }
}