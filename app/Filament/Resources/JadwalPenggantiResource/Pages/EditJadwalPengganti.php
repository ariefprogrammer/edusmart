<?php

namespace App\Filament\Resources\JadwalPenggantiResource\Pages;

use App\Filament\Resources\JadwalPenggantiResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use App\Models\JadwalPengganti;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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
            ->modalHeading(fn (): ?string => $guruBerbeda() ? 'Guru berbeda dari jadwal reguler' : null)
            ->modalDescription(fn (): ?string => $guruBerbeda() ? JadwalPenggantiResource::pesanGuruBerbeda($this->data) : null)
            ->modalSubmitActionLabel(fn (): ?string => $guruBerbeda() ? 'Ya, lanjutkan' : null);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        $pesan = array_filter([
            JadwalPengganti::pesanBentrok(
                $data['cabang_id'] ?? $record->cabang_id,
                $data['guru_id'] ?? $record->guru_id,
                $data['kelas_id'] ?? $record->kelas_id,
                $data['tanggal_pengganti'] ?? $record->tanggal_pengganti->toDateString(),
                $data['jam_mulai'] ?? $record->jam_mulai,
                $data['jam_selesai'] ?? $record->jam_selesai,
                $record->getKey(),
            ),
            JadwalPengganti::pesanSiswaBermasalah(
                $data['kelas_id'] ?? $record->kelas_id,
                $data['tanggal_seharusnya'] ?? $record->tanggal_seharusnya->toDateString(),
                $this->data['siswa'] ?? [],
                $record->getKey(),
                $record->siswa()->pluck('siswa.id')->all(),
            ),
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
