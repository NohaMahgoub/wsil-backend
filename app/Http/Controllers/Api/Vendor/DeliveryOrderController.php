<?php

namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Delivery;
use App\Models\OrderBid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryOrderController extends Controller
{
    // List vendor's own orders
    public function index(Request $request)
    {
      $orders = DeliveryOrder::where('vendor_id', $request->user()->id)
        ->with([
            'bids' => function($q) {
                $q->orderBy('price', 'asc'); // ← cheapest first
            },
            'bids.driver:id,name,phone',
            'bids.driver.driverProfile',
        ])
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->latest()
        ->paginate(15);

        return response()->json($orders);
    }

    // Create a new delivery order
    public function store(Request $request)
    {
        $request->validate([
            'product_name'        => 'required|string|max:255',
            'product_description' => 'nullable|string',
            'weight_kg'           => 'nullable|numeric|min:0',
            'pickup_address'      => 'required|string',
            'pickup_lat'          => 'nullable|numeric',
            'pickup_lng'          => 'nullable|numeric',
            'dropoff_address'     => 'required|string',
            'dropoff_lat'         => 'nullable|numeric',
            'dropoff_lng'         => 'nullable|numeric',
            'preferred_date'      => 'nullable|date|after_or_equal:today',
            'receiver_phone' => [
                'nullable',
                'string',
                'min:9',
                'max:10',
                'regex:/^[0-9]+$/',
            ],], [
                'product_name.required'   => 'يرجى إدخال اسم المنتج.',
                'pickup_address.required' => 'يرجى إدخال عنوان الاستلام.',
                'dropoff_address.required'=> 'يرجى إدخال عنوان التسليم.',
                'receiver_phone.min'      => 'رقم هاتف المستلم يجب أن يكون 9 أرقام على الأقل.',
                'receiver_phone.max'      => 'رقم هاتف المستلم غير صحيح.',
                'receiver_phone.regex'    => 'رقم هاتف المستلم يجب أن يحتوي على أرقام فقط.',
            ]);

        $order = DeliveryOrder::create([
            ...$request->only([
                'product_name',
                'product_description',
                'weight_kg',
                'pickup_address',
                'pickup_lat',
                'pickup_lng',
                'dropoff_address',
                'dropoff_lat',
                'dropoff_lng',
                'preferred_date',
                'receiver_phone',
            ]),
            'vendor_id' => $request->user()->id,
            'status'    => 'open',
        ]);

        return response()->json([
            'message' => 'تم نشر الطلب. يمكن للسائقين تقديم عروضهم الآن.',
            'order'   => $order,
        ], 201);
    }

    // Show a single order with all bids
    public function show(Request $request, DeliveryOrder $order)
    {
        // Make sure vendor owns this order
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $order->load([
            'bids.driver:id,name,phone',
            'bids.driver.driverProfile',
            'delivery.driver:id,name,phone',
            'delivery.driver.driverProfile',
            'delivery.statusLogs.changedBy:id,name',
            'vendor.vendorProfile:user_id,service_fee_percentage'
        ]);

        return response()->json($order);
    }

    // Vendor cancels an order (only if still open)
    public function cancel(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if (!in_array($order->status, ['open', 'assigned', 'active'])) {
            return response()->json([
                'message' => 'لا يمكن إلغاء الطلب بعد أن يبدأ السائق التوصيل.',
            ], 422);
        }

        DB::transaction(function () use ($order, $request) {
            // If driver was assigned → refund vendor
            if (in_array($order->status, ['assigned', 'active'])) {
                $delivery = $order->delivery;
                if ($delivery) {
                    // Refund vendor
                    $order->vendor->wallet->credit(
                        amount:      $delivery->total_charged,
                        description: 'استرداد — إلغاء الطلب من البائع',
                        reference:   "VENDOR-CANCEL-{$order->id}",
                    );
                    // Cancel delivery
                    $delivery->update(['status' => 'cancelled']);
                }
            }

            // Cancel all bids
            $order->bids()->update(['status' => 'cancelled']);

            // Cancel order
            $order->update(['status' => 'cancelled']);
        });

        try {
            broadcast(new \App\Events\DeliveryStatusChanged(
                orderId: $order->id,
                status:  'cancelled',
                message: 'تم إلغاء الطلب من البائع.',
            ));
        } catch (\Exception $e) {}

        return response()->json([
            'message' => 'تم إلغاء الطلب بنجاح.',
        ]);
    }
}
