<?php

namespace App\Filament\Resources\ProgressSiswaResource\Pages;

use App\Filament\Resources\ProgressSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListProgressSiswas extends ListRecords
{
    protected static string $resource = ProgressSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
