<?php

namespace App\Filament\Resources\CabangResource\Pages;

use App\Filament\Resources\CabangResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateCabang extends CreateRecord
{
    protected static string $resource = CabangResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (isset($data['koordinat'])) {
            $data['latitude'] = $data['koordinat']['lat'] ?? null;
            $data['longitude'] = $data['koordinat']['lng'] ?? null;
            unset($data['koordinat']);
        }

        return $data;
    }
}
