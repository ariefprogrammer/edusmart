<?php

namespace App\Filament\Resources\JadwalResource\Pages;

use App\Filament\Resources\JadwalResource;
use App\Models\Jadwal;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditJadwal extends EditRecord
{
    protected static string $resource = JadwalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Lapis kedua: cek bentrok guru / kelas sebelum perubahan disimpan.
        // Nilai yang tidak ada di form (mis. cabang_id untuk non super_admin)
        // diambil dari record yang sedang diedit.
        $record = $this->getRecord();

        $isActive = (bool) ($data['is_active'] ?? $record->is_active);

        // Jadwal nonaktif tidak dihitung bentrok; saat diaktifkan baru dicek.
        if ($isActive) {
            $pesan = Jadwal::pesanBentrok(
                $data['cabang_id'] ?? $record->cabang_id,
                $data['guru_id'] ?? $record->guru_id,
                $data['kelas_id'] ?? $record->kelas_id,
                $data['hari'] ?? $record->hari,
                $data['jam_mulai'] ?? $record->jam_mulai,
                $data['jam_selesai'] ?? $record->jam_selesai,
                $record->getKey(),
            );

            if ($pesan) {
                Notification::make()
                    ->danger()
                    ->title('Jadwal bentrok, perubahan tidak disimpan')
                    ->body($pesan)
                    ->persistent()
                    ->send();

                throw new Halt();
            }
        }

        return $data;
    }
}