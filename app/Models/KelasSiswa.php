<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KelasSiswa extends Pivot
{
    protected $table = 'kelas_siswa';

    public $incrementing = true;

    protected $fillable = ['kelas_id', 'siswa_id', 'tanggal_gabung', 'is_active'];

    public ?string $tanggalPerubahan = null;
    public ?string $keteranganPerubahan = null;

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function riwayat(): HasMany
    {
        return $this->hasMany(KelasSiswaDetail::class, 'kelas_siswa_id')->orderBy('tanggal');
    }

    protected static function booted(): void
    {
        static::created(function (KelasSiswa $pivot) {
            $pivot->riwayat()->create([
                'status' => $pivot->is_active ? 'aktif' : 'nonaktif',
                'tanggal' => $pivot->tanggalPerubahan ?? $pivot->tanggal_gabung ?? now(),
                'keterangan' => $pivot->keteranganPerubahan,
                'diubah_oleh' => auth()->id(),
            ]);
        });

        static::updating(function (KelasSiswa $pivot) {
            if ($pivot->isDirty('is_active')) {
                $pivot->riwayat()->create([
                    'status' => $pivot->is_active ? 'aktif' : 'nonaktif',
                    'tanggal' => $pivot->tanggalPerubahan ?? now(),
                    'keterangan' => $pivot->keteranganPerubahan,
                    'diubah_oleh' => auth()->id(),
                ]);
            }
        });
    }
}