<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DisputeController extends Controller
{
    // List all disputes
    public function index(Request $request)
    {
        $disputes = Dispute::with([
            'delivery.order',
            'delivery.driver:id,name',
            'delivery.vendor:id,name',
            'raisedBy:id,name',
            'resolvedBy:id,name',
        ])
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->latest()
        ->paginate(20);

        return response()->json($disputes);
    }

    // Show single dispute
    public function show(Dispute $dispute)
    {
        $dispute->load([
            'delivery.order',
            'delivery.driver:id,name',
            'delivery.vendor:id,name',
            'raisedBy:id,name',
            'resolvedBy:id,name',
        ]);

        return response()->json($dispute);
    }

    // Release money to driver
    public function releaseToDriver(Request $request, Dispute $dispute)
    {
        if ($dispute->status !== 'open') {
            return response()->json([
                'message' => 'This dispute has already been resolved.',
            ], 422);
        }

        $request->validate([
            'admin_note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($dispute, $request) {
            $delivery = $dispute->delivery;

            // Release money to driver wallet
            $delivery->driver->wallet->credit(
                amount:      $delivery->delivery_price,
                description: "نزاع #{$dispute->id} — تم تحرير المبلغ للسائق",
                reference:   "DISPUTE-{$dispute->id}",
            );

            // Update delivery and order
            $delivery->update([
                'status'       => 'completed',
                'confirmed_at' => now(),
            ]);
            $delivery->order->update(['status' => 'completed']);

            // Resolve dispute
            $dispute->update([
                'status'      => 'resolved',
                'resolution'  => 'released_to_driver',
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
                'admin_note'  => $request->admin_note,
            ]);
        });

        return response()->json([
            'message'    => 'Dispute resolved. Payment released to driver.',
            'resolution' => 'released_to_driver',
        ]);
    }

    // Refund money to vendor
    public function refundToVendor(Request $request, Dispute $dispute)
    {
        if ($dispute->status !== 'open') {
            return response()->json([
                'message' => 'This dispute has already been resolved.',
            ], 422);
        }

        $request->validate([
            'admin_note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($dispute, $request) {
            $delivery = $dispute->delivery;

            // Refund full amount back to vendor wallet
            $delivery->vendor->wallet->credit(
                amount:      $delivery->total_charged,
                description: "نزاع #{$dispute->id} — تم استرجاع المبلغ كاملاً للبائع",
                reference:   "DISPUTE-{$dispute->id}",
            );

            // Update delivery and order
            $delivery->update(['status' => 'completed']);
            $delivery->order->update(['status' => 'completed']);

            // Resolve dispute
            $dispute->update([
                'status'      => 'resolved',
                'resolution'  => 'refunded_to_vendor',
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
                'admin_note'  => $request->admin_note,
            ]);
        });

        return response()->json([
            'message'    => 'Dispute resolved. Full amount refunded to vendor.',
            'resolution' => 'refunded_to_vendor',
        ]);
    }

    // Split — partial payment to driver, partial refund to vendor
    public function split(Request $request, Dispute $dispute)
    {
        if ($dispute->status !== 'open') {
            return response()->json([
                'message' => 'This dispute has already been resolved.',
            ], 422);
        }

        $request->validate([
            'driver_amount' => 'required|numeric|min:0',
            'admin_note'    => 'nullable|string',
        ]);

        $delivery      = $dispute->delivery;
        $driverAmount  = $request->driver_amount;
        $vendorRefund  = $delivery->total_charged - $driverAmount;

        if ($driverAmount > $delivery->delivery_price) {
            return response()->json([
                'message' => 'Driver amount cannot exceed the delivery price.',
            ], 422);
        }

        DB::transaction(function () use ($dispute, $request, $delivery, $driverAmount, $vendorRefund) {
            // Pay driver their portion
            if ($driverAmount > 0) {
                $delivery->driver->wallet->credit(
                    amount:      $driverAmount,
                    description: "نزاع #{$dispute->id} — دفعة جزئية للسائق",
                    reference:   "DISPUTE-{$dispute->id}",
                );
            }

            // Refund vendor their portion
            if ($vendorRefund > 0) {
                $delivery->vendor->wallet->credit(
                    amount:      $vendorRefund,
                    description: "نزاع #{$dispute->id} — استرجاع جزئي للبائع",
                    reference:   "DISPUTE-{$dispute->id}",
                );
            }

            // Update delivery and order
            $delivery->update([
                'status'       => 'completed',
                'confirmed_at' => now(),
            ]);
            $delivery->order->update(['status' => 'completed']);

            // Resolve dispute
            $dispute->update([
                'status'      => 'resolved',
                'resolution'  => 'split',
                'resolved_by' => $request->user()->id,
                'resolved_at' => now(),
                'admin_note'  => $request->admin_note,
            ]);
        });

        return response()->json([
            'message'       => 'Dispute resolved with split payment.',
            'driver_amount' => $driverAmount,
            'vendor_refund' => $vendorRefund,
        ]);
    }
}