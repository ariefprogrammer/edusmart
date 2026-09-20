<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('radius_presensi_meter')->default(100);
            $table->unsignedInteger('toleransi_keterlambatan_menit')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('cabangs', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude', 'radius_presensi_meter', 'toleransi_keterlambatan_menit']);
        });
    }
};
