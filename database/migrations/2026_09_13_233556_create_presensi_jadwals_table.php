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
        Schema::create('presensi_jadwal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jadwal_id')->constrained('jadwal')->cascadeOnDelete();
            $table->foreignId('guru_id')->constrained('guru')->cascadeOnDelete();
            $table->date('tanggal');

            $table->dateTime('check_in')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_in_accuracy')->nullable();

            $table->dateTime('check_out')->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_out_accuracy')->nullable();

            $table->enum('status_masuk', ['tepat_waktu', 'terlambat'])->nullable();
            $table->timestamps();

            $table->unique(['jadwal_id', 'tanggal']); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi_jadwals');
    }
};
