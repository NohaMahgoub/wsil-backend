<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\OrderBid;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class BidController extends Controller
{
    // Vendor accepts a specific bid
    public function accept(Request $request, DeliveryOrder $order, OrderBid $bid)
    {
        // Ownership check
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        // Order must still be open
        if ($order->status !== 'open') {
            return response()->json([
                'message' => 'This order is no longer accepting bids.',
            ], 422);
        }

        // Bid must belong to this order
        if ($bid->order_id !== $order->id) {
            return response()->json(['message' => 'Bid does not belong to this order.'], 422);
        }

         // Check if driver already has an active delivery
        $activeDelivery = Delivery::where('driver_id', $bid->driver_id)
            ->whereIn('status', ['in_progress', 'picking_up', 'in_transit'])
            ->exists();

        if ($activeDelivery) {
            return response()->json([
                'message' => 'هذا السائق لديه توصيل نشط حالياً. يرجى اختيار سائق آخر.',
            ], 422);
        }


        // ── Get vendor service fee ────────────────────────────────
        $vendorProfile  = $request->user()->vendorProfile;
        $feePercentage  = $vendorProfile?->service_fee_percentage ?? 5.00;
        $deliveryPrice  = $bid->price;
        $serviceFee     = round($deliveryPrice * $feePercentage / 100, 2);
        $totalCharged   = round($deliveryPrice + $serviceFee, 2);


        // Check vendor has enough balance
        $wallet = $request->user()->wallet;
        if ($wallet->balance < $totalCharged) {
            return response()->json([
                'message'        =>  'الرصيد غير كافٍ. قم بشحن المحفظة.',
                'required'       => $totalCharged,
                'current_balance'=> $wallet->balance,
            ], 422);
        }

        DB::transaction(function () use ($order, $bid, $request, $serviceFee, $totalCharged, $wallet, $feePercentage) {
            // Debit vendor wallet (escrow)
            $wallet->debit(
                amount:      $totalCharged,
                description: "حجز مبلغ التوصيل #" . $order->id,
                reference:   "ORDER-{$order->id}",
            );

            // Mark accepted bid
            $bid->update(['status' => 'accepted']);

            // Reject all other bids
            OrderBid::where('order_id', $order->id)
                ->where('id', '!=', $bid->id)
                ->update(['status' => 'rejected']);

            // Update order status
            $order->update(['status' => 'assigned']);

            // Create the delivery record
            Delivery::create([
                'order_id'              => $order->id,
                'driver_id'             => $bid->driver_id,
                'vendor_id'             => $order->vendor_id,
                'delivery_price'        => $bid->price,
                'service_fee'           => $serviceFee,
                'vendor_fee_percentage' => $feePercentage,  // ← add
                'total_charged'         => $totalCharged,
                'status'                => 'in_progress',
            ]);

          try {
                $notification = new NotificationService();
                $notification->sendToUser(
                    user:  $bid->driver,
                    title: '🎉 تم قبول عرضك!',
                    body:  'تم اختيارك للتوصيل. يرجى قبول الطلب والبدء بالتوصيل.',
                    data:  ['order_id' => (string) $order->id, 'type' => 'bid_accepted'],
                );
            } catch (\Exception $e) {
                // Silent fail
            }
        });

        return response()->json([
            'message' => 'تم اختيار السائق. المبلغ محجوز في الضمان.',
            'total_charged' => $totalCharged,
            'service_fee'   => $serviceFee,
        ]);
    }
}