<?php

namespace App\Models;

use App\Models\Concerns\FormatsIndonesianPhoneNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\Concerns\HasUuid;

class WaliMurid extends Model
{
    use FormatsIndonesianPhoneNumber, HasUuid;

    protected $table = 'wali_murid';

    protected $fillable = ['uuid', 'nama', 'telepon', 'email', 'password', 'is_active'];

    protected $hidden = ['password'];

    protected function phoneNumberFields(): array
    {
        return ['telepon'];
    }

    public function siswa(): HasOne
    {
        return $this->hasOne(Siswa::class);
    }
}