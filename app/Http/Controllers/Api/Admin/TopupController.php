<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\TopupRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\NotificationService;

class TopupController extends Controller
{
    // List all top-up requests (filterable by status)
   public function index(Request $request)
    {
        $topups = TopupRequest::with('vendor:id,name,phone')
            ->latest()
            ->paginate(20);

        $topups->getCollection()->transform(function ($topup) {
            $topup->receipt_url = $topup->receipt_path
                ? asset('storage/' . $topup->receipt_path)
                : null;
            return $topup;
        });

        return response()->json($topups);
    }

    // Approve a top-up → credit the vendor wallet
    public function approve(Request $request, TopupRequest $topup)
    {
        if ($topup->status !== 'pending') {
            return response()->json([
                'message' => 'This request has already been reviewed.',
            ], 422);
        }

        DB::transaction(function () use ($topup, $request) {
            // Update request status
            $topup->update([
                'status'      => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // Credit the vendor wallet
            $topup->vendor->wallet->credit(
                amount:      $topup->amount,
                description: 'تم اعتماد شحن المحفظة',
                reference:   $topup->transfer_reference,
            );
        });

        try {
            $notification = new NotificationService();
            $notification->sendToUser(
                user:  $topup->vendor,
                title: '💳 تم شحن محفظتك',
                body:  "تمت إضافة SDG {$topup->amount} إلى محفظتك بنجاح.",
                data:  ['type' => 'topup_approved'],
            );
        } catch (\Exception $e) {
            // Silent fail
        }

        return response()->json([
            'message'         => 'تم اعتماد الشحن وإضافة الرصيد للمحفظة.',
            'credited_amount' => $topup->amount,
            'new_balance'     => $topup->vendor->wallet->fresh()->balance,
        ]);
    }

    // Reject a top-up request
    public function reject(Request $request, TopupRequest $topup)
    {
        $request->validate([
            'reason' => 'required|string',
        ]);

        if ($topup->status !== 'pending') {
            return response()->json([
                'message' => 'تمت مراجعة هذا الطلب مسبقاً.',
            ], 422);
        }

        $topup->update([
            'status'           => 'rejected',
            'reviewed_by'      => $request->user()->id,
            'reviewed_at'      => now(),
            'rejection_reason' => $request->reason,
        ]);

        // ← Notify vendor of rejection
        try {
            $notification = new NotificationService();
            $notification->sendToUser(
                user:  $topup->vendor,
                title: '❌ تم رفض طلب الشحن',
                body:  "تم رفض طلب شحن محفظتك بمبلغ SDG {$topup->amount}. السبب: {$request->reason}",
                data:  ['type' => 'topup_rejected'],
            );
        } catch (\Exception $e) {
            // Silent fail
        }

        return response()->json([
            'message' => 'تم رفض طلب الشحن.',
        ]);
    }

    // View receipt of a top-up
    public function receipt(TopupRequest $topup)
    {
        $media = $topup->getFirstMedia('receipts');

        if (! $media) {
            return response()->json(['message' => 'No receipt found.'], 404);
        }

        return response()->json([
            'receipt_url' => $media->getFullUrl(),
        ]);
    }
}