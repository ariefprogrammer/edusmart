<?php

namespace App\Livewire;

use App\Models\NilaiSiswa;
use Filament\Notifications\Notification;
use Livewire\Component;

class NilaiSiswaDetail extends Component
{
    public int $siswaId;

    public array $selected = [];

    public bool $selectAll = false;

    public function mount(int $siswaId): void
    {
        $this->siswaId = $siswaId;
    }

    public function getNilaiProperty()
    {
        return NilaiSiswa::query()
            ->where('siswa_id', $this->siswaId)
            ->with(['kelas', 'guru', 'kategoriNilai'])
            ->latest('id')
            ->get();
    }

    public function updatedSelectAll(bool $value): void
    {
        $this->selected = $value
            ? $this->nilai->pluck('id')->map(fn ($id) => (string) $id)->toArray()
            : [];
    }

    public function deleteOne(int $id): void
    {
        NilaiSiswa::query()->whereKey($id)->delete();

        $this->selected = array_values(array_diff($this->selected, [(string) $id]));
        $this->selectAll = false;

        $this->afterDelete('Nilai berhasil dihapus.');
    }

    public function deleteSelected(): void
    {
        if (empty($this->selected)) {
            return;
        }

        $count = count($this->selected);

        NilaiSiswa::query()->whereIn('id', $this->selected)->delete();

        $this->selected = [];
        $this->selectAll = false;

        $this->afterDelete("{$count} nilai berhasil dihapus.");
    }

    public function deleteAll(): void
    {
        NilaiSiswa::query()->where('siswa_id', $this->siswaId)->delete();

        $this->selected = [];
        $this->selectAll = false;

        $this->afterDelete('Seluruh nilai siswa ini berhasil dihapus.');
    }

    protected function afterDelete(string $message): void
    {
        Notification::make()
            ->title($message)
            ->success()
            ->send();

        $this->dispatch('nilai-siswa-updated');
    }

    public function render()
    {
        return view('livewire.nilai-siswa-detail');
    }
}