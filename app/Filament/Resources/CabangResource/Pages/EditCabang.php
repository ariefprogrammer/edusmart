<?php

namespace App\Filament\Resources\CabangResource\Pages;

use App\Filament\Resources\CabangResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCabang extends EditRecord
{
    protected static string $resource = CabangResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (isset($data['koordinat'])) {
            $data['latitude'] = $data['koordinat']['lat'] ?? null;
            $data['longitude'] = $data['koordinat']['lng'] ?? null;
            unset($data['koordinat']);
        }

        return $data;
    }
}
