<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name'          => 'required|string|max:255',
            'phone'         => 'required|string|unique:users,phone',
            'email'         => 'nullable|email|unique:users,email',
            'password'      => 'required|string|min:6|confirmed',
            'role'          => 'required|in:vendor,driver',
            'vehicle_type'  => 'required_if:role,driver|nullable|string',
            'vehicle_model' => 'nullable|string',
            'vehicle_plate' => 'nullable|string',
            'national_id'   => 'required_if:role,driver|nullable|string',
            'photo'         => 'nullable|image|mimes:jpg,jpeg,png|max:2048',
            ], [
            'name.required'          => 'يرجى إدخال الاسم الكامل.',
            'phone.required'         => 'يرجى إدخال رقم الهاتف.',
            'phone.unique'           => 'رقم الهاتف مسجل مسبقاً.',
            'password.required'      => 'يرجى إدخال كلمة المرور.',
            'password.min'           => 'كلمة المرور يجب أن تكون 6 أحرف على الأقل.',
            'password.confirmed'     => 'كلمة المرور غير متطابقة.',
            'role.required'          => 'يرجى تحديد نوع الحساب.',
            'vehicle_type.required_if' => 'يرجى اختيار نوع المركبة.',
            'national_id.required_if'  => 'يرجى إدخال الرقم الوطني.',
            'photo.required'            => 'يرجى إدخال صورة شخصية.',
        ]);

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

        // Create profile — only once ✅
        if ($request->role === 'vendor') {
            $user->vendorProfile()->create([]);
        } else {
            
            $photoPath = null;
            if ($request->hasFile('photo')) {
                $photoPath = $request->file('photo')->store('driver_photos', 'public');
            }

            $user->driverProfile()->create([
                'vehicle_type'  => $request->vehicle_type,
                'vehicle_model' => $request->vehicle_model,
                'vehicle_plate' => $request->vehicle_plate,
                'photo_path'    => $photoPath,
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
        return response()->json([
            'user' => $request->user(),
            'role' => $request->user()->getRoleNames()->first(),
        ]);
    }
}