<?php
namespace App\Http\Controllers\Api\Driver;

use App\Events\DeliveryStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\DeliveryStatusLog;
use App\Models\OrderBid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryController extends Controller
{
    // ── Helper: get active delivery ───────────────────────────────
    private function getActiveDelivery(DeliveryOrder $order)
    {
        return Delivery::where('order_id', $order->id)
            ->whereNotIn('status', ['cancelled'])
            ->latest()
            ->first();
    }

    // Driver accepts the order
    public function accept(Request $request, DeliveryOrder $order)
    {
        $delivery = $this->getActiveDelivery($order);

        if (!$delivery) {
            return response()->json(['message' => 'لم يتم العثور على التوصيل.'], 404);
        }

        if ($delivery->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if ($order->status !== 'assigned') {
            return response()->json([
                'message' => 'هذا الطلب لا ينتظر قبول السائق.',
            ], 422);
        }

        DB::transaction(function () use ($order, $delivery) {
            $order->update(['status' => 'active']);
            $delivery->update(['status' => 'active']);
        });

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status'      => 'assigned',
            'changed_by'  => $request->user()->id,
            'notes'       => 'السائق قبل التوصيل',
        ]);

        try {
            broadcast(new DeliveryStatusChanged(
                orderId: $order->id,
                status:  'active',
                message: 'السائق قبل طلبك وفي الطريق إليك.',
            ));
        } catch (\Exception $e) {
            // Silent fail
        }

        return response()->json([
            'message' => 'تم قبول الطلب. يمكنك الآن البدء بالتوصيل.',
            'order'   => $order->fresh(),
        ]);
    }

    // Driver pressed "ابدأ التوصيل" — heading to vendor
    public function startPickup(Request $request, DeliveryOrder $order)
    {
        $delivery = $this->getActiveDelivery($order);

        if (!$delivery || $delivery->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if (!in_array($order->status, ['active', 'assigned'])) {
            return response()->json([
                'message' => 'لا يمكن تغيير الحالة الآن.',
            ], 422);
        }

        $order->update(['status' => 'picking_up']);
        $delivery->update([
            'status'        => 'picking_up',
            'picking_up_at' => now(),
        ]);

        // Auto-cancel all other active bids for this driver
        OrderBid::where('driver_id', $request->user()->id)
            ->where('order_id', '!=', $order->id)
            ->where('status', 'pending')
            ->update(['status' => 'cancelled']);

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status'      => 'picking_up',
            'changed_by'  => $request->user()->id,
            'notes'       => 'السائق في الطريق لمكان الاستلام',
        ]);

        try {
            broadcast(new DeliveryStatusChanged(
                orderId: $order->id,
                status:  'picking_up',
                message: 'السائق في الطريق إليك لاستلام الطلب.',
            ));
        } catch (\Exception $e) {
            // Silent fail
        }

        return response()->json([
            'message' => 'تم تحديث الحالة. في الطريق لمكان الاستلام.',
        ]);
    }

    // Driver pressed "استلمت الطلب" — now in transit
    public function startTransit(Request $request, DeliveryOrder $order)
    {
        $delivery = $this->getActiveDelivery($order);

        if (!$delivery || $delivery->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if ($order->status !== 'picking_up') {
            return response()->json([
                'message' => 'لا يمكن تغيير الحالة الآن.',
            ], 422);
        }

        // Generate delivery code
        $code = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        $order->update(['status' => 'in_transit']);
        $delivery->update([
            'status'                   => 'in_transit',
            'in_transit_at'            => now(),
            'delivery_code'            => $code,
            'delivery_code_expires_at' => now()->addHours(24),
        ]);

        // Send code to receiver via WhatsApp
        if ($order->receiver_phone) {
            try {
                $phone   = $order->receiver_phone;
                $message = "📦 *وصل | Wsil*\n\n"
                        . "طلبك في الطريق إليك!\n\n"
                        . "رمز استلام الطلب:\n\n"
                        . "*{$code}*\n\n"
                        . "أعطِ هذا الرمز للسائق عند وصوله.\n"
                        . "⏱ صالح لمدة 24 ساعة.";

                \App\Services\WhatsAppOtpService::sendMessage($phone, $message);
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send delivery code: ' . $e->getMessage());
            }
        }

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status'      => 'in_transit',
            'changed_by'  => $request->user()->id,
            'notes'       => 'السائق استلم الطلب وفي الطريق إلى موقع التسليم',
        ]);

        try {
            broadcast(new DeliveryStatusChanged(
                orderId: $order->id,
                status:  'in_transit',
                message: 'السائق استلم الطلب وفي الطريق إلى موقع التسليم.',
            ));
        } catch (\Exception $e) {
            // Silent fail
        }

        return response()->json([
            'message' => 'تم تحديث الحالة. في الطريق للتسليم.',
        ]);
    }

    // Driver confirms delivery using receiver's code
    public function confirmCode(Request $request, DeliveryOrder $order)
    {
        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        $delivery = $this->getActiveDelivery($order);

        if (!$delivery || $delivery->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if ($delivery->status !== 'in_transit') {
            return response()->json([
                'message' => 'لا يمكن تأكيد التسليم في هذه المرحلة.',
            ], 422);
        }

        // Verify code
        if ($delivery->delivery_code !== $request->code) {
            return response()->json([
                'message' => 'رمز التسليم غير صحيح.',
            ], 422);
        }

        // Check expiry
        if (now()->isAfter($delivery->delivery_code_expires_at)) {
            return response()->json([
                'message' => 'انتهت صلاحية رمز التسليم.',
            ], 422);
        }

        DB::transaction(function () use ($order, $delivery, $request) {
            $now = now();

            // ── Get driver fee ────────────────────────────────────
            $driverProfile    = $delivery->driver->driverProfile;
            $driverFee        = $driverProfile?->service_fee_percentage ?? 5.00;
            $deliveryPrice    = $delivery->delivery_price;
            $driverServiceFee = round($deliveryPrice * $driverFee / 100, 2);
            $driverEarnings   = round($deliveryPrice - $driverServiceFee, 2);

            // ── Credit driver ─────────────────────────────────────
            $delivery->driver->wallet->credit(
                amount:      $driverEarnings,
                description: "✅ أرباح توصيل WSL-{$order->id} (رمز التسليم)",
                reference:   "CODE-CONFIRM-{$delivery->id}",
            );

            // ── Update delivery ───────────────────────────────────
            $delivery->update([
                'status'               => 'completed',
                'delivered_at'         => $now,
                'confirmed_at'         => $now,
                'code_verified_at'     => $now,
                'driver_fee_percentage'=> $driverFee,
                'driver_service_fee'   => $driverServiceFee,
                'driver_earnings'      => $driverEarnings,
            ]);

            $order->update(['status' => 'completed']);

            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id,
                'status'      => 'completed',
                'changed_by'  => $request->user()->id,
                'notes'       => 'تم التأكيد برمز التسليم',
            ]);
        });

        // Notify vendor
        try {
            $notification = new \App\Services\NotificationService();
            $notification->sendToUser(
                user:  $order->vendor,
                title: '✅ تم تسليم طلبك!',
                body:  "تم تأكيد استلام طلب WSL-{$order->id} برمز التسليم.",
                data:  ['order_id' => (string) $order->id, 'type' => 'delivery_confirmed'],
            );
        } catch (\Exception $e) {}

        return response()->json([
            'message'         => 'تم تأكيد التسليم بنجاح! تم إضافة أرباحك.',
            'driver_earnings' => $delivery->fresh()->driver_earnings,
        ]);
    }

    // Driver marks the order as delivered
    public function markDelivered(Request $request, DeliveryOrder $order)
    {
        $delivery = $this->getActiveDelivery($order);

        if (!$delivery || $delivery->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if (!in_array($delivery->status, ['in_progress', 'in_transit'])) {
            return response()->json([
                'message' => 'لا يمكن تأكيد التسليم في هذه المرحلة.',
            ], 422);
        }

        DB::transaction(function () use ($order, $delivery) {
            $now = now();
            $delivery->update([
                'status'          => 'delivered',
                'delivered_at'    => $now,
                'auto_release_at' => $now->copy()->addHours(24),
            ]);
            $order->update(['status' => 'delivered']);
        });

        DeliveryStatusLog::create([
            'delivery_id' => $delivery->id,
            'status'      => 'delivered',
            'changed_by'  => $request->user()->id,
            'notes'       => 'السائق أكد التسليم',
        ]);

        try {
            broadcast(new DeliveryStatusChanged(
                orderId: $order->id,
                status:  'delivered',
                message: 'تم تسليم الطلب. يرجى تأكيد الاستلام.',
            ));
        } catch (\Exception $e) {
            // Silent fail
        }

        return response()->json([
            'message'         => 'تم تسليم الطلب. في انتظار تأكيد البائع.',
            'auto_release_at' => $delivery->fresh()->auto_release_at,
        ]);
    }

    // Driver updates live location
    public function updateLocation(Request $request, DeliveryOrder $order)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $delivery = $this->getActiveDelivery($order);

        if (!$delivery || $delivery->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if (!in_array($order->status, ['active', 'picking_up', 'in_transit'])) {
            return response()->json([
                'message' => 'التوصيل غير نشط.',
            ], 422);
        }

        $delivery->update([
            'driver_lat' => $request->lat,
            'driver_lng' => $request->lng,
        ]);

        return response()->json([
            'message' => 'تم تحديث الموقع.',
            'lat'     => $request->lat,
            'lng'     => $request->lng,
        ]);
    }

    // Driver views their active & past deliveries
    public function index(Request $request)
    {
        $deliveries = Delivery::where('driver_id', $request->user()->id)
            ->with(['order', 'vendor:id,name,phone'])
            ->whereNotIn('status', ['cancelled']) 
            ->latest()
            ->paginate(15);

        return response()->json($deliveries);
    }

    // Driver cancels delivery before pickup
    public function cancel(Request $request, DeliveryOrder $order)
    {
        $delivery = $this->getActiveDelivery($order);

        if (!$delivery || $delivery->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        // Only allow cancel before picking_up
        if (!in_array($order->status, ['assigned', 'active'])) {
            return response()->json([
                'message' => 'لا يمكن إلغاء التوصيل بعد استلام الطلب.',
            ], 422);
        }

        DB::transaction(function () use ($order, $delivery, $request) {
            // Cancel delivery
            $delivery->update(['status' => 'cancelled']);

            // Return order to open
            $order->update(['status' => 'open']);

            // Refund vendor
            $order->vendor->wallet->credit(
                amount:      $delivery->total_charged,
                description: 'إعادة رسوم التوصيل — إلغاء السائق',
                reference:   "DRIVER-CANCEL-{$order->id}",
            );

            // Cancel driver's bid
            OrderBid::where('order_id', $order->id)
                ->where('driver_id', $request->user()->id)
                ->update(['status' => 'cancelled']);

            DeliveryStatusLog::create([
                'delivery_id' => $delivery->id,
                'status'      => 'cancelled',
                'changed_by'  => $request->user()->id,
                'notes'       => 'السائق ألغى التوصيل',
            ]);
        });

        try {
            broadcast(new DeliveryStatusChanged(
                orderId: $order->id,
                status:  'open',
                message: 'ألغى السائق التوصيل. الطلب متاح مجدداً.',
            ));
        } catch (\Exception $e) {}

        return response()->json([
            'message' => 'تم إلغاء التوصيل. تم إعادة المبلغ للبائع.',
        ]);
    }
}