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

        // Save request to DB (for rate limiting only)
        PhoneVerification::create([
            'phone'      => $phone,
            'otp'        => '000000', // placeholder — Nabda generates the real OTP
            'verified'   => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Format phone and send via Nabda
        $formattedPhone = self::formatPhone($phone);
        return self::sendViaNabda($formattedPhone);
    }

    // ── Verify OTP ────────────────────────────────────────────────
    public static function verify(string $phone, string $otp): bool
    {
        // Check local DB — is there a pending request?
        $record = PhoneVerification::where('phone', $phone)
            ->where('verified', false)
            ->latest()
            ->first();

        if (!$record) return false;
        if ($record->isExpired()) return false;

        // Verify with Nabda
        $formattedPhone = self::formatPhone($phone);
        $token = config('services.nabda.token');

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ])
                ->post('https://api.nabdaotp.com/api/v1/messages/otp/verify', [
                    'to'  => $formattedPhone,
                    'otp' => $otp,
                ]);

            Log::info('Nabda verify response', [
                'status' => $response->status(),
                'body'   => $response->json(),
            ]);

            if (!$response->successful()) {
                return false;
            }
        } catch (\Exception $e) {
            Log::error('Nabda Verify Exception: ' . $e->getMessage());
            return false;
        }

        // Mark as verified in local DB
        $record->update([
            'verified'    => true,
            'verified_at' => now(),
        ]);

        return true;
    }

    // ── Check if phone is verified ────────────────────────────────
    public static function isVerified(string $phone): bool
    {
        return PhoneVerification::where('phone', $phone)
            ->where('verified', true)
            ->where('verified_at', '>=', now()->subMinutes(30))
            ->exists();
    }

    // ── Format phone number ───────────────────────────────────────
    public static function formatPhone(string $phone): string
    {
        // Remove all non-digits
        $phone = preg_replace('/\D/', '', $phone);

        // Already has KSA code
        if (str_starts_with($phone, '966')) {
            return '+' . $phone;
        }

        // Already has Sudan code
        if (str_starts_with($phone, '249')) {
            return '+' . $phone;
        }

        // Exception: KSA test number
        if ($phone === '562924276') {
            return '+966' . $phone;
        }

        // Default: Sudan
        $phone = ltrim($phone, '0');
        return '+249' . $phone;
    }

    // ── Send via Nabda ────────────────────────────────────────────
    private static function sendViaNabda(string $phone): bool
    {
        $token = config('services.nabda.token');

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ])
                ->post('https://api.nabdaotp.com/api/v1/messages/otp/send', [
                    'to' => $phone,
                ]);

            Log::info('Nabda send response', [
                'status' => $response->status(),
                'body'   => $response->json(),
                'phone'  => $phone,
            ]);

            if (!$response->successful()) {
                Log::error('Nabda OTP Error', [
                    'status'   => $response->status(),
                    'response' => $response->json(),
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