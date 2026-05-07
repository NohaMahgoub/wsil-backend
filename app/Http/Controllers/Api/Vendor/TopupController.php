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
            'amount'             => 'required|numeric|min:10000',
            'bank_name'          => 'required|string',
            'transfer_reference' => 'required|string|unique:topup_requests,transfer_reference',
            'receipt'            => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:5120',
        ], [
            'amount.required'             => 'يرجى إدخال المبلغ.',
            'amount.numeric'              => 'يجب أن يكون المبلغ رقماً.',
            'amount.min'                  => 'الحد الأدنى للشحن هو 10000 SDG.',
            'bank_name.required'          => 'يرجى إدخال اسم البنك.',
            'transfer_reference.required' => 'يرجى إدخال رقم الحوالة.',
            'transfer_reference.unique'   => 'رقم الحوالة مستخدم مسبقاً. يرجى التحقق من الرقم.',
            'receipt.mimes'               => 'يجب أن يكون الإيصال صورة أو ملف PDF.',
            'receipt.max'                 => 'حجم الإيصال يجب أن لا يتجاوز 5 ميغابايت.',
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