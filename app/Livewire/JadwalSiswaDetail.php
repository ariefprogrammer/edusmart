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

        $siswa = Siswa::find($this->siswaId);

        if (! $siswa) {
            return collect();
        }

        // Hanya program yang masih aktif untuk siswa ini, dan hanya jam yang dipilihkan untuknya
        return $siswa->kelasAktif()
            ->with(['jadwal' => fn ($q) => $q
                ->where('is_active', true)
                ->whereIn('id', fn ($sub) => $sub
                    ->select('jadwal_id')
                    ->from('jadwal_siswa')
                    ->where('siswa_id', $this->siswaId))
                ->with('guru'),
            ])
            ->orderBy('kelas.nama_kelas')
            ->get()
            ->map(function ($kelas) use ($hariOrder) {
                $kelas->setRelation(
                    'jadwal',
                    $kelas->jadwal
                        ->sortBy(fn ($item) => [array_search($item->hari, $hariOrder), $item->jam_mulai])
                        ->values()
                );

                return $kelas;
            });
    }

    public function render()
    {
        return view('livewire.jadwal-siswa-detail');
    }
}