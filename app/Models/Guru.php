<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\Concerns\FormatsIndonesianPhoneNumber;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Guru extends Model
{
    use FormatsIndonesianPhoneNumber, SoftDeletes;

    protected $table = 'guru';

    protected $fillable = [
        'cabang_id', 'nama', 'telepon', 'email', 'pendidikan', 'is_active',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }

    protected function phoneNumberFields(): array
    {
        return ['telepon'];
    }

    public function jadwal(): HasMany
    {
        return $this->hasMany(Jadwal::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jadwalClock(): HasMany
    {
        return $this->hasMany(JadwalClock::class);
    }

    protected static function booted(): void
    {
        static::deleting(function (Guru $guru) {
            $guru->user?->delete();
        });
    }
}