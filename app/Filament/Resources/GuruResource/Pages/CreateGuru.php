<?php

namespace App\Filament\Resources\GuruResource\Pages;

use App\Filament\Resources\GuruResource;
use Filament\Resources\Pages\CreateRecord;

class CreateGuru extends CreateRecord
{
    protected static string $resource = GuruResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->hasRole('super_admin')) {
            $data['cabang_id'] = auth()->user()->cabang->first()?->id;
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->user?->syncRoles(['guru']);
        $this->record->user?->cabang()->sync([$this->record->cabang_id]);
    }
}