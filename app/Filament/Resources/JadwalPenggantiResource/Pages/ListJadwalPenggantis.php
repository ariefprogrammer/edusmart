<?php

namespace App\Filament\Resources\JadwalPenggantiResource\Pages;

use App\Filament\Resources\JadwalPenggantiResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListJadwalPenggantis extends ListRecords
{
    protected static string $resource = JadwalPenggantiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
