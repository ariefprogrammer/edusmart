<?php

namespace App\Filament\Resources;

use App\Models\Concerns\ScopedToCabang;
use App\Filament\Resources\SiswaResource\Pages;
use App\Models\Siswa;
use Filament\Forms;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Eloquent\Builder;

class SiswaResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = Siswa::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('cabang_id')
                ->relationship('cabang', 'nama_cabang')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->visible(fn () => auth()->user()->hasRole('super_admin'))
                ->default(fn () => auth()->user()->cabang->first()?->id)
                ->dehydrated(),

            Select::make('kelasList')
                ->label('Program yang Diikuti')
                ->relationship(
                    'kelasList',
                    'nama_kelas',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query
                        ->where('cabang_id', $get('cabang_id') ?? auth()->user()->cabang->first()?->id),
                )
                ->multiple()
                ->searchable()
                ->preload()
                ->required()
                ->pivotData(['tanggal_gabung' => now(), 'is_active' => true]),

            TextInput::make('nama')
                ->label('Nama Siswa')
                ->required()
                ->maxLength(255),

            DatePicker::make('tanggal_lahir')
                ->native(false),

            Textarea::make('alamat'),

            TextInput::make('telepon')
                ->tel()
                ->helperText('Format apa saja, otomatis diseragamkan ke 628xxx.'),

            Select::make('status')
                ->options([
                    'aktif' => 'Aktif',
                    'cuti' => 'Cuti',
                    'keluar' => 'Keluar',
                ])
                ->default('aktif')
                ->required(),

            DatePicker::make('tanggal_daftar')
                ->native(false)
                ->default(now())
                ->required(),

            Toggle::make('is_active')
                ->default(true)
                ->required(),

            Fieldset::make('Data Wali Murid')
                ->relationship('waliMurid')
                ->schema([
                    TextInput::make('nama')
                        ->label('Nama Wali Murid')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('telepon')
                        ->label('Telepon Wali Murid')
                        ->tel()
                        ->helperText('Format apa saja, otomatis diseragamkan ke 628xxx.'),

                    TextInput::make('email')
                        ->label('Email Wali Murid')
                        ->email()
                        ->unique(table: 'wali_murid', ignoreRecord: true)
                        ->maxLength(255),

                    TextInput::make('password')
                        ->label('Password Wali Murid')
                        ->password()
                        ->revealable()
                        ->dehydrateStateUsing(fn (?string $state) => Hash::make($state))
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->required(fn (string $context) => $context === 'create')
                        ->helperText(fn (string $context) => $context === 'edit'
                            ? 'Kosongkan jika tidak ingin mengubah password.'
                            : 'Dipakai untuk login portal wali murid (menyusul).')
                        ->maxLength(255),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nama')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kelasList.nama_kelas')
                    ->label('Program')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('waliMurid.nama')->label('Wali Murid')->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'aktif',
                        'warning' => 'cuti',
                        'danger' => 'keluar',
                    ]),
                Tables\Columns\IconColumn::make('is_active')->boolean(),
            ])
            ->filters([
                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSiswas::route('/'),
            'create' => Pages\CreateSiswa::route('/create'),
            'edit' => Pages\EditSiswa::route('/{record}/edit'),
        ];
    }
}