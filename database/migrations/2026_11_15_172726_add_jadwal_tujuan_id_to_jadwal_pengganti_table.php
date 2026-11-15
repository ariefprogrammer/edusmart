<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_pengganti', function (Blueprint $table) {
            $table->foreignId('jadwal_tujuan_id')->nullable()->after('jadwal_id')
                ->constrained('jadwal')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pengganti', function (Blueprint $table) {
            $table->dropForeign(['jadwal_tujuan_id']);
            $table->dropColumn('jadwal_tujuan_id');
        });
    }
};