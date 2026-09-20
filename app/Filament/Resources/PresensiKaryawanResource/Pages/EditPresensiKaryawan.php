<?php

namespace App\Filament\Resources\PresensiKaryawanResource\Pages;

use App\Filament\Resources\PresensiKaryawanResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditPresensiKaryawan extends EditRecord
{
    protected static string $resource = PresensiKaryawanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
