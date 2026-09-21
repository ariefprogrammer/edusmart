<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
        public function up(): void
    {
        Schema::table('report_siswa', function (Blueprint $table) {
            // Menandai report yang dibuat dalam satu kali "Buat Report" untuk banyak siswa.
            $table->uuid('batch_uuid')->nullable()->after('uuid')->index();
        });
    }

    public function down(): void
    {
        Schema::table('report_siswa', function (Blueprint $table) {
            $table->dropIndex(['batch_uuid']);
            $table->dropColumn('batch_uuid');
        });
    }
};
