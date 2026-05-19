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

        // Save to DB with real OTP
        PhoneVerification::create([
            'phone'      => $phone,
            'otp'        => $otp, // ← save real OTP for local verification
            'verified'   => false,
            'expires_at' => now()->addMinutes(10),
        ]);

        // Format phone and send via Nabda
        $whatsappPhone = self::formatPhone($phone);
        return self::sendViaNabda($whatsappPhone, $otp);
            
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

        // Remove leading zero
        $phone = ltrim($phone, '0');

        // Exception: KSA test number (after removing leading zero)
        if ($phone === '562924276') {
            return '+966' . $phone;
        }

        // Default: Sudan
        return '+249' . $phone;
    }

    // ── Send via Nabda ────────────────────────────────────────────
     private static function sendViaNabda(string $phone, string $otp): bool
    {
        $token = config('services.nabda.token');

        $message = "🔐 *وصل | Wsil*\n\n"
                . "رمز التحقق الخاص بك:\n\n"
                . "*{$otp}*\n\n"
                . "⏱ صالح لمدة 10 دقائق\n"
                . "🔒 لا تشارك هذا الرمز مع أحد";

        try {
            $response = Http::timeout(30)
                ->withHeaders([
                    'Authorization' => $token,
                    'Content-Type'  => 'application/json',
                ])
                ->post('https://api.nabdaotp.com/api/v1/messages/send', [
                    'phone'   => $phone,
                    'message' => $message,
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

    private static function getJwtToken(): ?string
    {
        // Cache token for 50 minutes (JWT usually expires in 1 hour)
        return \Illuminate\Support\Facades\Cache::remember('nabda_jwt', 3000, function () {
            // Step 1: Login
            $loginResponse = Http::post('https://api.nabdaotp.com/api/v1/auth/login', [
                'email'    => config('services.nabda.email'),
                'password' => config('services.nabda.password'),
            ]);

            if (!$loginResponse->successful()) {
                Log::error('Nabda login failed', ['body' => $loginResponse->json()]);
                return null;
            }

            $tempToken = $loginResponse->json('data.token') 
                    ?? $loginResponse->json('token')
                    ?? $loginResponse->json('data.accessToken');

            // Step 2: Select instance
            $instanceResponse = Http::withToken($tempToken)
                ->post('https://api.nabdaotp.com/api/v1/auth/select-instance', [
                    'instanceId' => config('services.nabda.instance_id'),
                ]);

            if (!$instanceResponse->successful()) {
                Log::error('Nabda select instance failed', ['body' => $instanceResponse->json()]);
                return null;
            }

            $jwt = $instanceResponse->json('data.token')
                ?? $instanceResponse->json('token')
                ?? $instanceResponse->json('data.accessToken');

            Log::info('Nabda JWT obtained successfully');
            return $jwt;
        });
    }
}