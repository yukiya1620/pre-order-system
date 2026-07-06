<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsVerification extends Model
{
    // updated_atカラムが無いため、Eloquentにその存在を教えない
    const UPDATED_AT = null;

    protected $fillable = [
        'phone_number',
        'code',
        'expires_at',
        'verified_at',
        'attempt_count',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }
}
