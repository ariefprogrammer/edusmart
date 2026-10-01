<?php

namespace App\Filament\Resources\JadwalSiswaResource\Pages;

use App\Filament\Resources\JadwalSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJadwalSiswas extends ListRecords
{
    protected static string $resource = JadwalSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\CreateAction::make(),
        ];
    }
}
