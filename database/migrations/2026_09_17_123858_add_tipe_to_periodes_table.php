<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodes', function (Blueprint $table) {
            $table->enum('tipe', ['bulan', 'semester'])->default('bulan')->after('nama_periode');
        });
    }

    public function down(): void
    {
        Schema::table('periodes', function (Blueprint $table) {
            $table->dropColumn('tipe');
        });
    }
};
