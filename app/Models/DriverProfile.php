<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverProfile extends Model
{
    protected $fillable = [
        'user_id', 'phone', 
        'vehicle_type',
        'vehicle_model',
        'vehicle_plate',
        'national_id',
        'rating', 'total_reviews', 'is_verified',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}