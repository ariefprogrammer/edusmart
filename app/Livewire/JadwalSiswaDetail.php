<?php

namespace App\Livewire;

use App\Models\Siswa;
use Livewire\Component;

class JadwalSiswaDetail extends Component
{
    public int $siswaId;

    public function mount(int $siswaId): void
    {
        $this->siswaId = $siswaId;
    }

    public function getProgramListProperty()
    {
        $hariOrder = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

        $siswa = Siswa::with([
            'kelasList' => fn ($q) => $q->with(['jadwal' => fn ($q2) => $q2->with('guru')]),
        ])->find($this->siswaId);

        return $siswa?->kelasList->map(function ($kelas) use ($hariOrder) {
            $kelas->setRelation(
                'jadwal',
                $kelas->jadwal->sortBy(fn ($item) => array_search($item->hari, $hariOrder))->values()
            );

            return $kelas;
        }) ?? collect();
    }

    public function render()
    {
        return view('livewire.jadwal-siswa-detail');
    }
}