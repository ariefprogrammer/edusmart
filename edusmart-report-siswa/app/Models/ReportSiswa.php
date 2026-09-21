<?php

namespace App\Models;

use App\Models\Concerns\HasUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use LogicException;

/**
 * Snapshot report siswa. Isi (`data`) dibekukan saat dibuat: nama program, guru,
 * periode, dan angka turunan disalin sebagai teks, sehingga tidak ikut berubah
 * ketika data induknya diubah. Koreksi dilakukan dengan membuat report baru.
 */
class ReportSiswa extends Model
{
    use SoftDeletes, HasUuid;

    protected $table = 'report_siswa';

    public const BAGIAN = [
        'presensi' => 'Presensi',
        'progress' => 'Progress',
        'nilai' => 'Nilai',
    ];

    protected $fillable = [
        'uuid', 'batch_uuid', 'cabang_id', 'siswa_id', 'siswa_nama', 'cabang_nama', 'periode_ringkasan',
        'parameter', 'data', 'schema_version',
        'generated_by', 'generated_by_nama', 'generated_at', 'catatan',
    ];

    protected $casts = [
        'parameter' => 'array',
        'data' => 'array',
        'generated_at' => 'datetime',
    ];

    /** Kolom yang tidak boleh diubah setelah snapshot dibuat. */
    protected const IMMUTABLE = [
        'uuid', 'batch_uuid', 'siswa_nama', 'cabang_nama', 'periode_ringkasan',
        'parameter', 'data', 'schema_version',
        'generated_by_nama', 'generated_at',
    ];

    protected static function booted(): void
    {
        static::updating(function (ReportSiswa $report) {
            $diubah = array_intersect(self::IMMUTABLE, array_keys($report->getDirty()));

            if ($diubah !== []) {
                throw new LogicException(
                    'Snapshot report tidak boleh diubah ('.implode(', ', $diubah).'). Buat report baru.'
                );
            }
        });
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class)->withTrashed();
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
