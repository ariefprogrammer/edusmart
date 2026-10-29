<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi_siswa', function (Blueprint $table) {
            $table->text('keterangan_pengganti')->nullable()->after('keterangan');
        });

        Schema::table('progress_siswa', function (Blueprint $table) {
            $table->text('keterangan_pengganti')->nullable()->after('catatan');
        });
    }

    public function down(): void
    {
        Schema::table('progress_siswa', function (Blueprint $table) {
            $table->dropColumn('keterangan_pengganti');
        });

        Schema::table('presensi_siswa', function (Blueprint $table) {
            $table->dropColumn('keterangan_pengganti');
        });
    }
};