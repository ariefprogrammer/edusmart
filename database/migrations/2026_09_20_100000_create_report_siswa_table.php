<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_siswa', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();

            // FK nullable + nullOnDelete: snapshot harus tetap ada walau siswa/cabang/user dihapus.
            $table->foreignId('cabang_id')->nullable()->constrained('cabangs')->nullOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete();

            // Nama disalin sebagai teks supaya daftar arsip tidak butuh join.
            $table->string('siswa_nama');
            $table->string('cabang_nama')->nullable();
            $table->string('periode_ringkasan', 500)->nullable();

            // Filter saat report dibuat (untuk fitur "Buat Ulang") dan isi snapshot.
            $table->json('parameter');
            $table->json('data');
            $table->unsignedSmallInteger('schema_version')->default(1);

            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('generated_by_nama')->nullable();
            $table->timestamp('generated_at');

            $table->text('catatan')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['cabang_id', 'siswa_id']);
            $table->index('generated_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_siswa');
    }
};
