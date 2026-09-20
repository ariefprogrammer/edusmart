<?php

namespace App\Filament\Resources\PresensiJadwalResource\Pages;

use App\Filament\Resources\PresensiJadwalResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPresensiJadwals extends ListRecords
{
    protected static string $resource = PresensiJadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
