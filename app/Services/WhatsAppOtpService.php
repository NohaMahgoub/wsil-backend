<?php
namespace App\Services;

use App\Models\PhoneVerification;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppOtpService
{
    // ── Send OTP ──────────────────────────────────────────────────
    public static function send(string $phone): bool
    {
        // Rate limit — max 3 attempts per 10 minutes
        $recentAttempts = PhoneVerification::where('phone', $phone)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->count();

        if ($recentAttempts >= 3) {
            return false;
        }

        // Generate 6-digit OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Save to DB
        PhoneVerification::create([
            'phone'      => $phone,
            'otp'        => $otp,
            'verified'   => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Format + Send
        $whatsappPhone = self::formatPhone($phone);
        return self::sendWhatsApp($whatsappPhone, $otp);
    }

    // ── Verify OTP ────────────────────────────────────────────────
    public static function verify(string $phone, string $otp): bool
    {
        $record = PhoneVerification::where('phone', $phone)
            ->where('otp', $otp)
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$record) return false;
        if ($record->isExpired()) return false;

        // Mark as verified
        $record->update([
            'verified'    => true,
            'verified_at' => now(),
        ]);

        return true;
    }

    // ── Check if phone is verified (for register) ─────────────────
    public static function isVerified(string $phone): bool
    {
        return PhoneVerification::where('phone', $phone)
            ->where('verified', true)
            ->where('verified_at', '>=', now()->subMinutes(30))
            ->exists();
    }

    // ── Format Sudanese phone ─────────────────────────────────────
    private static function formatPhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);
        $phone = ltrim($phone, '0');

        // Exception: KSA number for testing
        if ($phone === '562924276') {
            return '+966' . $phone;
        }

        // Already has country code
        if (str_starts_with($phone, '+249') || str_starts_with($phone, '+966')) {
            return $phone;
        }

        // Default: Sudan
        return '+249' . $phone;
    }

     // ── Send via Nabda OTP endpoint ───────────────────────────────
    private static function sendWhatsApp(string $phone, string $otp): bool
    {
        $token = config('services.nabda.token');

        try {
            $response = Http::withToken($token)
                ->post('https://api.nabdaotp.com/api/v1/messages/otp/send', [
                    'to' => $phone,
                ]);

            if (!$response->successful()) {
                Log::error('Nabda OTP Error', [
                    'status'   => $response->status(),
                    'response' => $response->json(),
                    'phone'    => $phone,
                ]);
                return false;
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Nabda OTP Exception: ' . $e->getMessage());
            return false;
        }
    }
}