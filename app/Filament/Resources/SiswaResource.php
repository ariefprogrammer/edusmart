<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SiswaResource\Pages;
use App\Models\Concerns\ScopedToCabang;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\PenjadwalanSiswa;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Fieldset;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use App\Exports\SiswaFormulirExport;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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

    protected static function idInt(mixed $nilai): array
    {
        return collect($nilai ?? [])->map(fn ($id) => (int) $id)->values()->all();
    }

    protected static function siswaTidakAktifDiForm(Get $get): bool
    {
        return ! (bool) $get('is_active')
            || in_array($get('status'), Siswa::STATUS_NONAKTIF, true);
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
            Tabs::make('Form Siswa')
                ->columnSpanFull()
                ->persistTabInQueryString()
                ->tabs([
                    // =================================================================
                    // TAB 1: BIODATA SISWA
                    // =================================================================
                    Tab::make('Biodata Siswa')
                        ->icon('heroicon-o-identification')
                        ->schema([
                            Section::make('Identitas Pribadi')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('nama')
                                        ->label('Nama Lengkap Siswa')
                                        ->required()
                                        ->maxLength(255),

                                    TextInput::make('nisn')
                                        ->label('NISN')
                                        ->maxLength(20),

                                    Select::make('jenis_kelamin')
                                        ->label('Jenis Kelamin')
                                        ->options([
                                            'L' => 'Laki-laki',
                                            'P' => 'Perempuan',
                                        ]),

                                    TextInput::make('anak_ke')
                                        ->label('Anak Ke-')
                                        ->numeric()
                                        ->minValue(1),

                                    TextInput::make('tempat_lahir')
                                        ->label('Tempat Lahir')
                                        ->maxLength(100),

                                    DatePicker::make('tanggal_lahir')
                                        ->label('Tanggal Lahir')
                                        ->native(false),

                                    Select::make('agama')
                                        ->label('Agama')
                                        ->options([
                                            'Islam' => 'Islam',
                                            'Kristen' => 'Kristen',
                                            'Katolik' => 'Katolik',
                                            'Hindu' => 'Hindu',
                                            'Buddha' => 'Buddha',
                                            'Konghucu' => 'Konghucu',
                                            'Lainnya' => 'Lainnya',
                                        ]),

                                    TextInput::make('kewarganegaraan')
                                        ->label('Kewarganegaraan')
                                        ->default('Indonesia')
                                        ->maxLength(50),

                                    TextInput::make('kelas')
                                        ->label('Kelas (Sekolah Asal)')
                                        ->placeholder('Contoh: 7 SMP / 10 SMA')
                                        ->maxLength(50),

                                    TextInput::make('asal_sekolah')
                                        ->label('Asal Sekolah')
                                        ->maxLength(150),
                                ]),

                            Section::make('Alamat & Kontak Siswa')
                                ->columns(2)
                                ->schema([
                                    Textarea::make('alamat')
                                        ->label('Alamat Jalan / Dusun')
                                        ->columnSpanFull(),

                                    Grid::make(4)
                                        ->schema([
                                            TextInput::make('rt')
                                                ->label('RT')
                                                ->maxLength(5),

                                            TextInput::make('rw')
                                                ->label('RW')
                                                ->maxLength(5),

                                            TextInput::make('kelurahan')
                                                ->label('Kelurahan / Desa')
                                                ->maxLength(100),

                                            TextInput::make('kode_pos')
                                                ->label('Kode Pos')
                                                ->maxLength(10),
                                        ]),

                                    TextInput::make('telepon')
                                        ->label('No. Seluler / WhatsApp')
                                        ->tel()
                                        ->helperText('Format apa saja, otomatis diseragamkan ke 628xxx.'),

                                    TextInput::make('telepon_rumah')
                                        ->label('No. Telepon Rumah')
                                        ->tel(),

                                    TextInput::make('media_sosial')
                                        ->label('Media Sosial Siswa')
                                        ->placeholder('Contoh: IG @username')
                                        ->maxLength(255),

                                    TextInput::make('alat_transportasi')
                                        ->label('Alat Transportasi')
                                        ->maxLength(100),

                                    TextInput::make('sumber_informasi')
                                        ->label('Sumber Informasi')
                                        ->placeholder('Contoh: Brosur, Instagram, Teman')
                                        ->maxLength(100),
                                ]),
                        ]),

                    // =================================================================
                    // TAB 2: AKADEMIK, PROGRAM & STATUS
                    // =================================================================
                    Tab::make('Program & Status')
                        ->icon('heroicon-o-academic-cap')
                        ->schema([
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
                                ->live()
                                ->afterStateUpdated(function (Get $get, Set $set, ?Model $record) {
                                    $kelasIds = self::idInt($get('kelasList'));

                                    $dipilih = collect(self::idInt($get('jadwal_ids')))
                                        ->intersect(PenjadwalanSiswa::idJadwalDi($kelasIds));

                                    if ($record?->exists) {
                                        $baru = array_diff($kelasIds, self::programAktifSiswa($record));
                                        $dipilih = $dipilih->merge(PenjadwalanSiswa::idTersimpan($record->getKey(), $baru));
                                    }

                                    $set('jadwal_ids', $dipilih->unique()->values()->all());
                                })
                                ->required(fn (Get $get, ?Model $record): bool => ! $record?->exists || ! self::siswaTidakAktifDiForm($get))
                                ->helperText(fn (Get $get, ?Model $record) => $record?->exists && self::siswaTidakAktifDiForm($get)
                                    ? 'Siswa tidak aktif/cuti/keluar: program yang masih aktif akan dinonaktifkan otomatis saat disimpan.'
                                    : null)
                                ->loadStateFromRelationshipsUsing(function (Select $component, ?Model $record) {
                                    $component->state(self::programAktifSiswa($record));
                                })
                                ->saveRelationshipsUsing(function (Model $record, $state) {
                                    $dipilih = collect($state ?? [])->map(fn ($id) => (int) $id);
                                    $relasi  = $record->kelasList();

                                    $semuaPernah = $relasi->pluck('kelas.id')->map(fn ($id) => (int) $id);
                                    $aktif = collect(self::programAktifSiswa($record));

                                    $bolehAktifkan = $record->wasRecentlyCreated || ! $record->sedangTidakAktif();

                                    if ($bolehAktifkan) {
                                        foreach ($dipilih->diff($semuaPernah) as $id) {
                                            $relasi->attach($id, ['tanggal_gabung' => now(), 'is_active' => true]);
                                        }

                                        foreach ($dipilih->intersect($semuaPernah)->diff($aktif) as $id) {
                                            $relasi->updateExistingPivot($id, ['is_active' => true]);
                                        }
                                    }

                                    foreach ($aktif->diff($dipilih) as $id) {
                                        $relasi->updateExistingPivot($id, ['is_active' => false]);
                                    }
                                })
                                ->disableOptionWhen(function (string $value, ?Model $record) use ($ambilIdPenuh, $ambilIdAktifSiswa) {
                                    $id = (int) $value;

                                    return in_array($id, $ambilIdPenuh(), true)
                                        && ! in_array($id, $ambilIdAktifSiswa($record), true);
                                })
                                ->rule(fn (?Model $record, Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($record, $get) {
                                    $tidakAktifDiForm = $record?->exists && self::siswaTidakAktifDiForm($get);

                                    $dipilih = collect($value ?? [])->map(fn ($id) => (int) $id);
                                    $akanDitambah = $dipilih
                                        ->diff(self::programAktifSiswa($record))
                                        ->values()
                                        ->all();

                                    if ($akanDitambah === []) {
                                        return;
                                    }

                                    if ($tidakAktifDiForm) {
                                        $fail('Siswa sedang tidak aktif, cuti, atau keluar. Aktifkan siswa (toggle aktif dan status) sebelum menambah atau mengaktifkan program.');

                                        return;
                                    }

                                    $namaPenuh = Kelas::whereIn('id', Kelas::idPenuh($akanDitambah))->pluck('nama_kelas');

                                    if ($namaPenuh->isNotEmpty()) {
                                        $fail('Kuota penuh untuk program: ' . $namaPenuh->join(', ') . '. Hapus dari pilihan atau naikkan kuota maksimalnya.');
                                    }
                                }),

                            Select::make('jadwal_ids')
                                ->label('Pilih jam')
                                ->multiple()
                                ->options(fn (Get $get) => PenjadwalanSiswa::opsi(self::idInt($get('kelasList'))))
                                ->visible(fn (Get $get) => filled($get('kelasList')))
                                ->dehydrated(false)
                                ->helperText('Pilih satu atau lebih jam yang diikuti siswa. Jam yang bentrok tidak diizinkan.')
                                ->required(function (Get $get, ?Model $record): bool {
                                    $wajib = PenjadwalanSiswa::kelasWajibPilih(
                                        $record?->getKey(),
                                        self::idInt($get('kelasList')),
                                        self::programAktifSiswa($record),
                                    );

                                    return PenjadwalanSiswa::namaKelasTanpaPilihan($wajib, [])->isNotEmpty();
                                })
                                ->loadStateFromRelationshipsUsing(function (Select $component, ?Model $record) {
                                    $component->state($record?->exists
                                        ? PenjadwalanSiswa::idTersimpan($record->getKey(), self::programAktifSiswa($record))
                                        : []);
                                })
                                ->saveRelationshipsUsing(function (Model $record, $state) {
                                    PenjadwalanSiswa::sinkron($record, self::idInt($state));
                                })
                                ->rule(fn (Get $get, ?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                                    $kelasIds = self::idInt($get('kelasList'));
                                    $jadwalIds = self::idInt($value);

                                    $wajib = PenjadwalanSiswa::kelasWajibPilih(
                                        $record?->getKey(),
                                        $kelasIds,
                                        self::programAktifSiswa($record),
                                    );

                                    $tanpa = PenjadwalanSiswa::namaKelasTanpaPilihan($wajib, $jadwalIds);

                                    if ($tanpa->isNotEmpty()) {
                                        $fail('Pilih minimal satu jam untuk program: ' . $tanpa->join(', ') . '.');

                                        return;
                                    }

                                    if ($pesan = PenjadwalanSiswa::pesanBentrok($jadwalIds)) {
                                        $fail($pesan);
                                    }
                                }),

                            Grid::make(2)
                                ->schema([
                                    DatePicker::make('tanggal_daftar')
                                        ->label('Tanggal Daftar')
                                        ->native(false)
                                        ->default(now())
                                        ->required(),

                                    DatePicker::make('tanggal_masuk')
                                        ->label('Tanggal Masuk')
                                        ->native(false),

                                    Select::make('status')
                                        ->options([
                                            'aktif' => 'Aktif',
                                            'cuti' => 'Cuti',
                                            'keluar' => 'Keluar',
                                        ])
                                        ->default('aktif')
                                        ->required()
                                        ->live(),

                                    Toggle::make('is_active')
                                        ->label('Akun / Siswa Aktif')
                                        ->default(true)
                                        ->required()
                                        ->live(),
                                ]),
                        ]),

                    // =================================================================
                    // TAB 3: DATA KELUARGA (AYAH, IBU, WALI) -> Relasi `keluarga`
                    // =================================================================
                    Tab::make('Data Orang Tua & Wali')
                        ->icon('heroicon-o-users')
                        ->schema([
                            Fieldset::make('Biodata Keluarga')
                                ->relationship('keluarga')
                                ->schema([
                                    Section::make('Data Ayah')
                                        ->columns(2)
                                        ->collapsible()
                                        ->schema([
                                            TextInput::make('nama_ayah')->label('Nama Ayah')->maxLength(255),
                                            TextInput::make('nik_ayah')->label('NIK Ayah')->maxLength(20),
                                            TextInput::make('tempat_lahir_ayah')->label('Tempat Lahir Ayah')->maxLength(100),
                                            DatePicker::make('tanggal_lahir_ayah')->label('Tanggal Lahir Ayah')->native(false),
                                            TextInput::make('pendidikan_ayah')->label('Pendidikan Terakhir')->maxLength(50),
                                            TextInput::make('pekerjaan_ayah')->label('Pekerjaan Ayah')->maxLength(100),
                                            TextInput::make('penghasilan_ayah')->label('Penghasilan Ayah')->maxLength(100),
                                            TextInput::make('media_sosial_ayah')->label('Media Sosial Ayah')->maxLength(255),
                                        ]),

                                    Section::make('Data Ibu')
                                        ->columns(2)
                                        ->collapsible()
                                        ->schema([
                                            TextInput::make('nama_ibu')->label('Nama Ibu')->maxLength(255),
                                            TextInput::make('nik_ibu')->label('NIK Ibu')->maxLength(20),
                                            TextInput::make('tempat_lahir_ibu')->label('Tempat Lahir Ibu')->maxLength(100),
                                            DatePicker::make('tanggal_lahir_ibu')->label('Tanggal Lahir Ibu')->native(false),
                                            TextInput::make('pendidikan_ibu')->label('Pendidikan Terakhir')->maxLength(50),
                                            TextInput::make('pekerjaan_ibu')->label('Pekerjaan Ibu')->maxLength(100),
                                            TextInput::make('penghasilan_ibu')->label('Penghasilan Ibu')->maxLength(100),
                                            TextInput::make('media_sosial_ibu')->label('Media Sosial Ibu')->maxLength(255),
                                        ]),

                                    Section::make('Data Wali (Jika Berbeda dari Orang Tua)')
                                        ->columns(2)
                                        ->collapsible()
                                        ->collapsed()
                                        ->schema([
                                            TextInput::make('nama_wali')->label('Nama Wali')->maxLength(255),
                                            TextInput::make('nik_wali')->label('NIK Wali')->maxLength(20),
                                            TextInput::make('tahun_lahir_wali')
                                                ->label('Tahun Lahir Wali')
                                                ->numeric()
                                                ->minValue(1900)
                                                ->maxValue((int) date('Y')),
                                            TextInput::make('pendidikan_wali')->label('Pendidikan Terakhir')->maxLength(50),
                                            TextInput::make('pekerjaan_wali')->label('Pekerjaan Wali')->maxLength(100),
                                            TextInput::make('penghasilan_wali')->label('Penghasilan Wali')->maxLength(100),
                                            TextInput::make('telepon_wali')->label('No. Telepon Wali')->tel(),
                                            TextInput::make('seluler_wali')->label('No. Seluler / WA Wali')->tel(),
                                            Textarea::make('alamat_wali')->label('Alamat Wali')->columnSpanFull(),
                                        ]),
                                ]),
                        ]),

                    // =================================================================
                    // TAB 4: AKUN WALI MURID -> Relasi `waliMurid`
                    // =================================================================
                    Tab::make('Akun Portal Wali')
                        ->icon('heroicon-o-key')
                        ->schema([
                            Fieldset::make('Akun Login Wali Murid')
                                ->relationship('waliMurid')
                                ->columns(2)
                                ->schema([
                                    TextInput::make('nama')
                                        ->label('Nama Pemilik Akun Wali')
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
                        ]),
                ]),
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
                Tables\Columns\TextColumn::make('nisn')
                    ->label('NISN')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('nama')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jenis_kelamin')
                    ->label('L/P')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'L' => 'info',
                        'P' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('asal_sekolah')
                    ->label('Asal Sekolah')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('kelasAktif.nama_kelas')
                    ->label('Program')
                    ->badge()
                    ->separator(','),
                Tables\Columns\TextColumn::make('waliMurid.nama')
                    ->label('Wali Murid')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'aktif' => 'success',
                        'cuti' => 'warning',
                        'keluar' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->filters([
                
                Tables\Filters\TrashedFilter::make(),
                Tables\Filters\SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload()
                    ->visible(fn () => auth()->user()->hasRole('super_admin')),
                Tables\Filters\SelectFilter::make('kelasAktif')
                    ->label('Kelas / Program')
                    ->relationship('kelasAktif', 'nama_kelas')
                    ->searchable()
                    ->preload()
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status Aktif')
                    ->trueLabel('Aktif')
                    ->falseLabel('Non-aktif')
                    ->placeholder('Semua'),
                Tables\Filters\SelectFilter::make('jenis_kelamin')
                    ->label('Jenis Kelamin')
                    ->options([
                        'L' => 'Laki-laki',
                        'P' => 'Perempuan',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('exportExcel')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->deselectRecordsAfterCompletion()
                    ->action(fn (EloquentCollection $records) => SiswaFormulirExport::download($records)),
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