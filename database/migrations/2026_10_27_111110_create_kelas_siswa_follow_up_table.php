<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_siswa_follow_up', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_siswa_id')->constrained('kelas_siswa')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('hasil', 30);
            $table->text('keterangan')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kelas_siswa_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_siswa_follow_up');
    }
};