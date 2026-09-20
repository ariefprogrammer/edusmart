<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->date('tanggal_gabung')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['kelas_id', 'siswa_id']);
        });

        // Migrasi data lama: siswa.kelas_id -> baris di kelas_siswa
        DB::table('siswa')->whereNotNull('kelas_id')->orderBy('id')->each(function ($row) {
            DB::table('kelas_siswa')->insert([
                'kelas_id' => $row->kelas_id,
                'siswa_id' => $row->id,
                'tanggal_gabung' => $row->tanggal_daftar,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        Schema::table('siswa', function (Blueprint $table) {
            $table->dropForeign(['kelas_id']);
            $table->dropColumn('kelas_id');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->foreignId('kelas_id')->nullable()->after('cabang_id')->constrained('kelas')->restrictOnDelete();
        });

        Schema::dropIfExists('kelas_siswa');
    }
};