<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryOrder extends Model
{
    protected $fillable = [
        'vendor_id', 'product_name', 'product_description',
        'weight_kg', 'pickup_address', 'pickup_lat', 'pickup_lng',
        'dropoff_address', 'dropoff_lat', 'dropoff_lng',
        'preferred_date', 'status',
    ];

    public function vendor()
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function bids()
    {
        return $this->hasMany(OrderBid::class, 'order_id');
    }

    public function delivery()
    {
        return $this->hasOne(Delivery::class, 'order_id')
            ->whereNotIn('status', ['cancelled'])
            ->latestOfMany();
    }

    public function activeDelivery()
    {
        return $this->hasOne(Delivery::class, 'order_id')
            ->whereNotIn('status', ['cancelled'])
            ->latest();
    }
}