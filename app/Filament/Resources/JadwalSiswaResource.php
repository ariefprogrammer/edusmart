<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JadwalSiswaResource\Pages;
use App\Models\Concerns\ScopedToCabang;
use App\Models\Siswa;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class JadwalSiswaResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = Siswa::class;
    protected static ?string $slug = 'jadwal-siswa';
    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    protected static ?string $navigationLabel = 'Jadwal Siswa';
    protected static ?string $modelLabel = 'Jadwal Siswa';
    protected static ?string $pluralModelLabel = 'Jadwal Siswa';
    protected static ?string $navigationGroup = 'Manajemen Siswa';
    protected static ?int $navigationSort = 13;

    public static function table(Table $table): Table
    {
        return $table
            ->recordUrl(null)
            ->recordAction('detail')
            ->columns([
                Tables\Columns\TextColumn::make('nama')
                    ->label('Nama Siswa')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('cabang.nama_cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('kelasList.nama_kelas')
                    ->label('Program')
                    ->badge()
                    ->separator(','),

                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'aktif',
                        'warning' => 'cuti',
                        'danger' => 'keluar',
                    ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('cabang_id')
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
                    ->modalHeading(fn (Siswa $record) => 'Jadwal — ' . $record->nama)
                    ->modalContent(fn (Siswa $record) => view('filament.resources.jadwal-siswa.detail-modal', [
                        'siswaId' => $record->id,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl'),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListJadwalSiswas::route('/'),
        ];
    }
}