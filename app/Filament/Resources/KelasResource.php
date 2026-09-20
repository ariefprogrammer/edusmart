<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KelasResource\Pages;
use App\Filament\Resources\KelasResource\RelationManagers;
use App\Models\Kelas;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use App\Models\Concerns\ScopedToCabang;
use Filament\Tables\Filters\SelectFilter;
use App\Filament\Resources\KelasResource\RelationManagers\SiswaRelationManager;

class KelasResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = Kelas::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-group';

    // Menu
    protected static ?string $navigationLabel = 'Kelas / Program';

    // Label
    protected static ?string $pluralModelLabel = 'Kelas / Program';

    // Tombol create
    protected static ?string $modelLabel = 'Kelas / Program';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Select::make('cabang_id')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->columnSpanFull()
                    ->visible(fn () => auth()->user()->hasRole('super_admin'))
                    ->default(fn () => auth()->user()->cabang->first()?->id)
                    ->dehydrated(),

                Forms\Components\TextInput::make('nama_kelas')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Forms\Components\Textarea::make('keterangan')
                    ->columnSpanFull(),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('cabang.nama_cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('nama_kelas')
                    ->label('Nama Kelas / Program')
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('kelola')
                    ->label('Kelola Program')
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->url(fn (Kelas $record) => \App\Filament\Pages\ManajemenProgram::getUrl(['kelas' => $record->id]))
                    ->openUrlInNewTab(),
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
            SiswaRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKelas::route('/'),
            'create' => Pages\CreateKelas::route('/create'),
            'edit' => Pages\EditKelas::route('/{record}/edit'),
        ];
    }
}
