<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CabangResource\Pages;
use App\Filament\Resources\CabangResource\RelationManagers;
use App\Models\Cabang;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\TimePicker;
use App\Filament\Forms\Components\LocationPicker;
use Illuminate\Database\Eloquent\Model;

class CabangResource extends Resource
{
    protected static ?string $model = Cabang::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('nama_cabang')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),
                Forms\Components\Textarea::make('alamat')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('pic_nama')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('pic_telepon')
                    ->tel()
                    ->placeholder('628xxx.')
                    ->required(),
                TimePicker::make('jam_buka')
                    ->seconds(false)
                    ->required(),

                TimePicker::make('jam_tutup')
                    ->seconds(false)
                    ->required(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
                LocationPicker::make('koordinat')
                    ->label('Titik Lokasi Cabang')
                    ->afterStateHydrated(function (LocationPicker $component, ?Model $record) {
                        $component->state([
                            'lat' => $record?->latitude,
                            'lng' => $record?->longitude,
                        ]);
                    })
                    ->columnSpanFull(),

                Forms\Components\TextInput::make('radius_presensi_meter')
                    ->label('Radius Presensi')
                    ->numeric()
                    ->minValue(10)
                    ->default(100)
                    ->required()
                    ->suffix('meter')
                    ->helperText('Jarak maksimum dari titik lokasi cabang agar guru bisa presensi.'),

                Forms\Components\TextInput::make('toleransi_keterlambatan_menit')
                    ->label('Toleransi Keterlambatan')
                    ->numeric()
                    ->minValue(0)
                    ->default(0)
                    ->required()
                    ->suffix('menit'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama_cabang')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pic_nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pic_telepon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('jam_buka'),
                Tables\Columns\TextColumn::make('jam_tutup'),
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
            'index' => Pages\ListCabangs::route('/'),
            'create' => Pages\CreateCabang::route('/create'),
            'edit' => Pages\EditCabang::route('/{record}/edit'),
        ];
    }
}
