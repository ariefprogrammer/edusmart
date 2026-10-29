<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JadwalPenggantiResource\Pages;
use App\Filament\Resources\JadwalPenggantiResource\RelationManagers;
use App\Models\JadwalPengganti;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Concerns\ScopedToCabang;
use App\Models\Jadwal;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Model;
use App\Models\Guru;

class JadwalPenggantiResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = JadwalPengganti::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';
    protected static ?string $navigationLabel = 'Jadwal Pengganti';
    protected static ?string $pluralModelLabel = 'Jadwal Pengganti';
    protected static ?string $modelLabel = 'Jadwal Pengganti';
    protected static ?string $navigationGroup = 'Penugasan & Presensi';
    protected static ?int $navigationSort = 8;

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

            Select::make('kelas_id')
                ->label('Kelas / Program')
                ->relationship(
                    'kelas',
                    'nama_kelas',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query
                        ->where('cabang_id', $get('cabang_id') ?? auth()->user()->cabang->first()?->id),
                )
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function (Set $set) {
                    $set('jadwal_id', null);
                    $set('siswa', []);
                }),

            Select::make('jadwal_id')
                ->label('Jadwal Reguler yang Digantikan')
                ->options(fn (Get $get) => blank($get('kelas_id')) ? [] : Jadwal::query()
                    ->with('guru')
                    ->where('kelas_id', $get('kelas_id'))
                    ->orderBy('hari')
                    ->orderBy('jam_mulai')
                    ->get()
                    ->mapWithKeys(fn (Jadwal $j) => [
                        $j->id => $j->hari . ' ' . substr($j->jam_mulai, 0, 5) . '–' . substr($j->jam_selesai, 0, 5)
                            . ' · ' . ($j->guru?->nama ?? '-') . ($j->is_active ? '' : ' (nonaktif)'),
                    ])
                    ->all())
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    $jadwal = $state ? Jadwal::find($state) : null;

                    if ($jadwal) {
                        $set('guru_id', $jadwal->guru_id);
                        $set('jam_mulai', $jadwal->jam_mulai);
                        $set('jam_selesai', $jadwal->jam_selesai);
                    }
                }),

            DatePicker::make('tanggal_seharusnya')
                ->label('Tanggal Seharusnya')
                ->required()
                ->rule(fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                    $jadwal = filled($value) && filled($get('jadwal_id')) ? Jadwal::find($get('jadwal_id')) : null;

                    if ($jadwal && JadwalPengganti::namaHari($value) !== $jadwal->hari) {
                        $fail('Tanggal ini jatuh pada hari ' . JadwalPengganti::namaHari($value)
                            . ', sedangkan jadwal reguler yang dipilih adalah hari ' . $jadwal->hari . '.');
                    }
                }),

            DatePicker::make('tanggal_pengganti')
                ->label('Tanggal Pengganti')
                ->required()
                ->after('tanggal_seharusnya')
                ->rule(fn (?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                    if (blank($value)) {
                        return;
                    }

                    // Saat edit, tanggal yang tidak diubah tidak dicek ulang (jadwal "terlewat" tetap bisa diedit isinya)
                    if ($record?->exists && $record->tanggal_pengganti?->isSameDay(Carbon::parse($value))) {
                        return;
                    }

                    if (Carbon::parse($value)->lt(today())) {
                        $fail('Tanggal pengganti tidak boleh di masa lalu.');
                    }
                }),

            Select::make('guru_id')
                ->label('Guru')
                ->relationship(
                    'guru',
                    'nama',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query
                        ->where('cabang_id', $get('cabang_id') ?? auth()->user()->cabang->first()?->id),
                )
                ->searchable()
                ->preload()
                ->required()
                ->rule(fn (Get $get, ?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                    $tanggal = $get('tanggal_pengganti');
                    $jamMulai = $get('jam_mulai');
                    $jamSelesai = $get('jam_selesai');
                    $kelasId = $get('kelas_id');
                    $cabangId = $get('cabang_id') ?? $record?->cabang_id ?? auth()->user()->cabang->first()?->id;

                    if (blank($value) || blank($kelasId) || blank($cabangId) || blank($tanggal) || blank($jamMulai) || blank($jamSelesai)) {
                        return;
                    }

                    if (Carbon::parse($jamSelesai)->lte(Carbon::parse($jamMulai))) {
                        return;
                    }

                    $pesan = JadwalPengganti::pesanBentrok(
                        $cabangId, $value, $kelasId, $tanggal, $jamMulai, $jamSelesai, $record?->getKey(),
                    );

                    if ($pesan) {
                        $fail($pesan);
                    }
                }),

            TimePicker::make('jam_mulai')
                ->seconds(false)
                ->required(),

            TimePicker::make('jam_selesai')
                ->seconds(false)
                ->required()
                ->after('jam_mulai'),

            Textarea::make('alasan')
                ->required()
                ->rows(3)
                ->columnSpanFull(),

            Select::make('siswa')
                ->label('Siswa yang Mengikuti')
                ->relationship(
                    'siswa',
                    'nama',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query
                        ->where('siswa.is_active', true)
                        ->whereHas('kelasList', fn ($q) => $q
                            ->where('kelas.id', $get('kelas_id'))
                            ->where('kelas_siswa.is_active', true)),
                )
                ->multiple()
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull()
                ->rule(fn (Get $get, ?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                    $kelasId = $get('kelas_id');
                    $tanggal = $get('tanggal_seharusnya');

                    if (blank($value) || blank($kelasId) || blank($tanggal)) {
                        return;
                    }

                    $pesan = JadwalPengganti::pesanSiswaBermasalah(
                        $kelasId,
                        $tanggal,
                        (array) $value,
                        $record?->getKey(),
                        $record?->exists ? $record->siswa()->pluck('siswa.id')->all() : [],
                    );

                    if ($pesan) {
                        $fail($pesan);
                    }
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('siswa'))
            ->defaultSort('tanggal_pengganti', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('cabang.nama_cabang')->label('Cabang')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('kelas.nama_kelas')->label('Kelas / Program')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('guru.nama')->label('Guru')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('tanggal_seharusnya')->label('Tgl Seharusnya')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('tanggal_pengganti')->label('Tgl Pengganti')->date('d M Y')->sortable(),
                Tables\Columns\TextColumn::make('jam')
                    ->label('Jam')
                    ->getStateUsing(fn (JadwalPengganti $record) => substr($record->jam_mulai, 0, 5) . '–' . substr($record->jam_selesai, 0, 5)),
                Tables\Columns\TextColumn::make('siswa_count')
                    ->label('Jumlah Siswa')
                    ->alignment(Alignment::Center),
                Tables\Columns\TextColumn::make('status_tampil')
                    ->label('Status')
                    ->badge()
                    ->getStateUsing(fn (JadwalPengganti $record) => $record->statusTampil())
                    ->color(fn (string $state) => match ($state) {
                        'selesai' => 'success',
                        'dibatalkan' => 'gray',
                        'terlewat' => 'danger',
                        default => 'warning',
                    }),
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
                Tables\Actions\Action::make('batalkan')
                    ->label('Batalkan')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Batalkan jadwal pengganti?')
                    ->modalDescription('Jadwal tidak akan muncul lagi di halaman guru dan tidak bisa diedit. Data tetap tersimpan untuk laporan.')
                    ->visible(fn (JadwalPengganti $record) => static::canEdit($record))
                    ->action(fn (JadwalPengganti $record) => $record->update([
                        'status' => JadwalPengganti::STATUS_DIBATALKAN,
                    ])),
            ])
            ->bulkActions([
                //
            ]);
    }

    public static function canEdit(Model $record): bool
    {
        return $record->status === JadwalPengganti::STATUS_TERJADWAL
            && parent::canEdit($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    /** Mengembalikan jadwal reguler jika guru yang dipilih berbeda dari gurunya; selain itu null. */
    public static function jadwalDenganGuruBerbeda(?array $data): ?Jadwal
    {
        $jadwalId = $data['jadwal_id'] ?? null;
        $guruId = $data['guru_id'] ?? null;

        if (blank($jadwalId) || blank($guruId)) {
            return null;
        }

        $jadwal = Jadwal::with('guru')->find($jadwalId);

        return $jadwal && (int) $jadwal->guru_id !== (int) $guruId ? $jadwal : null;
    }

    public static function pesanGuruBerbeda(?array $data): string
    {
        $jadwal = static::jadwalDenganGuruBerbeda($data);
        $guruBaru = Guru::find($data['guru_id'] ?? null)?->nama ?? '-';

        return 'Jadwal reguler ini diampu oleh ' . ($jadwal?->guru?->nama ?? '-')
            . ', tetapi guru pengganti yang dipilih adalah ' . $guruBaru . '. Lanjutkan?';
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
            'index' => Pages\ListJadwalPenggantis::route('/'),
            'create' => Pages\CreateJadwalPengganti::route('/create'),
            'edit' => Pages\EditJadwalPengganti::route('/{record}/edit'),
        ];
    }
}
