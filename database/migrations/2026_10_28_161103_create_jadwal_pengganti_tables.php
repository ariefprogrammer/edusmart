<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pengganti', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained('cabangs')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('jadwal_id')->nullable()->constrained('jadwal')->nullOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->date('tanggal_seharusnya');
            $table->date('tanggal_pengganti');
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->text('alasan');
            $table->enum('status', ['terjadwal', 'selesai', 'dibatalkan'])->default('terjadwal');
            $table->timestamp('selesai_pada')->nullable();
            $table->foreignId('dibuat_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['guru_id', 'tanggal_pengganti']);
            $table->index(['cabang_id', 'tanggal_pengganti']);
        });

        Schema::create('jadwal_pengganti_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_pengganti_id')->constrained('jadwal_pengganti')->cascadeOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete();
            $table->timestamps();

            $table->unique(['jadwal_pengganti_id', 'siswa_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pengganti_siswa');
        Schema::dropIfExists('jadwal_pengganti');
    }
};