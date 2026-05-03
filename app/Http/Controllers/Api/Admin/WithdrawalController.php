<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    public function index(Request $request)
    {
        $withdrawals = WithdrawalRequest::with('driver:id,name,email,phone')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->latest()
            ->paginate(20);

        return response()->json($withdrawals);
    }

    public function approve(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'تمت مراجعة هذا الطلب مسبقاً.',
            ], 422);
        }

        DB::transaction(function () use ($withdrawal, $request) {
            $withdrawal->update([
                'status'      => 'approved',
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            // Add confirmation transaction so driver sees it
            $withdrawal->driver->wallet->credit(
                amount:      0,
                description: "✅ تم اعتماد سحب #{$withdrawal->id} — تم التحويل البنكي",
                reference:   "WITHDRAWAL-APPROVED-{$withdrawal->id}",
            );
        });

        return response()->json([
            'message' => 'تمت الموافقة على السحب. تم تأكيد التحويل البنكي.',
            'amount'  => $withdrawal->amount,
            'driver'  => $withdrawal->driver->name,
        ]);
    }   

    public function reject(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'تمت مراجعة هذا الطلب مسبقاً.',
            ], 422);
        }

        $request->validate([
            'reason' => 'required|string',
        ]);

        DB::transaction(function () use ($withdrawal, $request) {
            $withdrawal->driver->wallet->credit(
                amount:      $withdrawal->amount,
                description: "طلب سحب #{$withdrawal->id} مرفوض — تم إعادة المبلغ",
                reference:   "WITHDRAWAL-REJECT-{$withdrawal->id}",
            );

            $withdrawal->update([
                'status'           => 'rejected',
                'reviewed_by'      => $request->user()->id,
                'reviewed_at'      => now(),
                'rejection_reason' => $request->reason,
            ]);
        });

        return response()->json([
            'message' => 'تم رفض طلب السحب. تم إعادة المبلغ لمحفظة السائق.',
        ]);
    }
}