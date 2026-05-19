<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    protected $fillable = [
        'phone',
        'otp',
        'verified',
        'verified_at',
        'expires_at',
    ];

    protected $casts = [
        'verified'    => 'boolean',
        'verified_at' => 'datetime',
        'expires_at'  => 'datetime',
    ];

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }
}