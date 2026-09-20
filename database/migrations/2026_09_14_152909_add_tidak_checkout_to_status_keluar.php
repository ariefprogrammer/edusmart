<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['presensi_karyawan', 'presensi_jadwal'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY status_keluar ENUM('pulang', 'bolos', 'tidak_checkout') NULL");
        }
    }

    public function down(): void
    {
        foreach (['presensi_karyawan', 'presensi_jadwal'] as $table) {
            DB::statement("ALTER TABLE {$table} MODIFY status_keluar ENUM('pulang', 'bolos') NULL");
        }
    }
};