<?php

namespace App\Filament\Resources\JadwalClockResource\Pages;

use App\Filament\Resources\JadwalClockResource;
use App\Models\JadwalClock;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateJadwalClock extends CreateRecord
{
    protected static string $resource = JadwalClockResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->hasRole('super_admin')) {
            $data['cabang_id'] = auth()->user()->cabang->first()?->id;
        }

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $hariList = $data['hari_list'] ?? [];
        unset($data['hari_list']);

        $records = collect($hariList)->map(
            fn (string $hari) => JadwalClock::updateOrCreate(
                ['user_id' => $data['user_id'], 'hari' => $hari],
                [...$data, 'hari' => $hari],
            )
        );

        return $records->first();
    }
}