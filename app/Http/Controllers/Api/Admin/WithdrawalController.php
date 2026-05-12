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
                'message' => 'تمت معالجة هذا الطلب مسبقاً.',
            ], 422);
        }

        $request->validate([
            'transaction_id'    => 'required|string',
            'transaction_proof' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'transaction_id.required' => 'يرجى إدخال رقم العملية.',
        ]);

        // Store proof file
        $proofPath = null;
        if ($request->hasFile('transaction_proof')) {
            $proofPath = $request->file('transaction_proof')
                ->store('withdrawal_proofs', 'public');
        }

        DB::transaction(function () use ($withdrawal, $request, $proofPath) {
            $withdrawal->update([
                'status'            => 'approved',
                'reviewed_by'       => $request->user()->id,
                'reviewed_at'       => now(),
                'transaction_id'    => $request->transaction_id,
                'transaction_proof' => $proofPath,
            ]);
        });

        // Notify driver
        try {
            $notification = new \App\Services\NotificationService();
            $notification->sendToUser(
                user:  $withdrawal->driver,
                title: '💰 تم تحويل أرباحك!',
                body:  "تم تحويل SDG {$withdrawal->amount} إلى حسابك. رقم العملية: {$request->transaction_id}",
                data:  ['type' => 'withdrawal_approved'],
            );
        } catch (\Exception $e) {}

        return response()->json([
            'message' => 'تم اعتماد طلب السحب وإشعار السائق.',
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