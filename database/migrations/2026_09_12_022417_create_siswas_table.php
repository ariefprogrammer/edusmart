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
        Schema::create('siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $table->foreignId('wali_murid_id')->unique()->constrained('wali_murid')->cascadeOnDelete();
            $table->string('nama');
            $table->date('tanggal_lahir')->nullable();
            $table->text('alamat')->nullable();
            $table->string('telepon')->nullable();
            $table->enum('status', ['aktif', 'cuti', 'keluar'])->default('aktif');
            $table->date('tanggal_daftar');
            $table->boolean('is_active')->default(true);
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswas');
    }
};
