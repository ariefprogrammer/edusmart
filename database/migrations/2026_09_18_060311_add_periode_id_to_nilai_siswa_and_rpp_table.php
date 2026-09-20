<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('nilai_siswa', 'periode_id')) {
            Schema::table('nilai_siswa', function (Blueprint $table) {
                $table->foreignId('periode_id')->nullable()->after('kategori_nilai_id')->constrained('periodes')->nullOnDelete();
            });
        }

        if (! Schema::hasColumn('rpp', 'periode_id')) {
            Schema::table('rpp', function (Blueprint $table) {
                $table->foreignId('periode_id')->nullable()->after('kelas_id')->constrained('periodes')->nullOnDelete();
            });
        }

        // Lepas FK dulu baru index unique bisa di-drop — kalau constraint FK-nya
        // sudah tidak ada (percobaan sebelumnya sempat berhasil sebagian), skip saja.
        try {
            Schema::table('nilai_siswa', function (Blueprint $table) {
                $table->dropForeign(['kategori_nilai_id']);
            });
        } catch (\Throwable $e) {
            // FK sudah tidak ada, lanjut
        }

        if (Schema::hasColumn('nilai_siswa', 'kelas_id')) {
            try {
                Schema::table('nilai_siswa', function (Blueprint $table) {
                    $table->dropUnique('nilai_siswa_kelas_id_siswa_id_kategori_nilai_id_unique');
                });
            } catch (\Throwable $e) {
                // Index sudah tidak ada, lanjut
            }
        }

        Schema::table('nilai_siswa', function (Blueprint $table) {
            $table->foreign('kategori_nilai_id')->references('id')->on('kategori_nilai')->cascadeOnDelete();
        });

        if (! $this->uniqueExists('nilai_siswa', 'nilai_siswa_unik_per_periode')) {
            Schema::table('nilai_siswa', function (Blueprint $table) {
                $table->unique(['kelas_id', 'siswa_id', 'kategori_nilai_id', 'periode_id'], 'nilai_siswa_unik_per_periode');
            });
        }
    }

    public function down(): void
    {
        Schema::table('nilai_siswa', function (Blueprint $table) {
            $table->dropUnique('nilai_siswa_unik_per_periode');
            $table->dropConstrainedForeignId('periode_id');
            $table->unique(['kelas_id', 'siswa_id', 'kategori_nilai_id']);
        });

        Schema::table('rpp', function (Blueprint $table) {
            $table->dropConstrainedForeignId('periode_id');
        });
    }

    protected function uniqueExists(string $table, string $indexName): bool
    {
        $result = \Illuminate\Support\Facades\DB::select(
            "SHOW INDEX FROM {$table} WHERE Key_name = ?",
            [$indexName]
        );

        return count($result) > 0;
    }
};