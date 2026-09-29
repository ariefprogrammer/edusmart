<?php

namespace App\Filament\Resources\JadwalResource\Pages;

use App\Filament\Resources\JadwalResource;
use App\Models\Jadwal;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

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

    protected function handleRecordCreation(array $data): Model
    {
        $hariList = $data['hari_list'] ?? [];
        unset($data['hari_list']);

        $records = collect($hariList)->map(
            fn (string $hari) => Jadwal::updateOrCreate(
                [
                    'kelas_id' => $data['kelas_id'],
                    'hari' => $hari,
                    'guru_id' => $data['guru_id'],
                    'jam_mulai' => $data['jam_mulai'],
                ],
                [...$data, 'hari' => $hari],
            )
        );

        return $records->first();
    }
}