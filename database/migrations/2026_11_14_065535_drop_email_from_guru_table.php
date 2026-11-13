<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            // Jika di langkah 3 kolom email punya unique/index, aktifkan salah satu:
            // $table->dropUnique(['email']);
            // $table->dropIndex(['email']);

            $table->dropColumn('email');
        });
    }

    public function down(): void
    {
        Schema::table('guru', function (Blueprint $table) {
            $table->string('email')->nullable()->after('telepon');
        });

        // Kembalikan data dari tabel users
        DB::statement('
            UPDATE guru
            JOIN users ON users.id = guru.user_id
            SET guru.email = users.email
        ');
    }
};