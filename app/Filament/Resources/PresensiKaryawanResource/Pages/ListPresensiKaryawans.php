<?php

namespace App\Filament\Resources\PresensiKaryawanResource\Pages;

use App\Filament\Resources\PresensiKaryawanResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListPresensiKaryawans extends ListRecords
{
    protected static string $resource = PresensiKaryawanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
