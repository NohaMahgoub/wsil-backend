<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppOtpService;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    // Send OTP
    public function send(Request $request)
    {
        $request->validate([
            'phone' => 'required|string|min:9|max:15',
        ]);

        $sent = WhatsAppOtpService::send($request->phone);

        if (!$sent) {
            return response()->json([
                'message' => 'فشل الإرسال أو تجاوزت الحد المسموح (3 محاولات كل 10 دقائق).',
            ], 429);
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق عبر واتساب ✅',
        ]);
    }

    // Verify OTP
    public function verify(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'otp'   => 'required|string|size:6',
        ]);

        $valid = WhatsAppOtpService::verify($request->phone, $request->otp);

        if (!$valid) {
            return response()->json([
                'message' => 'رمز التحقق غير صحيح أو منتهي الصلاحية.',
            ], 422);
        }

        return response()->json([
            'message'  => 'تم التحقق من الرقم بنجاح ✅',
            'verified' => true,
        ]);
    }
}