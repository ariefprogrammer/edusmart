<?php

namespace App\Livewire;

use App\Models\JadwalClock;
use Filament\Notifications\Notification;
use Livewire\Attributes\On;
use Livewire\Component;

class JadwalGuruDetail extends Component
{
    public int $userId;

    public array $selected = [];

    public bool $selectAll = false;

    public function mount(int $userId): void
    {
        $this->userId = $userId;
    }

    public function getJadwalProperty()
    {
        $hariOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        return JadwalClock::query()
            ->where('user_id', $this->userId)
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
        JadwalClock::query()->whereKey($id)->delete();

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

        JadwalClock::query()->whereIn('id', $this->selected)->delete();

        $this->selected = [];
        $this->selectAll = false;

        $this->afterDelete("{$count} jadwal berhasil dihapus.");
    }

    public function deleteAll(): void
    {
        JadwalClock::query()->where('user_id', $this->userId)->delete();

        $this->selected = [];
        $this->selectAll = false;

        $this->afterDelete('Seluruh jadwal karyawan ini berhasil dihapus.');
    }

    protected function afterDelete(string $message): void
    {
        Notification::make()
            ->title($message)
            ->success()
            ->send();

        // Beritahu tabel utama (ListJadwalClocks) untuk refresh datanya.
        $this->dispatch('jadwal-updated');
    }

    public function render()
    {
        return view('livewire.jadwal-guru-detail');
    }
}