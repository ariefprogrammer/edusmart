<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PresensiKaryawanResource\Pages;
use App\Filament\Resources\PresensiKaryawanResource\RelationManagers;
use App\Models\PresensiKaryawan;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Concerns\ScopedToCabang;

class PresensiKaryawanResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = PresensiKaryawan::class;

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('cabang_id')
                    ->relationship('cabang', 'id')
                    ->required(),
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
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
                Forms\Components\TextInput::make('check_in_foto')
                    ->maxLength(255)
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
                Forms\Components\TextInput::make('check_out_foto')
                    ->maxLength(255)
                    ->default(null),
                Forms\Components\TextInput::make('status_masuk'),
                Forms\Components\Textarea::make('keterangan')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')->label('Karyawan')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tanggal')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('check_in')->time('H:i'),
                Tables\Columns\BadgeColumn::make('status_masuk')
                    ->colors(['success' => 'hadir', 'danger' => 'terlambat'])
                    ->placeholder('-'),
                Tables\Columns\BadgeColumn::make('status_keluar')
                    ->colors([
                        'success' => 'pulang',
                        'danger' => 'bolos',
                        'warning' => 'tidak_checkout',
                    ])
                ->placeholder('-'),
                Tables\Columns\TextColumn::make('check_out')->time('H:i'),
                Tables\Columns\ImageColumn::make('check_in_foto')->label('Foto Masuk')->disk('public'),
                Tables\Columns\ImageColumn::make('check_out_foto')->label('Foto Keluar')->disk('public'),
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
            'index' => Pages\ListPresensiKaryawans::route('/'),
            'create' => Pages\CreatePresensiKaryawan::route('/create'),
            'edit' => Pages\EditPresensiKaryawan::route('/{record}/edit'),
        ];
    }
}
