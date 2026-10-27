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
use App\Models\Kelas;
use Closure;
use Illuminate\Database\Eloquent\Model;

class SiswaResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = Siswa::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Manajemen Siswa';
    protected static ?int $navigationSort = 12;

    protected static function programAktifSiswa(?Model $record): array
    {
        if (! $record?->exists) {
            return [];
        }

        return $record->kelasList()
            ->wherePivot('is_active', true)
            ->pluck('kelas.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    public static function form(Form $form): Form
    {
        $idPenuh = null;
        $ambilIdPenuh = function () use (&$idPenuh): array {
            return $idPenuh ??= Kelas::idPenuh();
        };

        $idAktifSiswa = null;
        $ambilIdAktifSiswa = function (?Model $record) use (&$idAktifSiswa): array {
            return $idAktifSiswa ??= self::programAktifSiswa($record);
        };
        
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
                // Nilai awal di form edit: hanya program yang pivot-nya masih aktif
                ->loadStateFromRelationshipsUsing(function (Select $component, ?Model $record) {
                    $component->state(self::programAktifSiswa($record));
                })
                // Penyimpanan manual: riwayat lama tidak ditimpa, program yang dilepas dinonaktifkan
                ->saveRelationshipsUsing(function (Model $record, $state) {
                    $dipilih = collect($state ?? [])->map(fn ($id) => (int) $id);
                    $relasi  = $record->kelasList();

                    // Semua program yang pernah diikuti (aktif + nonaktif)
                    $semuaPernah = $relasi->pluck('kelas.id')->map(fn ($id) => (int) $id);
                    // Program yang saat ini aktif
                    $aktif = collect(self::programAktifSiswa($record));

                    // 1. Program yang belum pernah diikuti -> catat bergabung hari ini
                    foreach ($dipilih->diff($semuaPernah) as $id) {
                        $relasi->attach($id, ['tanggal_gabung' => now(), 'is_active' => true]);
                    }

                    // 2. Program lama (sebelumnya nonaktif) yang dipilih lagi -> aktifkan kembali
                    foreach ($dipilih->intersect($semuaPernah)->diff($aktif) as $id) {
                        $relasi->updateExistingPivot($id, ['is_active' => true]);
                    }

                    // 3. Program yang dilepas -> nonaktifkan, riwayat tidak dihapus
                    foreach ($aktif->diff($dipilih) as $id) {
                        $relasi->updateExistingPivot($id, ['is_active' => false]);
                    }
                })
                // Tampilan: program penuh tidak bisa dipilih, kecuali siswa ini sudah aktif di sana
                ->disableOptionWhen(function (string $value, ?Model $record) use ($ambilIdPenuh, $ambilIdAktifSiswa) {
                    $id = (int) $value;

                    return in_array($id, $ambilIdPenuh(), true)
                        && ! in_array($id, $ambilIdAktifSiswa($record), true);
                })
                // Server: tolak penyimpanan kalau ada program penuh yang akan ditambahkan
                ->rule(fn (?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                    $dipilih = collect($value ?? [])->map(fn ($id) => (int) $id);
                    $akanDitambah = $dipilih
                        ->diff(self::programAktifSiswa($record))
                        ->values()
                        ->all();

                    if ($akanDitambah === []) {
                        return;
                    }

                    $namaPenuh = Kelas::whereIn('id', Kelas::idPenuh($akanDitambah))->pluck('nama_kelas');

                    if ($namaPenuh->isNotEmpty()) {
                        $fail('Kuota penuh untuk program: ' . $namaPenuh->join(', ') . '. Hapus dari pilihan atau naikkan kuota maksimalnya.');
                    }
                }),

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
                Tables\Columns\TextColumn::make('kelasAktif.nama_kelas')
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