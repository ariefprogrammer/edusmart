<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JadwalResource\Pages;
use App\Filament\Resources\JadwalResource\RelationManagers;
use App\Models\Jadwal;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Filament\Forms\Get;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\SelectFilter;
use Filament\Support\Enums\Alignment;
use App\Models\Concerns\ScopedToCabang;
use App\Models\Kelas;
use Closure;
use Illuminate\Database\Eloquent\Model;

class JadwalResource extends Resource
{
    use ScopedToCabang;
    protected static ?string $model = Jadwal::class;
    protected static ?string $navigationIcon = 'heroicon-o-table-cells';

    // Menu
    protected static ?string $navigationLabel = 'Jadwal Kelas / Program';

    // Label
    protected static ?string $pluralModelLabel = 'Jadwal Kelas / Program';
    protected static ?string $navigationGroup = 'Penugasan & Presensi';
    protected static ?int $navigationSort = 7;

    // Tombol create
    protected static ?string $modelLabel = 'Jadwal';

    public static function form(Form $form): Form
    {
        $belumMemenuhi = null;
        $ambilBelumMemenuhi = function () use (&$belumMemenuhi) {
            return $belumMemenuhi ??= Kelas::belumMemenuhiMinimal();
        };

        return $form
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
                    // Label menjelaskan kenapa sebuah kelas tidak bisa dipilih
                    ->getOptionLabelFromRecordUsing(function (Kelas $record) use ($ambilBelumMemenuhi) {
                        $belum = $ambilBelumMemenuhi()->get($record->id);

                        return $belum
                            ? "{$record->nama_kelas} (belum memenuhi minimal: {$belum->siswa_aktif_count}/{$belum->kuota_minimal} siswa)"
                            : $record->nama_kelas;
                    })
                    // Tampilan: kelas yang belum memenuhi minimal tidak bisa dipilih
                    ->disableOptionWhen(function (string $value, ?Model $record) use ($ambilBelumMemenuhi) {
                        // Jadwal yang sudah ada boleh tetap di kelasnya
                        if ($record?->exists && (int) $record->kelas_id === (int) $value) {
                            return false;
                        }

                        return $ambilBelumMemenuhi()->has((int) $value);
                    })
                    // Server: tolak penyimpanan kalau kelasnya belum memenuhi minimal
                    ->rule(fn (?Model $record): Closure => function (string $attribute, $value, Closure $fail) use ($record) {
                        if (blank($value)) {
                            return;
                        }

                        // Mengedit jadwal tanpa mengganti kelas tidak dicek ulang
                        if ($record?->exists && (int) $record->kelas_id === (int) $value) {
                            return;
                        }

                        $kelas = Kelas::belumMemenuhiMinimal([(int) $value])->first();

                        if ($kelas) {
                            $fail(
                                'Kelas ' . $kelas->nama_kelas . ' belum memenuhi kuota minimal ('
                                . $kelas->siswa_aktif_count . ' dari ' . $kelas->kuota_minimal
                                . ' siswa), sehingga belum dapat dijadwalkan.'
                            );
                        }
                    }),

                Select::make('guru_id')
                    ->relationship(
                        'guru',
                        'nama',
                        modifyQueryUsing: fn (Builder $query, Get $get) => $query
                            ->where('cabang_id', $get('cabang_id') ?? auth()->user()->cabang->first()?->id),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),

                Forms\Components\Fieldset::make('Pilihan Hari')
                ->schema([
                    Forms\Components\CheckboxList::make('hari_list')
                        ->label('')
                        ->options([
                            'Senin' => 'Senin', 'Selasa' => 'Selasa', 'Rabu' => 'Rabu',
                            'Kamis' => 'Kamis', 'Jumat' => 'Jumat', 'Sabtu' => 'Sabtu', 'Minggu' => 'Minggu',
                        ])
                        ->columns(7)
                        ->columnSpanFull()
                        ->required()
                        ->helperText('Pilih satu atau banyak hari sekaligus — jadwal dengan guru, jam mulai, dan jam selesai yang sama akan dibuat untuk tiap hari yang dicentang.')
                        ->visible(fn (string $operation) => $operation === 'create'),

                    Forms\Components\Select::make('hari')
                        ->label('')
                        ->options([
                            'Senin' => 'Senin', 'Selasa' => 'Selasa', 'Rabu' => 'Rabu',
                            'Kamis' => 'Kamis', 'Jumat' => 'Jumat', 'Sabtu' => 'Sabtu', 'Minggu' => 'Minggu',
                        ])
                        ->required()
                        ->columnSpanFull()
                        ->visible(fn (string $operation) => $operation === 'edit'),
                ])
                ->columnSpanFull(),

                Forms\Components\TimePicker::make('jam_mulai')
                    ->seconds(false)
                    ->required(),

                Forms\Components\TimePicker::make('jam_selesai')
                    ->seconds(false)
                    ->required()
                    ->after('jam_mulai'), // validasi: jam selesai harus setelah jam mulai

                Forms\Components\Toggle::make('is_active')
                    ->default(true)
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Satu baris per kelas/program — ambil jadwal dengan id terbesar sebagai representasi.
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn(
                'id',
                Jadwal::query()->selectRaw('MAX(id)')->groupBy('kelas_id')
            ))
            ->recordUrl(null)
            ->recordAction('detail')
            ->columns([
                Tables\Columns\TextColumn::make('cabang.nama_cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('kelas.nama_kelas')
                    ->label('Kelas / Program')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jumlah_jadwal')
                    ->label('Jumlah Hari Terjadwal')
                    ->alignment(Alignment::Center)
                    ->getStateUsing(
                        fn (Jadwal $record) => Jadwal::query()
                            ->where('kelas_id', $record->kelas_id)
                            ->count()
                    ),
                Tables\Columns\IconColumn::make('ada_aktif')
                    ->label('Ada Jadwal Aktif')
                    ->boolean()
                    ->alignment(Alignment::Center)
                    ->getStateUsing(
                        fn (Jadwal $record) => Jadwal::query()
                            ->where('kelas_id', $record->kelas_id)
                            ->where('is_active', true)
                            ->exists()
                    ),
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
                Tables\Actions\Action::make('detail')
                    ->label('Detail')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (Jadwal $record) => 'Detail Jadwal — ' . ($record->kelas?->nama_kelas ?? '-'))
                    ->modalContent(fn (Jadwal $record) => view('filament.resources.jadwal.detail-modal', [
                        'kelasId' => $record->kelas_id,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('4xl'),
            ])
            ->bulkActions([
                //
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
            'index' => Pages\ListJadwals::route('/'),
            'create' => Pages\CreateJadwal::route('/create'),
            'edit' => Pages\EditJadwal::route('/{record}/edit'),
        ];
    }
}
