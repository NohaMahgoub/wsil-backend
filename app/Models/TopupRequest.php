<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TopupRequest extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'vendor_id', 'amount', 'bank_name',
        'transfer_reference', 'receipt_path',
        'status', 'reviewed_by',
        'reviewed_at', 'rejection_reason',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}