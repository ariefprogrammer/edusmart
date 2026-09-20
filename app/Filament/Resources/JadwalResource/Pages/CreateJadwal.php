<?php

namespace App\Filament\Resources\JadwalResource\Pages;

use App\Filament\Resources\JadwalResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreateJadwal extends CreateRecord
{
    protected static string $resource = JadwalResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->hasRole('super_admin')) {
            $data['cabang_id'] = auth()->user()->cabang->first()?->id;
        }

        return $data;
    }
}
