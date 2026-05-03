<?php
use App\Models\DeliveryOrder;
use Illuminate\Support\Facades\Broadcast;

// Only the vendor of the order can listen
Broadcast::channel('order.{orderId}', function ($user, $orderId) {
    $order = DeliveryOrder::find($orderId);

    if (! $order) return false;

    // Vendor of the order can listen
    // Driver of the order can also listen
    return $user->id === $order->vendor_id
        || ($order->delivery && $user->id === $order->delivery->driver_id);
});