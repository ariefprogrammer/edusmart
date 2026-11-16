<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('nisn', 20)->nullable()->after('nama');
            $table->enum('jenis_kelamin', ['L', 'P'])->nullable()->after('nisn');
            $table->string('kelas', 50)->nullable()->after('jenis_kelamin');
            $table->string('asal_sekolah', 150)->nullable()->after('kelas');
            $table->string('tempat_lahir', 100)->nullable()->after('asal_sekolah');
            
            $table->string('agama', 50)->nullable()->after('tanggal_lahir');
            $table->string('kewarganegaraan', 50)->default('Indonesia')->after('agama');
            
            $table->string('rt', 5)->nullable()->after('alamat');
            $table->string('rw', 5)->nullable()->after('rt');
            $table->string('kelurahan', 100)->nullable()->after('rw');
            $table->string('kode_pos', 10)->nullable()->after('kelurahan');
            
            $table->string('alat_transportasi', 100)->nullable()->after('kode_pos');
            $table->string('sumber_informasi', 100)->nullable()->after('alat_transportasi');
            $table->unsignedTinyInteger('anak_ke')->nullable()->after('sumber_informasi');
            $table->string('media_sosial')->nullable()->after('anak_ke');
            
            $table->string('telepon_rumah', 30)->nullable()->after('telepon');
            $table->date('tanggal_masuk')->nullable()->after('tanggal_daftar');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn([
                'nisn',
                'jenis_kelamin',
                'kelas',
                'asal_sekolah',
                'tempat_lahir',
                'agama',
                'kewarganegaraan',
                'rt',
                'rw',
                'kelurahan',
                'kode_pos',
                'alat_transportasi',
                'sumber_informasi',
                'anak_ke',
                'media_sosial',
                'telepon_rumah',
                'tanggal_masuk',
            ]);
        });
    }
};