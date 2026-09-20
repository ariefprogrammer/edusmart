<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wali_murid', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->after('id');
        });

        DB::table('wali_murid')->whereNull('uuid')->orderBy('id')->each(function ($row) {
            DB::table('wali_murid')->where('id', $row->id)->update([
                'uuid' => (string) Str::uuid(),
            ]);
        });

        Schema::table('wali_murid', function (Blueprint $table) {
            $table->uuid('uuid')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        Schema::table('wali_murid', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });
    }
};