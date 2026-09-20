<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GuruResource\Pages;
use App\Filament\Resources\GuruResource\RelationManagers;
use App\Models\Guru;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Components\Select;
use App\Models\Concerns\ScopedToCabang;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Get;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;
use Filament\Tables\Filters\SelectFilter;

class GuruResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = Guru::class;

    protected static ?string $navigationIcon = 'heroicon-o-academic-cap';

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

                Forms\Components\TextInput::make('nama')
                    ->required()
                    ->columnSpanFull()
                    ->maxLength(255),

                Forms\Components\TextInput::make('telepon')
                    ->tel()
                    ->columnSpanFull()
                    ->helperText('Bisa diisi format apa saja (08xxx, +62xxx, 62xxx) — akan otomatis diseragamkan ke 628xxx.'),

                // Pendidikan dikembalikan ke atas dan dibuat full width (lebar penuh)
                Forms\Components\TextInput::make('pendidikan')
                    ->columnSpanFull()
                    ->maxLength(255),

                // Membungkus email dan password dalam Grid 2 kolom agar bersebelahan
                Forms\Components\Grid::make(2)
                    ->schema([
                        // Email diletakkan di sebelah kiri
                        Forms\Components\TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Email ini juga dipakai sebagai email login guru.')
                            ->unique(
                                table: 'users',
                                column: 'email',
                                modifyRuleUsing: fn (Unique $rule, ?Model $record) => $rule->ignore($record?->user?->id),
                            ),

                        // Password diletakkan di sebelah kanan
                        Forms\Components\Group::make()
                            ->relationship('user')
                            ->schema([
                                Hidden::make('name')
                                    ->dehydrateStateUsing(fn (Get $get): ?string => $get('../nama'))
                                    ->dehydrated(),

                                Hidden::make('email')
                                    ->dehydrateStateUsing(fn (Get $get): ?string => $get('../email'))
                                    ->dehydrated(),

                                Forms\Components\TextInput::make('password')
                                    ->password()
                                    ->revealable()
                                    ->dehydrateStateUsing(fn (?string $state) => Hash::make($state))
                                    ->dehydrated(fn (?string $state) => filled($state))
                                    ->required(fn (string $context) => $context === 'create')
                                    ->helperText(fn (string $context) => $context === 'edit'
                                        ? 'Kosongkan jika tidak ingin mengubah password.'
                                        : 'Dipakai untuk login ke panel & presensi.')
                                    ->maxLength(255),
                            ]),
                    ]),

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required()
                    ->columnSpanFull(), 
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
                Tables\Columns\TextColumn::make('nama')
                    ->searchable(),
                Tables\Columns\TextColumn::make('telepon')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pendidikan')
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
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListGurus::route('/'),
            'create' => Pages\CreateGuru::route('/create'),
            'edit' => Pages\EditGuru::route('/{record}/edit'),
        ];
    }
}
