<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_pengganti_siswa', function (Blueprint $table) {
            $table->timestamp('presensi_disimpan_pada')->nullable()->after('siswa_id');
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pengganti_siswa', function (Blueprint $table) {
            $table->dropColumn('presensi_disimpan_pada');
        });
    }
};
