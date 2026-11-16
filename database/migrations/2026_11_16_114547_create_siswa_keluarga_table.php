<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswa_keluarga', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')
                  ->unique()
                  ->constrained('siswa')
                  ->onDelete('cascade');

            // Data Ayah
            $table->string('nama_ayah')->nullable();
            $table->string('nik_ayah', 20)->nullable();
            $table->string('tempat_lahir_ayah', 100)->nullable();
            $table->date('tanggal_lahir_ayah')->nullable();
            $table->string('pendidikan_ayah', 50)->nullable();
            $table->string('pekerjaan_ayah', 100)->nullable();
            $table->string('penghasilan_ayah', 100)->nullable();
            $table->string('media_sosial_ayah')->nullable();

            // Data Ibu
            $table->string('nama_ibu')->nullable();
            $table->string('nik_ibu', 20)->nullable();
            $table->string('tempat_lahir_ibu', 100)->nullable();
            $table->date('tanggal_lahir_ibu')->nullable();
            $table->string('pendidikan_ibu', 50)->nullable();
            $table->string('pekerjaan_ibu', 100)->nullable();
            $table->string('penghasilan_ibu', 100)->nullable();
            $table->string('media_sosial_ibu')->nullable();

            // Data Wali
            $table->string('nama_wali')->nullable();
            $table->string('nik_wali', 20)->nullable();
            $table->year('tahun_lahir_wali')->nullable();
            $table->text('alamat_wali')->nullable();
            $table->string('telepon_wali', 30)->nullable();
            $table->string('seluler_wali', 30)->nullable();
            $table->string('pendidikan_wali', 50)->nullable();
            $table->string('pekerjaan_wali', 100)->nullable();
            $table->string('penghasilan_wali', 100)->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswa_keluarga');
    }
};