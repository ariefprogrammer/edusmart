<?php

namespace App\Filament\Resources\PeriodeResource\Pages;

use App\Filament\Resources\PeriodeResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;

class CreatePeriode extends CreateRecord
{
    protected static string $resource = PeriodeResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->hasRole('super_admin')) {
            $data['cabang_id'] = auth()->user()->cabang->first()?->id;
        }

        return $data;
    }
}
