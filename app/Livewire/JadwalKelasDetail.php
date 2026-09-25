<?php

namespace App\Livewire;

use App\Models\Jadwal;
use Filament\Notifications\Notification;
use Livewire\Component;

class JadwalKelasDetail extends Component
{
    public int $kelasId;

    public array $selected = [];

    public bool $selectAll = false;

    public function mount(int $kelasId): void
    {
        $this->kelasId = $kelasId;
    }

    public function getJadwalProperty()
    {
        $hariOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        return Jadwal::query()
            ->where('kelas_id', $this->kelasId)
            ->with('guru')
            ->get()
            ->sortBy(fn ($item) => array_search($item->hari, $hariOrder))
            ->values();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value
            ? $this->jadwal->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    public function deleteOne(int $id): void
    {
        Jadwal::query()->whereKey($id)->delete();

        $this->selected = array_values(array_diff($this->selected, [(string) $id]));
        $this->selectAll = false;

        $this->afterDelete('Jadwal berhasil dihapus.');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $count = count($this->selected);

        Jadwal::query()->whereIn('id', $this->selected)->delete();

        $this->selected = [];
        $this->selectAll = false;

        $this->afterDelete("{$count} jadwal berhasil dihapus.");
    }

    public function deleteAll(): void
    {
        Jadwal::query()->where('kelas_id', $this->kelasId)->delete();

        $this->selected = [];
        $this->selectAll = false;

        $this->afterDelete('Seluruh jadwal kelas ini berhasil dihapus.');
    }

    protected function afterDelete(string $message): void
    {
        Notification::make()
            ->title($message)
            ->success()
            ->send();

        $this->dispatch('jadwal-kelas-updated');
    }

    public function render()
    {
        return view('livewire.jadwal-kelas-detail');
    }
}