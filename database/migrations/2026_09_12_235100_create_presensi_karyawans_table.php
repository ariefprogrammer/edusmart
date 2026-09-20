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
        Schema::create('presensi_karyawan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cabang_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('tanggal');

            $table->dateTime('check_in')->nullable();
            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_in_accuracy')->nullable();
            $table->string('check_in_foto')->nullable();

            $table->dateTime('check_out')->nullable();
            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->unsignedInteger('check_out_accuracy')->nullable();
            $table->string('check_out_foto')->nullable();

            $table->enum('status_masuk', ['tepat_waktu', 'terlambat'])->nullable();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'tanggal']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('presensi_karyawans');
    }
};
