<?php

namespace App\Filament\Widgets;

use App\Models\Kelas;
use App\Models\Siswa;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class AdminCabangStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('admin_cabang') ?? false;
    }

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $cabangId = auth()->user()->cabang->first()?->id;

        return [
            Stat::make(
                'Siswa Aktif',
                Siswa::where('cabang_id', $cabangId)->where('is_active', true)->count()
            )
                ->icon('heroicon-o-academic-cap')
                ->color('success'),

            Stat::make(
                'Siswa Non-Aktif',
                Siswa::where('cabang_id', $cabangId)->where('is_active', false)->count()
            )
                ->icon('heroicon-o-user-minus')
                ->color('danger'),

            Stat::make(
                'Program / Kelas',
                Kelas::where('cabang_id', $cabangId)->count()
            )
                ->icon('heroicon-o-book-open')
                ->color('warning'),
        ];
    }
}