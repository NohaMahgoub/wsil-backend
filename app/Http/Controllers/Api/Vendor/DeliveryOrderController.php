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
        ]);

        return response()->json($order);
    }

    // Vendor cancels an order (only if still open)
    public function cancel(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($order->status !== 'open') {
            return response()->json([
                'message' => 'Only open orders can be cancelled.',
            ], 422);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Order cancelled successfully.']);
    }
}
