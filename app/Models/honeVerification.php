<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhoneVerification extends Model
{
    protected $fillable = [
        'phone', 'otp', 'verified', 'expires_at', 'verified_at',
    ];

    protected $casts = [
        'verified'    => 'boolean',
        'expires_at'  => 'datetime',
        'verified_at' => 'datetime',
    ];

    public function isExpired(): bool
    {
        return now()->isAfter($this->expires_at);
    }

    public function isValid(): bool
    {
        return !$this->verified && !$this->isExpired();
    }
}