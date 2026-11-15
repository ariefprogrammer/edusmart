<?php

namespace App\Filament\Resources\JadwalPenggantiResource\Pages;

use App\Filament\Resources\JadwalPenggantiResource;
use App\Models\JadwalPengganti;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateJadwalPengganti extends CreateRecord
{
    protected static string $resource = JadwalPenggantiResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()->hasRole('super_admin')) {
            $data['cabang_id'] = auth()->user()->cabang->first()?->id;
        }

        $data['dibuat_oleh'] = auth()->id();

        return $data;
    }

    protected function getCreateFormAction(): Action
    {
        $guruBerbeda = fn (): bool => JadwalPenggantiResource::jadwalDenganGuruBerbeda($this->data) !== null;

        return Action::make('create')
            ->label(__('filament-panels::resources/pages/create-record.form.actions.create.label'))
            ->action(fn () => $this->create())
            ->keyBindings(['mod+s'])
            ->requiresConfirmation($guruBerbeda)
            ->modalHeading(fn (): ?string => $guruBerbeda() ? 'Guru berbeda dari jadwal yang dipilih' : null)
            ->modalDescription(fn (): ?string => $guruBerbeda() ? JadwalPenggantiResource::pesanGuruBerbeda($this->data) : null)
            ->modalSubmitActionLabel(fn (): ?string => $guruBerbeda() ? 'Ya, lanjutkan' : null);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $siswaIds = $this->data['siswa'] ?? [];
        $jadwalTujuanId = $data['jadwal_tujuan_id'] ?? null;

        // Lapis kedua: cek ulang tepat sebelum menyimpan
        $pesan = array_filter([
            JadwalPengganti::pesanBentrok(
                $data['cabang_id'], $data['guru_id'], $data['kelas_id'],
                $data['tanggal_pengganti'], $data['jam_mulai'], $data['jam_selesai'],
                null,
                $jadwalTujuanId,
            ),
            JadwalPengganti::pesanSiswaBermasalah(
                $data['kelas_id'], $data['tanggal_seharusnya'], $siswaIds,
                null,
                [],
                $data['jadwal_id'] ?? null,
            ),
            filled($jadwalTujuanId)
                ? JadwalPengganti::pesanKuotaTujuan($jadwalTujuanId, $data['tanggal_pengganti'], $siswaIds)
                : null,
        ]);

        if ($pesan) {
            Notification::make()
                ->danger()
                ->title('Jadwal pengganti tidak disimpan')
                ->body(implode(' ', $pesan))
                ->persistent()
                ->send();

            throw new Halt();
        }

        return parent::handleRecordCreation($data);
    }
}