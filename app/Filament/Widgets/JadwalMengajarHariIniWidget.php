<?php

namespace App\Filament\Widgets;

use App\Models\Jadwal;
use Carbon\Carbon;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class JadwalMengajarHariIniWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('guru') ?? false;
    }

    public function getHeading(): string
    {
        return 'Jadwal Mengajar Hari Ini';
    }

    public function table(Table $table): Table
    {
        $guruId = auth()->user()->guru?->id;
        $hariIni = ucfirst(Carbon::now()->locale('id')->isoFormat('dddd'));

        return $table
            ->query(
                Jadwal::query()
                    ->where('guru_id', $guruId)
                    ->where('hari', $hariIni)
            )
            ->columns([
                Tables\Columns\TextColumn::make('kelas.nama_kelas')
                    ->label('Nama Program'),
                Tables\Columns\TextColumn::make('jam_mulai')
                    ->label('Jam Mulai')
                    ->time('H:i'),
                Tables\Columns\TextColumn::make('jam_selesai')
                    ->label('Jam Selesai')
                    ->time('H:i'),
            ])
            ->paginated(false);
    }
}