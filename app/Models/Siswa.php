<?php

namespace App\Models;

use App\Models\Concerns\FormatsIndonesianPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\Concerns\HasUuid;
use App\Models\KelasSiswa;
use Illuminate\Support\Facades\DB;

class Siswa extends Model
{
    use SoftDeletes, FormatsIndonesianPhoneNumber, HasUuid;

    protected $table = 'siswa';

    public const STATUS_NONAKTIF = ['cuti', 'keluar'];

    protected $fillable = [
        'uuid', 'cabang_id', 'wali_murid_id', 'nama', 'tanggal_lahir',
        'alamat', 'telepon', 'status', 'tanggal_daftar', 'is_active',
    ];

    protected $casts = [
        'tanggal_lahir' => 'date',
        'tanggal_daftar' => 'date',
    ];

    protected function phoneNumberFields(): array
    {
        return ['telepon'];
    }

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    public function waliMurid(): BelongsTo
    {
        return $this->belongsTo(WaliMurid::class, 'wali_murid_id');
    }

    public function statusRiwayat(): HasMany
    {
        return $this->hasMany(SiswaStatusRiwayat::class);
    }

    public function kelasList(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'kelas_siswa')
            ->using(KelasSiswa::class)
            ->withPivot(['id', 'tanggal_gabung', 'is_active'])
            ->withTimestamps();
    }

    public function kelasAktif()
    {
        return $this->kelasList()->wherePivot('is_active', true);
    }

    protected static function booted(): void
    {
        static::created(function (Siswa $siswa) {
            $siswa->statusRiwayat()->create([
                'status_lama' => null,
                'status_baru' => $siswa->status,
                'tanggal_perubahan' => now(),
                'diubah_oleh' => auth()->id(),
            ]);
        });

        static::updating(function (Siswa $siswa) {
            if ($siswa->isDirty('status')) {
                $siswa->statusRiwayat()->create([
                    'status_lama' => $siswa->getOriginal('status'),
                    'status_baru' => $siswa->status,
                    'tanggal_perubahan' => now(),
                    'diubah_oleh' => auth()->id(),
                ]);
            }
        });

        static::updated(function (Siswa $siswa) {
            $dimatikan = $siswa->wasChanged('is_active') && ! $siswa->is_active;
            $statusNonaktif = $siswa->wasChanged('status')
                && in_array($siswa->status, self::STATUS_NONAKTIF, true);

            if ($dimatikan || $statusNonaktif) {
                $alasan = $statusNonaktif
                    ? "Status siswa diubah menjadi {$siswa->status}"
                    : 'Siswa dinonaktifkan';

                $siswa->nonaktifkanSemuaProgramAktif($alasan . ' (otomatis)');
            }
        });
    }

    /** Siswa dianggap tidak aktif jika toggle mati atau status cuti/keluar. */
    public function sedangTidakAktif(): bool
    {
        return ! $this->is_active || in_array($this->status, self::STATUS_NONAKTIF, true);
    }

    /** Nonaktifkan semua program aktif lewat model pivot, supaya riwayat tercatat. */
    public function nonaktifkanSemuaProgramAktif(?string $keterangan = null): int
    {
        return DB::transaction(function () use ($keterangan) {
            $pivots = KelasSiswa::query()
                ->where('siswa_id', $this->getKey())
                ->where('is_active', true)
                ->get();

            foreach ($pivots as $pivot) {
                $pivot->tanggalPerubahan = today()->toDateString();
                $pivot->keteranganPerubahan = $keterangan;
                $pivot->is_active = false;
                $pivot->save(); // event updating di KelasSiswa mencatat ke kelas_siswa_detail
            }

            return $pivots->count();
        });
    }

}