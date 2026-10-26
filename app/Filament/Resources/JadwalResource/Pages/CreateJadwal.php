<?php

namespace App\Filament\Resources\JadwalResource\Pages;

use App\Filament\Resources\JadwalResource;
use App\Models\Jadwal;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
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

        // Lapis kedua: cek bentrok guru / kelas sebelum satu pun jadwal disimpan.
        // Jadwal nonaktif tidak dicek karena tidak dihitung sebagai bentrok.
        if ($data['is_active'] ?? false) {
            $pesan = [];

            foreach ($hariList as $hari) {
                $bentrok = Jadwal::pesanBentrok(
                    $data['cabang_id'],
                    $data['guru_id'],
                    $data['kelas_id'],
                    $hari,
                    $data['jam_mulai'],
                    $data['jam_selesai'],
                );

                if ($bentrok) {
                    $pesan[] = $bentrok;
                }
            }

            if ($pesan) {
                Notification::make()
                    ->danger()
                    ->title('Jadwal bentrok, tidak ada jadwal yang disimpan')
                    ->body(implode(' ', $pesan))
                    ->persistent()
                    ->send();

                throw new Halt();
            }
        }

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