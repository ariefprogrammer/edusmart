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
        Schema::create('progress_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete();
            $table->foreignId('guru_id')->nullable()->constrained('guru')->nullOnDelete();
            $table->date('tanggal');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['kelas_id', 'siswa_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('progress_siswas');
    }
};
