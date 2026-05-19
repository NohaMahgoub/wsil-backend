<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\WhatsAppOtpService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

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
    public static function verify(string $phone, string $otp): bool
    {
        // 1️⃣ Check local DB first (rate limit + expiry)
        $record = PhoneVerification::where('phone', $phone)
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$record) return false;
        if ($record->isExpired()) return false;

        // 2️⃣ Verify with Nabda
        $formattedPhone = self::formatPhone($phone);
        $token = config('services.nabda.token');

        try {
            $response = Http::withToken($token)
                ->post('https://api.nabdaotp.com/api/v1/messages/otp/verify', [
                    'to'  => $formattedPhone,
                    'otp' => $otp,
                ]);

            if (!$response->successful()) {
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Nabda Verify Exception: ' . $e->getMessage());
            return false;
        }

        // 3️⃣ Mark as verified in local DB
        $record->update([
            'otp'         => $otp,
            'verified'    => true,
            'verified_at' => now(),
        ]);

        return true;
    }
}