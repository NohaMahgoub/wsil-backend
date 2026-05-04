<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    // Get wallet balance + recent transactions
    public function show(Request $request)
    {
        $wallet = $request->user()
            ->wallet()
            ->with(['transactions' => function ($q) {
                $q->latest()->limit(20);
            }])
            ->first();

        return response()->json([
            'balance'      => $wallet->balance,
            'transactions' => $wallet->transactions,
        ]);
    }
}