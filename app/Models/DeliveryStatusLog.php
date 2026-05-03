<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DeliveryStatusLog extends Model
{
    protected $fillable = [
        'delivery_id',
        'status',
        'changed_by',
        'notes',
    ];

    public function delivery()
    {
        return $this->belongsTo(Delivery::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    public function statusAr(): string
    {
        return match($this->status) {
            'assigned'    => 'تم التعيين',
            'picking_up'  => 'في الطريق للاستلام',
            'in_transit'  => 'في الطريق للتسليم',
            'delivered'   => 'تم التسليم',
            'completed'   => 'مكتمل',
            'cancelled'   => 'ملغي',
            default       => $this->status,
        };
    }
}