<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PresensiJadwalResource\Pages;
use App\Filament\Resources\PresensiJadwalResource\RelationManagers;
use App\Models\PresensiJadwal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Concerns\ScopedToCabang;

class PresensiJadwalResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = PresensiJadwal::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('cabang_id')
                    ->relationship('cabang', 'id')
                    ->required(),
                Forms\Components\Select::make('jadwal_id')
                    ->relationship('jadwal', 'id')
                    ->required(),
                Forms\Components\Select::make('guru_id')
                    ->relationship('guru', 'id')
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
                Forms\Components\TextInput::make('status_masuk'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('guru.nama')->label('Guru')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('jadwal.kelas.nama_kelas')->label('Program')->searchable(),
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('check_in')->time('H:i'),
                Tables\Columns\BadgeColumn::make('status_masuk')
                    ->colors(['success' => 'tepat_waktu', 'danger' => 'terlambat'])
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('check_out')->time('H:i'),
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
            'index' => Pages\ListPresensiJadwals::route('/'),
            'create' => Pages\CreatePresensiJadwal::route('/create'),
            'edit' => Pages\EditPresensiJadwal::route('/{record}/edit'),
        ];
    }
}
