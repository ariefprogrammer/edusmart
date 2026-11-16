<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\KelasResource;
use App\Filament\Resources\SiswaResource;
use App\Models\Cabang;
use App\Models\Kelas;
use App\Models\Siswa;
use Filament\Facades\Filament;
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
                ->color('primary')
                ->url($this->urlCabang()),

            Stat::make('Siswa Aktif', Siswa::where('is_active', true)->count())
                ->icon('heroicon-o-academic-cap')
                ->color('success')
                ->url(SiswaResource::getUrl('index', ['tableFilters' => ['is_active' => ['value' => 1]]])),

            Stat::make('Siswa Non-aktif', Siswa::where('is_active', false)->count())
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->url(SiswaResource::getUrl('index', ['tableFilters' => ['is_active' => ['value' => 0]]])),

            Stat::make('Total Program', Kelas::count())
                ->icon('heroicon-o-book-open')
                ->color('warning')
                ->url(KelasResource::getUrl('index')),
        ];
    }

    /**
     * Resource cabang dicari lewat modelnya, supaya tidak bergantung pada nama class-nya.
     * Bila tidak ditemukan, card tetap tampil tetapi tidak bisa diklik.
     */
    protected function urlCabang(): ?string
    {
        $resource = Filament::getModelResource(Cabang::class);

        return $resource && $resource::hasPage('index')
            ? $resource::getUrl('index')
            : null;
    }
}