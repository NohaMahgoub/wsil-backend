<?php

namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\OrderBid;
use Illuminate\Http\Request;
use App\Events\DriverLocationUpdated;

class DeliveryOrderController extends Controller
{
    // Driver browses all open orders
    public function index(Request $request)
    {
        $query = DeliveryOrder::where('status', 'open')
        ->with(['vendor:id,name,phone', 'bids' => function ($q) use ($request) {
            $q->where('driver_id', $request->user()->id);
        }])
        ->withCount(['bids as bids_count' => function ($q) {
            $q->where('status', 'pending');
        }]);

        // Filter by distance if driver sends location
        if ($request->filled('lat') && $request->filled('lng') && $request->filled('radius')) {
            $lat    = $request->lat;
            $lng    = $request->lng;
            $radius = $request->get('radius', 100); // km, default 100

            $query->whereNotNull('pickup_lat')
                ->whereNotNull('pickup_lng')
                ->selectRaw("delivery_orders.*, ( 6371 * acos( cos( radians(?) ) * cos( radians(pickup_lat) ) * cos( radians(pickup_lng) - radians(?) ) + sin( radians(?) ) * sin( radians(pickup_lat) ) ) ) AS distance", [$lat, $lng, $lat])
                ->having('distance', '<=', $radius)
                ->orderBy('distance');
        } else {
            $query->latest();
        }

        $orders = $query->paginate(15);

        return response()->json($orders);
    }

    // Driver views a single order detail
    public function show(DeliveryOrder $order)
    {
        if ($order->status !== 'open') {
            return response()->json([
                'message' => 'هذا الطلب لم يعد متاحاً.',
            ], 422);
        }

        $order->load('vendor:id,name,phone');

        return response()->json($order);
    }

    public function updateLocation(Request $request, DeliveryOrder $order)
    {
        $request->validate([
            'lat' => 'required|numeric',
            'lng' => 'required|numeric',
        ]);

        $delivery = $order->delivery;

        if (!$delivery || $delivery->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        // Allow location updates for all active statuses
        $activeStatuses = ['in_progress', 'picking_up', 'in_transit'];
        if (!in_array($order->status, $activeStatuses)) {
            return response()->json([
                'message' => 'التوصيل غير نشط.',
            ], 422);
        }

        // Save to database
        $delivery->update([
            'driver_lat' => $request->lat,
            'driver_lng' => $request->lng,
        ]);

        // Broadcast to vendor in real time
        try {
            broadcast(new DriverLocationUpdated(
                orderId:  $order->id,
                driverId: $request->user()->id,
                lat:      $request->lat,
                lng:      $request->lng,
            ))->toOthers();
        } catch (\Exception $e) {
            // Broadcasting failed silently
        }

        return response()->json([
            'message' => 'تم تحديث الموقع.',
            'lat'     => $request->lat,
            'lng'     => $request->lng,
        ]);
    }
}