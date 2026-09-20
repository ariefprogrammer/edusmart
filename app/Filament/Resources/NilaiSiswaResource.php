<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NilaiSiswaResource\Pages;
use App\Filament\Resources\NilaiSiswaResource\RelationManagers;
use App\Models\NilaiSiswa;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Concerns\ScopedToCabang;

class NilaiSiswaResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = NilaiSiswa::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('cabang_id')
                    ->required()
                    ->numeric(),
                Forms\Components\Select::make('kelas_id')
                    ->relationship('kelas', 'id')
                    ->default(null),
                Forms\Components\Select::make('siswa_id')
                    ->relationship('siswa', 'id')
                    ->default(null),
                Forms\Components\Select::make('guru_id')
                    ->relationship('guru', 'id')
                    ->default(null),
                Forms\Components\Select::make('kategori_nilai_id')
                    ->relationship('kategoriNilai', 'id')
                    ->required(),
                Forms\Components\TextInput::make('nilai')
                    ->numeric()
                    ->default(null),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('siswa.nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kelas.nama_kelas')->label('Program'),
                Tables\Columns\TextColumn::make('kategoriNilai.nama_kategori')->label('Kategori'),
                Tables\Columns\TextColumn::make('nilai')->sortable(),
                Tables\Columns\TextColumn::make('guru.nama')->label('Guru'),
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
            'index' => Pages\ListNilaiSiswas::route('/'),
            'create' => Pages\CreateNilaiSiswa::route('/create'),
            'edit' => Pages\EditNilaiSiswa::route('/{record}/edit'),
        ];
    }
}
