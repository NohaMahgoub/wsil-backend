<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WithdrawalRequest extends Model
{
    protected $fillable = [
        'driver_id', 'amount', 'bank_name',
        'account_number', 'iban', 'status',
        'reviewed_by', 'reviewed_at', 'rejection_reason',
        'account_name',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}