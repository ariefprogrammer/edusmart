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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Concerns\ScopedToCabang;

class ProgressSiswaResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = ProgressSiswa::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('cabang_id')
                    ->relationship('cabang', 'id')
                    ->required(),
                Forms\Components\Select::make('kelas_id')
                    ->relationship('kelas', 'id')
                    ->default(null),
                Forms\Components\Select::make('siswa_id')
                    ->relationship('siswa', 'id')
                    ->default(null),
                Forms\Components\Select::make('guru_id')
                    ->relationship('guru', 'id')
                    ->default(null),
                Forms\Components\DatePicker::make('tanggal')
                    ->required(),
                Forms\Components\Textarea::make('catatan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kelas.nama_kelas')->label('Program'),
                Tables\Columns\TextColumn::make('guru.nama')->label('Guru'),
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('catatan')->limit(50)->wrap(),
            ])
            ->filters([
                //
            ])
            ->actions([
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
