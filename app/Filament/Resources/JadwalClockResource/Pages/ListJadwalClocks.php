<?php

namespace App\Filament\Resources\JadwalClockResource\Pages;

use App\Filament\Resources\JadwalClockResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJadwalClocks extends ListRecords
{
    protected static string $resource = JadwalClockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
