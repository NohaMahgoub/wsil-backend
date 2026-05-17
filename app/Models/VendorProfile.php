<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VendorProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'phone', 'is_verified','photo_path','service_fee_percentage',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}