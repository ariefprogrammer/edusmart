<?php

namespace App\Console\Commands;

use App\Models\Jadwal;
use App\Models\JadwalClock;
use App\Models\PresensiJadwal;
use App\Models\PresensiKaryawan;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use App\Support\Hari;

class TandaiTidakCheckoutPresensi extends Command
{
    protected $signature = 'presensi:tandai-tidak-checkout';
    protected $description = 'Menandai status_keluar = tidak_checkout untuk presensi yang lewat jam pulang tanpa checkout sama sekali';

    public function handle(): void
    {
        $hariIni = Hari::ini();
        $jamSekarang = now()->format('H:i:s');

        $this->tandaiKaryawan($hariIni, $jamSekarang);
        $this->tandaiJadwal($hariIni, $jamSekarang);
    }

    protected function tandaiKaryawan(string $hariIni, string $jamSekarang): void
    {
        JadwalClock::where('hari', $hariIni)
            ->where('is_active', true)
            ->where('clock_out', '<=', $jamSekarang)
            ->each(function (JadwalClock $jc) {
                $presensi = PresensiKaryawan::where('user_id', $jc->user_id)
                    ->whereDate('tanggal', today())
                    ->first();

                // Kalau tidak ada baris presensi sama sekali, berarti tidak pernah check-in juga -> di luar cakupan fitur ini
                if ($presensi && is_null($presensi->check_out) && is_null($presensi->status_keluar)) {
                    $presensi->update(['status_keluar' => 'tidak_checkout']);
                }
            });
    }

    protected function tandaiJadwal(string $hariIni, string $jamSekarang): void
    {
        Jadwal::where('hari', $hariIni)
            ->where('is_active', true)
            ->where('jam_selesai', '<=', $jamSekarang)
            ->each(function (Jadwal $jadwal) {
                $presensi = PresensiJadwal::where('jadwal_id', $jadwal->id)
                    ->whereDate('tanggal', today())
                    ->first();

                if ($presensi && is_null($presensi->check_out) && is_null($presensi->status_keluar)) {
                    $presensi->update(['status_keluar' => 'tidak_checkout']);
                }
            });
    }
}