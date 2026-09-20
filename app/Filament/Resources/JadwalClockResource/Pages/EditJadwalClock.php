<?php

namespace App\Filament\Resources\JadwalClockResource\Pages;

use App\Filament\Resources\JadwalClockResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditJadwalClock extends EditRecord
{
    protected static string $resource = JadwalClockResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
