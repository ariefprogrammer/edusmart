<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Periode extends Model
{
    use SoftDeletes;

    protected $fillable = ['cabang_id', 'nama_periode', 'tipe', 'tanggal_mulai', 'tanggal_selesai'];

    protected $casts = [
        'tanggal_mulai' => 'date',
        'tanggal_selesai' => 'date',
    ];

    public function cabang(): BelongsTo
    {
        return $this->belongsTo(Cabang::class);
    }
}