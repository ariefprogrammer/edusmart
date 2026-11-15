<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private array $tabel = ['presensi_siswa', 'progress_siswa'];

    public function up(): void
    {
        foreach ($this->tabel as $nama) {
            Schema::table($nama, function (Blueprint $table) {
                $table->foreignId('jadwal_id')->nullable()->after('kelas_id')
                    ->constrained('jadwal')->nullOnDelete();
                $table->index('kelas_id');
            });

            Schema::table($nama, function (Blueprint $table) use ($nama) {
                $table->dropUnique($nama . '_kelas_id_siswa_id_tanggal_unique');
                $table->unique(['jadwal_id', 'siswa_id', 'tanggal'], $nama . '_jadwal_id_siswa_id_tanggal_unique');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tabel as $nama) {
            Schema::table($nama, function (Blueprint $table) use ($nama) {
                $table->dropUnique($nama . '_jadwal_id_siswa_id_tanggal_unique');
            });

            Schema::table($nama, function (Blueprint $table) use ($nama) {
                $table->unique(['kelas_id', 'siswa_id', 'tanggal'], $nama . '_kelas_id_siswa_id_tanggal_unique');
                $table->dropForeign(['jadwal_id']);
                $table->dropColumn('jadwal_id');
                $table->dropIndex(['kelas_id']);
            });
        }
    }
};