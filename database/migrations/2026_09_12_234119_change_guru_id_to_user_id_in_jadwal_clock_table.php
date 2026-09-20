<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('jadwal_clock', 'user_id')) {
            Schema::table('jadwal_clock', function (Blueprint $table) {
                $table->foreignId('user_id')->nullable()->after('guru_id')->constrained('users')->cascadeOnDelete();
            });

            DB::table('jadwal_clock')->orderBy('id')->each(function ($row) {
                $guru = DB::table('guru')->where('id', $row->guru_id)->first();

                if ($guru?->user_id) {
                    DB::table('jadwal_clock')->where('id', $row->id)->update(['user_id' => $guru->user_id]);
                }
            });

            DB::table('jadwal_clock')->whereNull('user_id')->delete();
        }

        Schema::table('jadwal_clock', function (Blueprint $table) {
            $table->dropForeign(['guru_id']);
            $table->dropUnique('jadwal_clock_guru_id_hari_unique');
            $table->dropColumn('guru_id');
        });

        Schema::table('jadwal_clock', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable(false)->change();
            $table->unique(['user_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_clock', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'hari']);
            $table->dropConstrainedForeignId('user_id');
        });
    }
};