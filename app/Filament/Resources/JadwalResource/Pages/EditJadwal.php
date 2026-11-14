<?php

namespace App\Filament\Resources\JadwalResource\Pages;

use App\Filament\Resources\JadwalResource;
use App\Models\Guru;
use App\Models\Jadwal;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Support\HtmlString;

class EditJadwal extends EditRecord
{
    protected static string $resource = JadwalResource::class;

    public bool $duplikatDikonfirmasi = false;
    public string $pesanDuplikat = '';
    public string $namaGuruDuplikat = '';

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        $isActive = (bool) ($data['is_active'] ?? $record->is_active);

        if ($isActive) {
            $cabangId = $data['cabang_id'] ?? $record->cabang_id;
            $guruId = $data['guru_id'] ?? $record->guru_id;
            $kelasId = $data['kelas_id'] ?? $record->kelas_id;
            $hari = $data['hari'] ?? $record->hari;
            $jamMulai = $data['jam_mulai'] ?? $record->jam_mulai;
            $jamSelesai = $data['jam_selesai'] ?? $record->jam_selesai;

            // Bentrok keras: guru yang sama
            $pesan = Jadwal::pesanBentrok(
                $cabangId, $guruId, $kelasId, $hari, $jamMulai, $jamSelesai, $record->getKey(),
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

            // Bentrok lunak: kelas yang sama dengan guru lain, minta konfirmasi
            if (! $this->duplikatDikonfirmasi) {
                $ada = Jadwal::pesanKelasSama(
                    $cabangId, $guruId, $kelasId, $hari, $jamMulai, $jamSelesai, $record->getKey(),
                );

                if ($ada) {
                    $this->pesanDuplikat = $ada;
                    $this->namaGuruDuplikat = Guru::find($guruId)?->nama ?? '-';
                    $this->mountAction('konfirmasiDuplikat');

                    throw new Halt();
                }
            }
        }

        return $data;
    }

    public function konfirmasiDuplikatAction(): Actions\Action
    {
        return Actions\Action::make('konfirmasiDuplikat')
            ->requiresConfirmation()
            ->color('warning')
            ->icon('heroicon-o-exclamation-triangle')
            ->modalHeading('Jam ini sudah dijadwalkan dengan guru lain')
            ->modalDescription(fn () => new HtmlString(
                e($this->pesanDuplikat)
                . '<br><br><strong>Tetap simpan jadwal yang sama untuk guru '
                . e($this->namaGuruDuplikat) . '?</strong>'
            ))
            ->modalSubmitActionLabel('Ya, simpan')
            ->modalCancelActionLabel('Batal')
            ->action(function () {
                $this->duplikatDikonfirmasi = true;

                try {
                    $this->save();
                } finally {
                    $this->duplikatDikonfirmasi = false;
                }
            });
    }
}