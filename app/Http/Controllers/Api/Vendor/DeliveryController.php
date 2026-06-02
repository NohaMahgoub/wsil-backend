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
    // Vendor confirms delivery → release money to driver (minus driver fee)
    public function confirm(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $delivery = $order->delivery;

        if (!$delivery || $delivery->status !== 'delivered') {
            return response()->json([
                'message' => 'لم يتم تسليم الطلب بعد.',
            ], 422);
        }

        DB::transaction(function () use ($order, $delivery) {
            // ── Get driver service fee ────────────────────────────
            $driverProfile    = $delivery->driver->driverProfile;
            $driverFee        = $driverProfile?->service_fee_percentage ?? 5.00;
            $deliveryPrice    = $delivery->delivery_price;
            $driverServiceFee = round($deliveryPrice * $driverFee / 100, 2);
            $driverEarnings   = round($deliveryPrice - $driverServiceFee, 2);

            // ── Credit driver wallet (minus platform fee) ─────────
            $delivery->driver->wallet->credit(
                amount:      $driverEarnings,
                description: "✅ أرباح توصيل WSL-{$order->id}",
                reference:   "DELIVERY-{$delivery->id}",
            );

            // ── Update delivery record ────────────────────────────
            $delivery->update([
                'status'               => 'completed',
                'confirmed_at'         => now(),
                'driver_fee_percentage'=> $driverFee,
                'driver_service_fee'   => $driverServiceFee,
                'driver_earnings'      => $driverEarnings,
            ]);

            $order->update(['status' => 'completed']);

            // ── Update driver rating ──────────────────────────────
            $this->updateDriverRating($delivery->driver_id);
        });

        $delivery->refresh();

        // ── Notify driver ─────────────────────────────────────────
        try {
            $driverEarnings = $delivery->driver_earnings;
            $notification   = new NotificationService();
            $notification->sendToUser(
                user:  $delivery->driver,
                title: '💰 تم تحرير أرباحك!',
                body:  "تم إضافة SDG {$driverEarnings} إلى محفظتك.",
                data:  ['order_id' => (string) $order->id, 'type' => 'payment_released'],
            );
        } catch (\Exception $e) {}

        return response()->json([
            'message'          => 'تم تأكيد التسليم. تم تحرير الأرباح للسائق.',
            'driver_earnings'  => $delivery->driver_earnings,
            'driver_service_fee' => $delivery->driver_service_fee,
        ]);
    }

    // ── Update driver average rating ──────────────────────────────
    private function updateDriverRating(int $driverId): void
    {
        $avg   = \App\Models\Review::where('reviewee_id', $driverId)
                    ->where('reviewee_role', 'driver')
                    ->avg('rating');
        $count = \App\Models\Review::where('reviewee_id', $driverId)
                    ->where('reviewee_role', 'driver')
                    ->count();

        $profile = \App\Models\DriverProfile::where('user_id', $driverId)->first();
        if ($profile) {
            $profile->update([
                'rating'        => round($avg ?? 0, 2),
                'total_reviews' => $count,
            ]);
        }
    }

    // Vendor raises a dispute
    public function dispute(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $delivery = $order->delivery;

        if (!$delivery || $delivery->status !== 'delivered') {
            return response()->json([
                'message' => 'يمكنك رفع نزاع فقط بعد تسليم الطلب.',
            ], 422);
        }

        if ($delivery->dispute) {
            return response()->json([
                'message' => 'يوجد نزاع مسبق لهذا التوصيل.',
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string|min:10',
        ]);

        DB::transaction(function () use ($order, $delivery, $request) {
            Dispute::create([
                'delivery_id' => $delivery->id,
                'raised_by'   => $request->user()->id,
                'reason'      => $request->reason,
                'status'      => 'open',
            ]);

            $delivery->update(['status' => 'disputed']);
            $order->update(['status' => 'disputed']);
        });

        // Notify driver
        try {
            $notification = new NotificationService();
            $notification->sendToUser(
                user:  $delivery->driver,
                title: '⚠️ تم رفع نزاع',
                body:  "رفع البائع نزاعاً على طلب WSL-{$order->id}. سيقوم المسؤول بمراجعته.",
                data:  ['order_id' => (string) $order->id, 'type' => 'dispute_raised'],
            );
        } catch (\Exception $e) {}

        return response()->json([
            'message' => 'تم رفع النزاع. سيقوم المسؤول بمراجعته.',
        ]);

    }

    // Vendor tracks driver live location
    public function track(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $delivery = $order->delivery;

        if (!$delivery) {
            return response()->json(['message' => 'لا يوجد توصيل نشط.'], 404);
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
            description: "↩ استرجاع مبلغ الطلب WSL-{$order->id}",
            reference:   "CANCEL-{$order->id}",
        );

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status'      => 'cancelled',
            'changed_by'  => $request->user()->id,
            'notes'       => 'البائع ألغى التوصيل قبل تحرك السائق',
        ]);

        // Reset all bids back to pending
        \App\Models\OrderBid::where('order_id', $order->id)
            ->whereIn('status', ['accepted', 'rejected'])
            ->update(['status' => 'pending']);

        $order->update(['status' => 'open']);
        $delivery->update(['status' => 'cancelled']);
    });
        
        try {
            $notification = new NotificationService();
            $notification->sendToUser(
                user:  $delivery->driver,
                title: '❌ تم إلغاء التوصيل',
                body:  "ألغى البائع طلب WSL-{$order->id} قبل تحركك. الطلب متاح مجدداً.",
                data:  ['order_id' => (string) $order->id, 'type' => 'delivery_cancelled'],
            );
        } catch (\Exception $e) {}

        return response()->json([
            'message' => 'تم إلغاء التوصيل. تم استرجاع المبلغ لمحفظتك.',
        ]);
    }
}