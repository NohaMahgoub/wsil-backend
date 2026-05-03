<?php
namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WithdrawalController extends Controller
{
    // Driver views their withdrawal history
    public function index(Request $request)
    {
        $withdrawals = WithdrawalRequest::where('driver_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($withdrawals);
    }

    // Driver submits a withdrawal request
    public function store(Request $request)
    {
        $request->validate([
            'amount'         => 'required|numeric|min:50',
            'bank_name'      => 'required|string',
            'account_number' => 'required|string',
            'iban'           => 'required|string',
        ]);

        $wallet = $request->user()->wallet;

        // Check sufficient balance
        if ($wallet->balance < $request->amount) {
            return response()->json([
                'message'         => 'الرصيد غير كافٍ.',
                'current_balance' => $wallet->balance,
                'requested'       => $request->amount,
            ], 422);
        }

        // Check no pending withdrawal exists
        $pending = WithdrawalRequest::where('driver_id', $request->user()->id)
            ->where('status', 'pending')
            ->exists();

        if ($pending) {
            return response()->json([
                'message' => 'لديك طلب سحب معلق بالفعل.',
            ], 422);
        }

        DB::transaction(function () use ($request, $wallet) {
            // Debit wallet immediately (hold the amount)
            $wallet->debit(
                amount:      $request->amount,
                description: 'طلب سحب — في انتظار موافقة المسؤول',
                reference:   'WITHDRAWAL-PENDING',
            );

            // Create withdrawal request
            WithdrawalRequest::create([
                'driver_id'      => $request->user()->id,
                'amount'         => $request->amount,
                'bank_name'      => $request->bank_name,
                'account_number' => $request->account_number,
                'iban'           => $request->iban,
                'status'         => 'pending',
            ]);
        });

        return response()->json([
            'message' => 'تم إرسال طلب السحب. سيتم معالجته قريباً.',
            'balance' => $wallet->fresh()->balance,
        ], 201);
    }

    // Driver cancels a pending withdrawal
    public function cancel(Request $request, WithdrawalRequest $withdrawal)
    {
        if ($withdrawal->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'message' => 'يمكن إلغاء الطلبات المعلقة فقط.',
            ], 422);
        }

        DB::transaction(function () use ($withdrawal, $request) {
            // Refund the held amount back to wallet
            $request->user()->wallet->credit(
                amount:      $withdrawal->amount,
                description: 'تم إلغاء طلب السحب — تم إعادة المبلغ',
                reference:   "WITHDRAWAL-CANCEL-{$withdrawal->id}",
            );

            $withdrawal->update(['status' => 'rejected']);
        });

        return response()->json([
            'message' => 'تم إلغاء طلب السحب. تم إعادة المبلغ لمحفظتك.',
            'balance' => $request->user()->wallet->fresh()->balance,
        ]);
    }
}