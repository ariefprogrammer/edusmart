<?php

namespace App\Filament\Resources\NilaiSiswaResource\Pages;

use App\Filament\Resources\NilaiSiswaResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNilaiSiswa extends EditRecord
{
    protected static string $resource = NilaiSiswaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
