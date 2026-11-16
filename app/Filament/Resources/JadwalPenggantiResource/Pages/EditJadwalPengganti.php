<?php

namespace App\Filament\Resources\JadwalPenggantiResource\Pages;

use App\Filament\Resources\JadwalPenggantiResource;
use App\Models\JadwalPengganti;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditJadwalPengganti extends EditRecord
{
    protected static string $resource = JadwalPenggantiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Actions\DeleteAction::make(),
        ];
    }

    protected function getSaveFormAction(): Action
    {
        $guruBerbeda = fn (): bool => JadwalPenggantiResource::jadwalDenganGuruBerbeda($this->data) !== null;

        return Action::make('save')
            ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
            ->action(fn () => $this->save())
            ->keyBindings(['mod+s'])
            ->requiresConfirmation($guruBerbeda)
            ->modalHeading(fn (): ?string => $guruBerbeda() ? 'Guru berbeda dari jadwal yang dipilih' : null)
            ->modalDescription(fn (): ?string => $guruBerbeda() ? JadwalPenggantiResource::pesanGuruBerbeda($this->data) : null)
            ->modalSubmitActionLabel(fn (): ?string => $guruBerbeda() ? 'Ya, lanjutkan' : null);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        $siswaIds = $this->data['siswa'] ?? [];
        $tanggalSeharusnya = $data['tanggal_seharusnya'] ?? $record->tanggal_seharusnya->toDateString();
        $tanggalPengganti = $data['tanggal_pengganti'] ?? $record->tanggal_pengganti->toDateString();

        // Jadwal tujuan yang sengaja dikosongkan tidak boleh kembali ke nilai lama
        $jadwalTujuanId = array_key_exists('jadwal_tujuan_id', $data)
            ? $data['jadwal_tujuan_id']
            : $record->jadwal_tujuan_id;

        // Data lama yang beda bulan tetap bisa diedit selama kedua tanggalnya tidak diubah
        $tanggalBerubah = \Carbon\Carbon::parse($tanggalSeharusnya)->toDateString() !== $record->tanggal_seharusnya->toDateString()
            || \Carbon\Carbon::parse($tanggalPengganti)->toDateString() !== $record->tanggal_pengganti->toDateString();

        // Lapis kedua: cek ulang tepat sebelum menyimpan
        $pesan = array_filter([
            $tanggalBerubah ? JadwalPengganti::pesanBedaBulan($tanggalSeharusnya, $tanggalPengganti) : null,
            JadwalPengganti::pesanBentrok(
                $data['cabang_id'] ?? $record->cabang_id,
                $data['guru_id'] ?? $record->guru_id,
                $data['kelas_id'] ?? $record->kelas_id,
                $tanggalPengganti,
                $data['jam_mulai'] ?? $record->jam_mulai,
                $data['jam_selesai'] ?? $record->jam_selesai,
                $record->getKey(),
                $jadwalTujuanId,
            ),
            JadwalPengganti::pesanSiswaBermasalah(
                $data['kelas_id'] ?? $record->kelas_id,
                $tanggalSeharusnya,
                $siswaIds,
                $record->getKey(),
                $record->siswa()->pluck('siswa.id')->all(),
                $data['jadwal_id'] ?? $record->jadwal_id,
            ),
            // Data lama tanpa jadwal tujuan tidak dicek kuotanya
            filled($jadwalTujuanId)
                ? JadwalPengganti::pesanKuotaTujuan($jadwalTujuanId, $tanggalPengganti, $siswaIds, $record->getKey())
                : null,
        ]);

        if ($pesan) {
            Notification::make()
                ->danger()
                ->title('Perubahan tidak disimpan')
                ->body(implode(' ', $pesan))
                ->persistent()
                ->send();

            throw new Halt();
        }

        return $data;
    }
}