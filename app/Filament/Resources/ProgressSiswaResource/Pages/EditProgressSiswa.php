<?php

namespace App\Filament\Resources\ProgressSiswaResource\Pages;

use App\Filament\Resources\ProgressSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProgressSiswa extends EditRecord
{
    protected static string $resource = ProgressSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
