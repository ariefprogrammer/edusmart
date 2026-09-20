<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['presensi_karyawan', 'presensi_jadwal'] as $table) {
            // 1. Lebarkan enum dulu supaya value lama & baru sama-sama valid sementara
            DB::statement("ALTER TABLE {$table} MODIFY status_masuk ENUM('tepat_waktu', 'terlambat', 'hadir') NULL");

            // 2. Migrasi data lama
            DB::table($table)->where('status_masuk', 'tepat_waktu')->update(['status_masuk' => 'hadir']);

            // 3. Persempit enum ke nilai final
            DB::statement("ALTER TABLE {$table} MODIFY status_masuk ENUM('hadir', 'terlambat') NULL");

            // 4. Tambah kolom status_keluar
            DB::statement("ALTER TABLE {$table} ADD COLUMN status_keluar ENUM('pulang', 'bolos') NULL AFTER status_masuk");
        }
    }

    public function down(): void
    {
        foreach (['presensi_karyawan', 'presensi_jadwal'] as $table) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN status_keluar");
            DB::statement("ALTER TABLE {$table} MODIFY status_masuk ENUM('tepat_waktu', 'terlambat', 'hadir') NULL");
            DB::table($table)->where('status_masuk', 'hadir')->update(['status_masuk' => 'tepat_waktu']);
            DB::statement("ALTER TABLE {$table} MODIFY status_masuk ENUM('tepat_waktu', 'terlambat') NULL");
        }
    }
};