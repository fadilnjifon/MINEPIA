<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampostOtp extends Model
{
    protected $fillable = [
        'email', 
        'code_hash', 
        'purpose', 
        'expires_at', 
        'used'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used' => 'boolean',
    ];
}
