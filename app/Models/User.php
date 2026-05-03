<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasApiTokens, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
        'national_id',        
        'approval_status',   
        'approved_at',    
        'approved_by',     
        'role',
        'is_suspended',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
    ];

    public function vendorProfile()
    {
        return $this->hasOne(VendorProfile::class);
    }

    public function driverProfile()
    {
        return $this->hasOne(DriverProfile::class);
    }

    public function wallet()
    {
        return $this->hasOne(Wallet::class);
    }

    public function deliveryOrders()
    {
        return $this->hasMany(DeliveryOrder::class, 'vendor_id');
    }

    public function bids()
    {
        return $this->hasMany(OrderBid::class, 'driver_id');
    }
}
