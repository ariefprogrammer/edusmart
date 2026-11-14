<?php

namespace App\Filament\Resources\JadwalResource\Pages;

use App\Filament\Resources\JadwalResource;
use App\Models\Guru;
use App\Models\Jadwal;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class CreateJadwal extends CreateRecord
{
    protected static string $resource = JadwalResource::class;

    public bool $duplikatDikonfirmasi = false;
    public array $pesanDuplikat = [];
    public string $namaGuruDuplikat = '';

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

        if ($data['is_active'] ?? false) {
            // 1) Bentrok keras: guru yang sama
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

            // 2) Bentrok lunak: kelas yang sama dengan guru lain, minta konfirmasi
            if (! $this->duplikatDikonfirmasi) {
                $peringatan = [];

                foreach ($hariList as $hari) {
                    $ada = Jadwal::pesanKelasSama(
                        $data['cabang_id'],
                        $data['guru_id'],
                        $data['kelas_id'],
                        $hari,
                        $data['jam_mulai'],
                        $data['jam_selesai'],
                    );

                    if ($ada) {
                        $peringatan[] = $ada;
                    }
                }

                if ($peringatan) {
                    $this->pesanDuplikat = $peringatan;
                    $this->namaGuruDuplikat = Guru::find($data['guru_id'])?->nama ?? '-';
                    $this->mountAction('konfirmasiDuplikat');

                    throw new Halt();
                }
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

    public function konfirmasiDuplikatAction(): Action
    {
        return Action::make('konfirmasiDuplikat')
            ->requiresConfirmation()
            ->color('warning')
            ->icon('heroicon-o-exclamation-triangle')
            ->modalHeading('Jam ini sudah dijadwalkan dengan guru lain')
            ->modalDescription(fn () => new HtmlString(
                collect($this->pesanDuplikat)->map(fn ($p) => e($p))->implode('<br>')
                . '<br><br><strong>Tetap buat jadwal yang sama untuk guru '
                . e($this->namaGuruDuplikat) . '?</strong>'
            ))
            ->modalSubmitActionLabel('Ya, buat jadwal')
            ->modalCancelActionLabel('Batal')
            ->action(function () {
                $this->duplikatDikonfirmasi = true;

                try {
                    $this->create();
                } finally {
                    $this->duplikatDikonfirmasi = false;
                }
            });
    }
}