<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Str;

class Dashboard extends BaseDashboard
{
    protected static string $view = 'filament.pages.dashboard';
    protected static ?string $navigationLabel = 'Dashboard';
    
    public function getHeading(): string
    {
        return 'Dashboard';
    }

    protected function getViewData(): array
    {
        $user = auth()->user();

        return [
            'namaUser' => $user->name,
            'roleUser' => $user->getRoleNames()
                ->map(fn ($role) => Str::headline($role))
                ->join(', '),
        ];
    }
}