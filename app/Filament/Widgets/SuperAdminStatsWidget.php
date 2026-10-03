<?php

namespace App\Filament\Widgets;

use App\Models\Cabang;
use App\Models\Kelas;
use App\Models\Siswa;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SuperAdminStatsWidget extends BaseWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    protected function getColumns(): int
    {
        return 4;
    }

    protected function getStats(): array
    {
        return [
            Stat::make('Total Cabang', Cabang::count())
                ->icon('heroicon-o-building-office')
                ->color('primary'),

            Stat::make('Siswa Aktif', Siswa::where('is_active', true)->count())
                ->icon('heroicon-o-academic-cap')
                ->color('success'),

            Stat::make('Siswa Non-aktif', Siswa::where('is_active', false)->count())
                ->icon('heroicon-o-user-minus')
                ->color('danger'),

            Stat::make('Total Program', Kelas::count())
                ->icon('heroicon-o-book-open')
                ->color('warning'),
        ];
    }
}