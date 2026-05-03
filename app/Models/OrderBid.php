<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderBid extends Model
{
    protected $fillable = [
        'order_id', 'driver_id', 'price', 'status',
    ];

    public function order()
    {
        return $this->belongsTo(DeliveryOrder::class, 'order_id');
    }

    public function driver()
    {
        return $this->belongsTo(User::class, 'driver_id');
    }
}