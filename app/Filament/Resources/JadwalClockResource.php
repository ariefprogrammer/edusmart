<?php

namespace App\Filament\Resources;

use App\Filament\Resources\JadwalClockResource\Pages;
use App\Filament\Resources\JadwalClockResource\RelationManagers;
use App\Models\JadwalClock;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Models\Concerns\ScopedToCabang;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Get;
use Filament\Forms\Components\CheckboxList;
use Filament\Tables\Filters\SelectFilter;

class JadwalClockResource extends Resource
{
    use ScopedToCabang;

    protected static ?string $model = JadwalClock::class;

    protected static ?string $navigationIcon = 'heroicon-o-calendar-days';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Select::make('cabang_id')
                ->relationship('cabang', 'nama_cabang')
                ->searchable()
                ->preload()
                ->required()
                ->columnSpanFull()
                ->live()
                ->visible(fn () => auth()->user()->hasRole('super_admin'))
                ->default(fn () => auth()->user()->cabang->first()?->id)
                ->dehydrated(),

            Select::make('user_id')
                ->label('Karyawan')
                ->columnSpanFull()
                ->relationship(
                    'user',
                    'name',
                    modifyQueryUsing: fn (Builder $query, Get $get) => $query
                        ->whereHas('cabang', fn ($q) => $q->where('cabangs.id', $get('cabang_id') ?? auth()->user()->cabang->first()?->id)),
                )
                ->searchable()
                ->preload()
                ->required(),

            // Membungkus pilihan hari menggunakan Fieldset agar memiliki border
            Forms\Components\Fieldset::make('Pilihan Hari')
                ->schema([
                    CheckboxList::make('hari_list')
                        ->label('') // Label dikosongkan karena sudah diwakili oleh judul Fieldset
                        ->options([
                            'Senin' => 'Senin', 'Selasa' => 'Selasa', 'Rabu' => 'Rabu',
                            'Kamis' => 'Kamis', 'Jumat' => 'Jumat', 'Sabtu' => 'Sabtu', 'Minggu' => 'Minggu',
                        ])
                        ->columns(7)
                        ->columnSpanFull()
                        ->required()
                        ->helperText('Pilih satu atau banyak hari sekaligus — jadwal clock-in/out yang sama akan dibuat untuk tiap hari yang dicentang.')
                        ->visible(fn (string $operation) => $operation === 'create'),

                    Select::make('hari')
                        ->label('')
                        ->options([
                            'Senin' => 'Senin', 'Selasa' => 'Selasa', 'Rabu' => 'Rabu',
                            'Kamis' => 'Kamis', 'Jumat' => 'Jumat', 'Sabtu' => 'Sabtu', 'Minggu' => 'Minggu',
                        ])
                        ->required()
                        ->columnSpanFull()
                        ->visible(fn (string $operation) => $operation === 'edit'),
                ])
                ->columnSpanFull(), // Pastikan Fieldset membentang penuh

            TimePicker::make('clock_in')
                ->seconds(false)
                ->required(),

            TimePicker::make('clock_out')
                ->seconds(false)
                ->required()
                ->after('clock_in'),

            Toggle::make('is_active')
                ->default(true)
                ->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            // Satu baris per guru — ambil jadwal dengan id terbesar sebagai representasi.
            ->modifyQueryUsing(fn (Builder $query) => $query->whereIn(
                'id',
                JadwalClock::query()->selectRaw('MAX(id)')->groupBy('user_id')
            ))
            ->recordUrl(null)
            ->recordAction('detail')
            ->columns([
                Tables\Columns\TextColumn::make('cabang.nama_cabang')
                    ->label('Cabang')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Karyawan')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('jumlah_hari')
                    ->label('Jumlah Hari Terjadwal')
                    ->getStateUsing(
                        fn (JadwalClock $record) => JadwalClock::query()
                            ->where('user_id', $record->user_id)
                            ->count()
                    ),
                Tables\Columns\IconColumn::make('ada_aktif')
                    ->label('Ada Jadwal Aktif')
                    ->boolean()
                    ->getStateUsing(
                        fn (JadwalClock $record) => JadwalClock::query()
                            ->where('user_id', $record->user_id)
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
                    ->modalHeading(fn (JadwalClock $record) => 'Detail Jadwal — ' . ($record->user?->name ?? '-'))
                    ->modalContent(fn (JadwalClock $record) => view('filament.resources.jadwal-clock.detail-modal', [
                        'userId' => $record->user_id,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalWidth('3xl'),
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
            'index' => Pages\ListJadwalClocks::route('/'),
            'create' => Pages\CreateJadwalClock::route('/create'),
            'edit' => Pages\EditJadwalClock::route('/{record}/edit'),
        ];
    }
}
