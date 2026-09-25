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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use pxlrbt\FilamentExcel\Actions\Tables\ExportAction;
use pxlrbt\FilamentExcel\Actions\Tables\ExportBulkAction;
use pxlrbt\FilamentExcel\Exports\ExcelExport;
use pxlrbt\FilamentExcel\Columns\Column;
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
                Forms\Components\Section::make('Data Presensi')
                    ->schema([
                        Forms\Components\Placeholder::make('cabang_display')
                            ->label('Cabang')
                            ->content(fn ($record) => $record?->cabang?->nama_cabang ?? '-'),

                        Forms\Components\Placeholder::make('user_display')
                            ->label('Nama')
                            ->content(fn ($record) => $record?->user?->name ?? '-'),

                        Forms\Components\Placeholder::make('tanggal_display')
                            ->label('Tanggal')
                            ->content(fn ($record) => $record?->tanggal
                                ? \Carbon\Carbon::parse($record->tanggal)->translatedFormat('d F Y')
                                : '-'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Check In')
                    ->schema([
                        Forms\Components\DateTimePicker::make('check_in'),
                        Forms\Components\TextInput::make('check_in_accuracy')
                            ->numeric()
                            ->default(null),
                        Forms\Components\TextInput::make('status_masuk'),
                        Forms\Components\Placeholder::make('check_in_foto_preview')
                            ->label('Preview Foto Masuk')
                            ->content(function ($record) {
                                if (!$record || !$record->check_in_foto) {
                                    return new HtmlString('<span class="text-sm text-gray-400">Tidak ada foto</span>');
                                }

                                $url = Storage::disk('public')->url($record->check_in_foto);

                                return new HtmlString(
                                    '<img src="' . e($url) . '" style="max-width: 240px; border-radius: 0.5rem;" />'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Check Out')
                    ->schema([
                        Forms\Components\DateTimePicker::make('check_out'),                        
                        Forms\Components\TextInput::make('check_out_accuracy')
                            ->numeric()
                            ->default(null),
                        Forms\Components\TextInput::make('status_keluar'),
                        Forms\Components\Placeholder::make('check_out_foto_preview')
                            ->label('Preview Foto Keluar')
                            ->content(function ($record) {
                                if (!$record || !$record->check_out_foto) {
                                    return new HtmlString('<span class="text-sm text-gray-400">Tidak ada foto</span>');
                                }

                                $url = Storage::disk('public')->url($record->check_out_foto);

                                return new HtmlString(
                                    '<img src="' . e($url) . '" style="max-width: 240px; border-radius: 0.5rem;" />'
                                );
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Peta Lokasi Presensi')
                    ->schema([
                        Forms\Components\ViewField::make('koordinat_map')
                            ->label(false)
                            ->view('filament.forms.components.presensi-map')
                            ->viewData(fn ($record) => [
                                'checkInLat' => $record?->check_in_lat,
                                'checkInLng' => $record?->check_in_lng,
                                'checkOutLat' => $record?->check_out_lat,
                                'checkOutLng' => $record?->check_out_lng,
                            ])
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Textarea::make('keterangan')
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
                SelectFilter::make('cabang_id')
                    ->label('Cabang')
                    ->relationship('cabang', 'nama_cabang')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('user_id')
                    ->label('Karyawan')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status_masuk')
                    ->label('Status Masuk')
                    ->options([
                        'hadir' => 'Hadir',
                        'terlambat' => 'Terlambat',
                    ]),

                SelectFilter::make('status_keluar')
                    ->label('Status Keluar')
                    ->options([
                        'pulang' => 'Pulang',
                        'bolos' => 'Bolos',
                        'tidak_checkout' => 'Tidak Checkout',
                    ]),

                Filter::make('tanggal')
                    ->form([
                        Forms\Components\DatePicker::make('tanggal_dari')
                            ->label('Dari Tanggal'),
                        Forms\Components\DatePicker::make('tanggal_sampai')
                            ->label('Sampai Tanggal'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['tanggal_dari'],
                                fn (Builder $query, $date) => $query->whereDate('tanggal', '>=', $date),
                            )
                            ->when(
                                $data['tanggal_sampai'],
                                fn (Builder $query, $date) => $query->whereDate('tanggal', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['tanggal_dari'] ?? null) {
                            $indicators[] = 'Dari ' . \Carbon\Carbon::parse($data['tanggal_dari'])->format('d M Y');
                        }

                        if ($data['tanggal_sampai'] ?? null) {
                            $indicators[] = 'Sampai ' . \Carbon\Carbon::parse($data['tanggal_sampai'])->format('d M Y');
                        }

                        return $indicators;
                    }),
            ])
            ->filtersFormColumns(3)
            ->headerActions([
                ExportAction::make()
                    ->label('Export Excel')
                    ->extraAttributes([
                            'style' => 'background-color: #2563eb; color: #ffffff; border-color: #2563eb;',
                        ])
                    ->exports([
                        ExcelExport::make()
                            ->fromTable()
                            ->except([
                                'check_in_foto',
                                'check_out_foto',
                            ])
                            ->withColumns([
                                Column::make('cabang.nama_cabang')->heading('Cabang'),
                                Column::make('user.name')->heading('Karyawan'),
                                Column::make('tanggal')->heading('Tanggal'),
                                Column::make('check_in')->heading('Check In'),
                                Column::make('status_masuk')->heading('Status Masuk'),
                                Column::make('check_out')->heading('Check Out'),
                                Column::make('status_keluar')->heading('Status Keluar'),
                            ])
                            ->withFilename(fn () => 'presensi-karyawan-' . now()->format('Y-m-d_His')),
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    ExportBulkAction::make()
                        ->label('Export Terpilih')
                        ->exports([
                            ExcelExport::make()
                                ->fromTable()
                                ->except([
                                    'check_in_foto',
                                    'check_out_foto',
                                ])
                                ->withColumns([
                                    Column::make('cabang.nama_cabang')->heading('Cabang'),
                                    Column::make('user.name')->heading('Karyawan'),
                                    Column::make('tanggal')->heading('Tanggal'),
                                    Column::make('check_in')->heading('Check In'),
                                    Column::make('status_masuk')->heading('Status Masuk'),
                                    Column::make('check_out')->heading('Check Out'),
                                    Column::make('status_keluar')->heading('Status Keluar'),
                                ])
                                ->withFilename(fn () => 'presensi-karyawan-terpilih-' . now()->format('Y-m-d_His')),
                        ]),
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