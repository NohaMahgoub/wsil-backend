<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Delivery extends Model
{
    protected $fillable = [
        'order_id', 'driver_id', 'vendor_id',
        'delivery_price', 'service_fee',
        'vendor_fee_percentage', 'total_charged',
        'driver_fee_percentage', 'driver_service_fee', 'driver_earnings',
        'driver_lat', 'driver_lng', 'status',
        'delivered_at', 'confirmed_at', 'auto_release_at',
        'picking_up_at', 'in_transit_at',
    ];

    protected $casts = [
        'delivered_at'    => 'datetime',
        'confirmed_at'    => 'datetime',
        'auto_release_at' => 'datetime',
        'picking_up_at'   => 'datetime',  
        'in_transit_at'   => 'datetime',
    ];

    public function statusLogs()
    {
        return $this->hasMany(DeliveryStatusLog::class);
    }

    public function order()
    {
        return $this->belongsTo(DeliveryOrder::class, 'order_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function dispute()
    {
        return $this->hasOne(Dispute::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    // Check if review window is still open
    public function canBeReviewed(): bool
    {
        if ($this->status !== 'completed') return false;
        if (! $this->confirmed_at) return false;

        return now()->isBefore($this->confirmed_at->addHours(48));
    }
}