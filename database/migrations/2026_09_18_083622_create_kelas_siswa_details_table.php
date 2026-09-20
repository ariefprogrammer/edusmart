<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('kelas_siswa_detail', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelas_siswa_id')->constrained('kelas_siswa')->cascadeOnDelete();
            $table->enum('status', ['aktif', 'nonaktif']);
            $table->date('tanggal');
            $table->text('keterangan')->nullable();
            $table->foreignId('diubah_oleh')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas_siswa_details');
    }
};
