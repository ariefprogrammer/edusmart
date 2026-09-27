<?php

namespace App\Livewire;

use App\Models\PresensiJadwal;
use Filament\Notifications\Notification;
use Livewire\Component;

class PresensiJadwalDetail extends Component
{
    public int $kelasId;

    public array $selected = [];

    public bool $selectAll = false;

    public function mount(int $kelasId): void
    {
        $this->kelasId = $kelasId;
    }

    public function getPresensiProperty()
    {
        return PresensiJadwal::query()
            ->whereHas('jadwal', fn ($q) => $q->where('kelas_id', $this->kelasId))
            ->with(['guru', 'jadwal.kelas'])
            ->latest('tanggal')
            ->latest('id')
            ->get();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value
            ? $this->presensi->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    public function deleteOne(int $id): void
    {
        PresensiJadwal::query()->whereKey($id)->delete();

        $this->selected = array_values(array_diff($this->selected, [(string) $id]));
        $this->selectAll = false;

        $this->afterDelete('Presensi berhasil dihapus.');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $count = count($this->selected);

        PresensiJadwal::query()->whereIn('id', $this->selected)->delete();

        $this->selected = [];
        $this->selectAll = false;

        $this->afterDelete("{$count} presensi berhasil dihapus.");
    }

    public function deleteAll(): void
    {
        PresensiJadwal::query()
            ->whereHas('jadwal', fn ($q) => $q->where('kelas_id', $this->kelasId))
            ->delete();

        $this->selected = [];
        $this->selectAll = false;

        $this->afterDelete('Seluruh presensi kelas/program ini berhasil dihapus.');
    }

    protected function afterDelete(string $message): void
    {
        Notification::make()
            ->title($message)
            ->success()
            ->send();

        $this->dispatch('presensi-jadwal-updated');
    }

    public function render()
    {
        return view('livewire.presensi-jadwal-detail');
    }
}