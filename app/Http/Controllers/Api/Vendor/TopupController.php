<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\TopupRequest;
use Illuminate\Http\Request;

class TopupController extends Controller
{
    // Vendor submits a top-up request
 public function store(Request $request)
    {
        $request->validate([
            'amount'             => 'required|numeric|min:50',
            'bank_name'          => 'required|string',
            'transfer_reference' => 'required|string',
            'receipt'            => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ]);

        // Store receipt file
        $receiptPath = null;
        if ($request->hasFile('receipt')) {
            $receiptPath = $request->file('receipt')
                ->store('receipts', 'public');
        }

        $topup = TopupRequest::create([
            'vendor_id'          => $request->user()->id,
            'amount'             => $request->amount,
            'bank_name'          => $request->bank_name,
            'transfer_reference' => $request->transfer_reference,
            'receipt_path'       => $receiptPath,
            'status'             => 'pending',
        ]);

        return response()->json([
            'message' => 'تم إرسال طلب الشحن. في انتظار موافقة المسؤول.',
            'topup'   => $topup,
        ], 201);
    }

    // Vendor views their own top-up history
    public function index(Request $request)
    {
        $topups = TopupRequest::where('vendor_id', $request->user()->id)
            ->latest()
            ->paginate(15);

        return response()->json($topups);
    }
}