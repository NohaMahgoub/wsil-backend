<?php
namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\OrderBid;
use Illuminate\Http\Request;
use App\Services\NotificationService;

class BidController extends Controller
{
    // Driver places a bid on an order
    public function store(Request $request, DeliveryOrder $order)
    {
        // Order must be open
        if ($order->status !== 'open') {
            return response()->json([
                'message' => 'This order is no longer accepting bids.',
            ], 422);
        }

        $request->validate([
            'price' => 'required|numeric|min:1',
        ]);

        // Driver can only bid once per order
        $existingBid = OrderBid::where('order_id', $order->id)
            ->where('driver_id', $request->user()->id)
            ->first();

        if ($existingBid) {
            return response()->json([
                'message' => 'لقد قمت مسبقا بتقديم عرض سعر للتوصيل',
                'bid'     => $existingBid,
            ], 422);
        }

        $bid = OrderBid::create([
            'order_id'  => $order->id,
            'driver_id' => $request->user()->id,
            'price'     => $request->price,
            'status'    => 'pending',
        ]);

        //Notify vendor
        try {
            $notification = new NotificationService();
            $notification->sendToUser(
                user:  $order->vendor,
                title: '📦 عرض جديد على طلبك',
                body:  "قدّم {$request->user()->name} عرضاً بسعر SDG {$request->price} على طلبك.",
                data:  ['order_id' => (string) $order->id, 'type' => 'new_bid'],
            );
        } catch (\Exception $e) {
            // Silent fail
        }
        return response()->json([
            'message' => 'Bid placed successfully.',
            'bid'     => $bid,
        ], 201);
    }

    // Driver updates their bid (only if still pending)
    public function update(Request $request, DeliveryOrder $order, OrderBid $bid)
    {
        if ($bid->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($bid->status !== 'pending') {
            return response()->json([
                'message' => 'You can only edit a pending bid.',
            ], 422);
        }

        $request->validate([
            'price' => 'required|numeric|min:1',
        ]);

        $bid->update(['price' => $request->price]);

        return response()->json([
            'message' => 'Bid updated successfully.',
            'bid'     => $bid,
        ]);
    }

    // Driver cancels their bid
    public function destroy(Request $request, DeliveryOrder $order, OrderBid $bid)
    {
        if ($bid->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        if ($bid->status !== 'pending') {
            return response()->json([
                'message' => 'You can only cancel a pending bid.',
            ], 422);
        }

        $bid->delete();

        return response()->json(['message' => 'Bid cancelled successfully.']);
    }

    // Driver views all their bids
    public function index(Request $request)
    {
        $bids = OrderBid::where('driver_id', $request->user()->id)
            ->with('order')
            ->latest()
            ->paginate(15);

        return response()->json($bids);
    }
}