<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use App\Services\WhatsAppOtpService;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'            => 'required|string|max:255',
            'phone'           => [
                'required',
                'string',
                'regex:/^0\d{9}$/',
                \Illuminate\Validation\Rule::unique('users', 'phone')->where(function ($query) {
                    // Block re-registration only for pending or approved accounts
                    return $query->whereIn('approval_status', ['pending', 'approved']);
                }),
            ],
            'email'           => 'nullable|email|unique:users,email',
            'password'        => 'required|string|min:6|confirmed',
            'role'            => 'required|in:vendor,driver',
            'vehicle_type'    => 'required_if:role,driver|nullable|string',
            'vehicle_model'   => 'nullable|string',
            'vehicle_plate'   => 'nullable|string',
            'national_id'        => 'nullable|string', 
            'national_id_photo'  => 'required_if:role,driver|nullable|image|mimes:jpg,jpeg,png|max:5120',
            'photo'              => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            'vehicle_license'    => 'nullable|image|max:5120',
        ], [
            'name.required'            => 'يرجى إدخال الاسم الكامل.',
            'phone.required'           => 'يرجى إدخال رقم الهاتف.',
            'phone.regex'              => 'رقم الهاتف يجب أن يكون 10 أرقام ويبدأ بـ 0.',
            'phone.unique'             => 'رقم الهاتف مسجل مسبقاً.',
            'password.min'             => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
            'password.confirmed'       => 'كلمة المرور غير متطابقة.',
            'role.required'            => 'يرجى تحديد نوع الحساب.',
            'vehicle_type.required_if' => 'يرجى اختيار نوع المركبة.',
            'national_id_photo.required_if'  => 'يرجى إدخال صورة الرقم الوطني.',
            'photo.required'           => 'يرجى إدخال صورة شخصية.',
        ]);

        // Check phone was verified (only if enabled from settings)
        $phoneVerificationEnabled = \App\Models\AppSetting::get('phone_verification', 'false') === 'true';

        if ($phoneVerificationEnabled && !WhatsAppOtpService::isVerified($request->phone)) {
            return response()->json([
                'message' => 'يجب التحقق من رقم الهاتف عبر واتساب أولاً.',
            ], 422);
        }

        User::where('phone', $request->phone)
            ->where('approval_status', 'rejected')
            ->delete();

        $user = User::create([
            'name'            => $request->name,
            'phone'           => $request->phone,
            'email'           => $request->email,
            'national_id'     => $request->national_id,
            'approval_status' => $request->role === 'driver' ? 'pending' : 'approved',
            'password'        => bcrypt($request->password),
            'role'            => $request->role,
        ]);

        // Assign role
        $user->assignRole($request->role);

        // Create wallet
        $user->wallet()->create(['balance' => 0]);

        // Create profile
        if ($request->role === 'vendor') {

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('vendor_photos', 'public');
            }

            $user->vendorProfile()->create([
                'photo_path' => $photoPath,
            ]);

        } else {

            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('driver_photos', 'public');
            }

            $licensePath = null;
            if ($request->hasFile('vehicle_license')) {
                $licensePath = $request->file('vehicle_license')
                    ->store('vehicle_licenses', 'public');
            }

            $nationalIdPhotoPath = null;
            if ($request->hasFile('national_id_photo')) {
                $nationalIdPhotoPath = $request->file('national_id_photo')
                    ->store('national_id_photos', 'public');
            }

            $user->driverProfile()->create([
                'vehicle_type'            => $request->vehicle_type,
                'vehicle_model'           => $request->vehicle_model,
                'vehicle_plate'           => $request->vehicle_plate,
                'national_id'             => $request->national_id,
                'national_id_photo_path'  => $nationalIdPhotoPath,
                'photo_path'              => $photoPath,
                'vehicle_license_path'    => $licensePath,
            ]);
        }
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم إنشاء الحساب بنجاح',
            'token'   => $token,
            'role'    => $user->role,
            'user'    => $user,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'phone'    => 'required_without:email|string|nullable',
            'email'    => 'required_without:phone|email|nullable',
            'password' => 'required|string',
        ]);

        // Find user by phone or email
        $user = $request->phone
            ? User::where('phone', $request->phone)->first()
            : User::where('email', $request->email)->first();

        if (!$user || !\Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'بيانات الدخول غير صحيحة',
            ], 401);
        }

        // Block unapproved drivers
        if ($user->role === 'driver' && $user->approval_status !== 'approved') {
            $message = $user->approval_status === 'rejected'
                ? 'تم رفض طلبك. يرجى التواصل مع الدعم.'
                : 'حسابك قيد المراجعة. سيتم إشعارك عند الموافقة.';

            return response()->json([
                'message'         => $message,
                'approval_status' => $user->approval_status,
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'message' => 'تم تسجيل الدخول بنجاح',
            'token'   => $token,
            'role'    => $user->role,
            'user'    => $user,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully',
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load('driverProfile', 'vendorProfile');

        return response()->json([
            'user' => $user,
            'role' => $user->getRoleNames()->first(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'photo' => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
        ]);

        if ($request->filled('name')) {
            $user->update(['name' => $request->name]);
        }

        if ($request->hasFile('photo')) {
            $path = $request->file('photo')->store(
                $user->role === 'driver' ? 'driver_photos' : 'vendor_photos',
                'public'
            );

            if ($user->role === 'driver') {
                $user->driverProfile()->update(['photo_path' => $path]);
            } else {
                $user->vendorProfile()->update(['photo_path' => $path]);
            }
        }

        $user->load('driverProfile', 'vendorProfile');

        return response()->json([
            'message' => 'تم تحديث الملف الشخصي بنجاح',
            'user'    => $user,
        ]);
    }

    // Step 1: Request OTP
    public function resetRequest(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $user = User::where('phone', $request->phone)->first();
        if (!$user) {
            return response()->json([
                'message' => 'رقم الهاتف غير مسجل في المنصة.',
            ], 404);
        }

        $sent = WhatsAppOtpService::send($request->phone);
        if (!$sent) {
            return response()->json([
                'message' => 'فشل إرسال رمز التحقق. حاول مرة أخرى.',
            ], 429);
        }

        return response()->json([
            'message' => 'تم إرسال رمز التحقق عبر واتساب ✅',
        ]);
    }

    // Step 2: Verify OTP
    public function resetVerify(Request $request)
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
            'message'  => 'تم التحقق بنجاح ✅',
            'verified' => true,
        ]);
    }

    // Step 3: Reset password
    public function resetPassword(Request $request)
    {
        $request->validate([
            'phone'    => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);

        $verified = WhatsAppOtpService::isVerified($request->phone);
        if (!$verified) {
            return response()->json([
                'message' => 'انتهت صلاحية رمز التحقق. أعد المحاولة.',
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();
        if (!$user) {
            return response()->json(['message' => 'المستخدم غير موجود.'], 404);
        }

        $user->update(['password' => bcrypt($request->password)]);

        return response()->json([
            'message' => 'تم تغيير كلمة المرور بنجاح ✅',
        ]);
    }
}