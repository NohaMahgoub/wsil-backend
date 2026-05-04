<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\DeliveryStatusLog;
use App\Models\Dispute;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    // Vendor confirms delivery → release money to driver
    public function confirm(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $delivery = $order->delivery;

        if (! $delivery || $delivery->status !== 'delivered') {
            return response()->json([
                'message' => 'This delivery has not been marked as delivered yet.',
            ], 422);
        }

        DB::transaction(function () use ($order, $delivery) {
            // Release money to driver wallet
            $delivery->driver->wallet->credit(
                amount:      $delivery->delivery_price,
                description: "دفعة مقابل التوصيل #{$order->id}",
                reference:   "DELIVERY-{$delivery->id}",
            );

            // Update statuses
            $delivery->update([
                'status'       => 'completed',
                'confirmed_at' => now(),
            ]);
            $order->update(['status' => 'completed']);
        });

        $notification = new NotificationService();
        $notification->sendToUser(
            user:  $delivery->driver,
            title: '💰 Payment Released!',
            body:  "Your payment of SAR {$delivery->delivery_price} has been released to your wallet.",
            data:  ['order_id' => (string) $order->id, 'type' => 'payment_released'],
        );
                return response()->json([
            'message' => 'تم تأكيد التسليم. تم تحرير المبلغ للسائق.',
            'amount_released'  => $delivery->delivery_price,
        ]);
    }

    // Vendor raises a dispute
    public function dispute(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $delivery = $order->delivery;

        if (! $delivery || $delivery->status !== 'delivered') {
            return response()->json([
                'message' => 'You can only dispute a delivered order.',
            ], 422);
        }

        // Check if dispute already exists
        if ($delivery->dispute) {
            return response()->json([
                'message' => 'A dispute already exists for this delivery.',
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        DB::transaction(function () use ($order, $delivery, $request) {
            // Create dispute
            Dispute::create([
                'delivery_id' => $delivery->id,
                'raised_by'   => $request->user()->id,
                'reason'      => $request->reason,
                'status'      => 'open',
            ]);

            // Freeze the delivery
            $delivery->update(['status' => 'disputed']);
            $order->update(['status' => 'disputed']);
        });

        return response()->json([
            'message' => 'Dispute raised. Admin will review and resolve it.',
        ]);
    }

    // Vendor tracks driver live location
    public function track(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $delivery = $order->delivery;

        if (! $delivery) {
            return response()->json(['message' => 'No active delivery found.'], 404);
        }

        return response()->json([
            'driver_lat' => $delivery->driver_lat,
            'driver_lng' => $delivery->driver_lng,
            'status'     => $delivery->status,
            'driver'     => [
                'name'    => $delivery->driver->name,
                'vehicle' => $delivery->driver->driverProfile->vehicle_type,
                'plate'   => $delivery->driver->driverProfile->vehicle_plate,
            ],
        ]);
    }

    // Vendor cancels if driver takes too long
    public function cancelPickup(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if ($order->status !== 'assigned') {
            return response()->json([
                'message' => 'لا يمكن الإلغاء بعد تحرك السائق.',
            ], 422);
        }

        $delivery = $order->delivery;

        DB::transaction(function () use ($order, $delivery, $request) {
            // Refund vendor
            $request->user()->wallet->credit(
                amount:      $delivery->total_charged,
                description: "استرجاع مبلغ الطلب #{$order->id} — إلغاء التوصيل",
                reference:   "CANCEL-{$order->id}",
            );

            // Log cancellation
            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id,
                'status'      => 'cancelled',
                'changed_by'  => $request->user()->id,
                'notes'       => 'البائع ألغى التوصيل قبل تحرك السائق',
            ]);

            // Reset order to open
            $order->update(['status' => 'open']);
            $delivery->update(['status' => 'cancelled']);
        });

        return response()->json([
            'message' => 'تم إلغاء التوصيل. تم استرجاع المبلغ لمحفظتك.',
        ]);
}
}