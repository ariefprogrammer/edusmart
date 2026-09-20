<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['presensi_siswa', 'progress_siswa'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->foreignId('periode_id')->nullable()->after('kelas_id')->constrained('periodes')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['presensi_siswa', 'progress_siswa'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropConstrainedForeignId('periode_id');
            });
        }
    }
};