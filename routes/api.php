<?php

use App\Http\Controllers\Api\AuthController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Vendor\TopupController as VendorTopupController;
use App\Http\Controllers\Api\Vendor\WalletController;
use App\Http\Controllers\Api\Admin\TopupController as AdminTopupController;
use App\Http\Controllers\Api\Vendor\DeliveryOrderController as VendorOrderController;
use App\Http\Controllers\Api\Vendor\BidController as VendorBidController;
use App\Http\Controllers\Api\Driver\DeliveryOrderController as DriverOrderController;
use App\Http\Controllers\Api\Driver\BidController as DriverBidController;
use App\Http\Controllers\Api\Driver\DeliveryController as DriverDeliveryController;
use App\Http\Controllers\Api\Vendor\DeliveryController as VendorDeliveryController;
use App\Http\Controllers\Api\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Api\Admin\DisputeController as AdminDisputeController;
use App\Http\Controllers\Api\Vendor\DisputeController as VendorDisputeController;
use App\Http\Controllers\Api\Driver\WithdrawalController as DriverWithdrawalController;
use App\Http\Controllers\Api\Admin\WithdrawalController as AdminWithdrawalController;
use App\Http\Controllers\Api\Vendor\ReviewController as VendorReviewController;
use App\Http\Controllers\Api\Driver\ReviewController as DriverReviewController;
use App\Http\Controllers\Api\Admin\ReviewController as AdminReviewController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\FcmTokenController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\ReviewController;
use Illuminate\Http\Request;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

// OTP verification
Route::post('otp/send',   [App\Http\Controllers\Api\OtpController::class, 'send']);
Route::post('otp/verify', [App\Http\Controllers\Api\OtpController::class, 'verify']);

//app version
Route::post('/version/check', [App\Http\Controllers\Api\AppVersionController::class, 'check']);

// Settings
Route::get('/settings', function () {
    return response()->json([
        'support_whatsapp' => \App\Models\AppSetting::get('support_whatsapp', '249912414288'),
        'bank_name'        => \App\Models\AppSetting::get('bank_name', 'بنك الخرطوم'),
        'account_name'     => \App\Models\AppSetting::get('account_name', 'نهى احمد'),
        'account_number'   => \App\Models\AppSetting::get('account_number', '8389213'),
        'phone_verification' => \App\Models\AppSetting::get('phone_verification', 'false'),
    ]);
});

// Terms
Route::get('/terms', function () {
    return response()->json([
        'vendor_terms'  => \App\Models\AppSetting::get('vendor_terms', ''),
        'driver_terms'  => \App\Models\AppSetting::get('driver_terms', ''),
        'terms_version' => \App\Models\AppSetting::get('terms_version', '1.0'),
    ]);
});

Route::get('/drivers/{driverId}/reviews', [ReviewController::class, 'driverReviews']);


// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
    Route::post('/profile/update', [AuthController::class, 'updateProfile']);
    Route::post('/fcm-token', [FcmTokenController::class, 'update']);

});

// ── Vendor routes ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:vendor'])
    ->prefix('vendor')
    ->group(function () {
        // Wallet
        Route::get('wallet', [WalletController::class, 'show']);

        // Top-up requests
        Route::get('topups',  [VendorTopupController::class, 'index']);
        Route::post('topups', [VendorTopupController::class, 'store']);

        // Delivery orders
        Route::get('orders',                 [VendorOrderController::class, 'index']);
        Route::post('orders',                [VendorOrderController::class, 'store']);
        Route::get('orders/{order}',         [VendorOrderController::class, 'show']);
        Route::post('orders/{order}/cancel', [VendorOrderController::class, 'cancel']);

        // Bids
        Route::post('orders/{order}/bids/{bid}/accept', [VendorBidController::class, 'accept']);

        // Delivery lifecycle
        Route::get('orders/{order}/track',    [VendorDeliveryController::class, 'track']);
        Route::post('orders/{order}/confirm', [VendorDeliveryController::class, 'confirm']);
        Route::post('orders/{order}/dispute', [VendorDeliveryController::class, 'dispute']);
        Route::post('orders/{order}/cancel-pickup', [VendorDeliveryController::class, 'cancelPickup']);

        // Disputes
        Route::get('disputes',          [VendorDisputeController::class, 'index']);
        Route::get('disputes/{dispute}',[VendorDisputeController::class, 'show']);

        // Reviews
        Route::post('orders/{order}/review', [VendorReviewController::class, 'store']);
        Route::get('reviews/my',  [VendorReviewController::class, 'index']);           
    });
// ── Driver routes ─────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:driver'])
    ->prefix('driver')
    ->group(function () {
        // Browse orders
        Route::get('orders',         [DriverOrderController::class, 'index']);
        Route::get('orders/{order}', [DriverOrderController::class, 'show']);

        // Bids
        Route::get('bids',                         [DriverBidController::class, 'index']);
        Route::post('orders/{order}/bids',         [DriverBidController::class, 'store']);
        Route::put('orders/{order}/bids/{bid}',    [DriverBidController::class, 'update']);
        Route::delete('orders/{order}/bids/{bid}', [DriverBidController::class, 'destroy']);

        // Delivery lifecycle
        Route::get('deliveries',                          [DriverDeliveryController::class, 'index']);
        Route::post('orders/{order}/accept',              [DriverDeliveryController::class, 'accept']);
        Route::post('orders/{order}/mark-delivered',      [DriverDeliveryController::class, 'markDelivered']);
        Route::post('orders/{order}/update-location',     [DriverDeliveryController::class, 'updateLocation']);
        Route::post('orders/{order}/start-pickup',  [DriverDeliveryController::class, 'startPickup']);
        Route::post('orders/{order}/start-transit', [DriverDeliveryController::class, 'startTransit']);
        Route::post('orders/{order}/cancel', [DriverDeliveryController::class, 'cancel']);

        // Withdrawals
        Route::get('withdrawals',                    [DriverWithdrawalController::class, 'index']);
        Route::post('withdrawals',                   [DriverWithdrawalController::class, 'store']);
        Route::post('withdrawals/{withdrawal}/cancel',[DriverWithdrawalController::class, 'cancel']);

        // Reviews
        Route::post('orders/{order}/review',  [DriverReviewController::class, 'store']);
        Route::get('reviews/my',              [DriverReviewController::class, 'index']);
        Route::get('reviews/received',        [DriverReviewController::class, 'received']);
        Route::get('reviews/given',           [DriverReviewController::class, 'given']);

        Route::get('wallet', [WalletController::class, 'show']);
    });


// ── Admin routes ──────────────────────────────────────────────
Route::middleware(['auth:sanctum', 'role:admin'])
    ->prefix('admin')
    ->group(function () {
        Route::get('vendors',              [AdminUserController::class, 'vendors']);
        Route::get('drivers',              [AdminUserController::class, 'drivers']);
        Route::get('users/{user}',         [AdminUserController::class, 'show']);
        Route::post('users/{user}/suspend',[AdminUserController::class, 'suspend']);
        Route::post('users/{user}/restore',[AdminUserController::class, 'restore']);


        Route::get('topups',                  [AdminTopupController::class, 'index']);
        Route::post('topups/{topup}/approve', [AdminTopupController::class, 'approve']);
        Route::post('topups/{topup}/reject',  [AdminTopupController::class, 'reject']);
        Route::get('topups/{topup}/receipt',  [AdminTopupController::class, 'receipt']);

        // Orders
        Route::get('orders',         [AdminOrderController::class, 'index']);
        Route::get('orders/{order}', [AdminOrderController::class, 'show']);
        
        // Disputes
        Route::get('disputes',                          [AdminDisputeController::class, 'index']);
        Route::get('disputes/{dispute}',                [AdminDisputeController::class, 'show']);
        Route::post('disputes/{dispute}/release-driver',[AdminDisputeController::class, 'releaseToDriver']);
        Route::post('disputes/{dispute}/refund-vendor', [AdminDisputeController::class, 'refundToVendor']);
        Route::post('disputes/{dispute}/split',         [AdminDisputeController::class, 'split']);


        // Withdrawals
        Route::get('withdrawals',                        [AdminWithdrawalController::class, 'index']);
        Route::post('withdrawals/{withdrawal}/approve',  [AdminWithdrawalController::class, 'approve']);
        Route::post('withdrawals/{withdrawal}/reject',   [AdminWithdrawalController::class, 'reject']);

        // Reviews
        Route::get('reviews',              [AdminReviewController::class, 'index']);
        Route::delete('reviews/{review}',  [AdminReviewController::class, 'destroy']);

        // Wallet
        Route::get('dashboard', [AdminDashboardController::class, 'index']);

        // approve and reject driver
        Route::post('drivers/{user}/approve', [AdminUserController::class, 'approveDriver']);
        Route::post('drivers/{user}/reject',  [AdminUserController::class, 'rejectDriver']);
        
        // app version 
        Route::put('app-version',  [App\Http\Controllers\Api\Admin\AppVersionController::class, 'update']);
        Route::get('app-version',  [App\Http\Controllers\Api\Admin\AppVersionController::class, 'show']);

        // Save settings
        Route::put('settings', function (Request $request) {
            $fields = [
                'support_whatsapp',
                'bank_name',
                'account_name',
                'account_number',
                'phone_verification',
            ];
            foreach ($fields as $field) {
                if ($request->has($field)) {
                    \App\Models\AppSetting::set($field, $request->$field);
                }
            }
            return response()->json(['message' => 'تم حفظ الإعدادات.']);
        });

        // Save terms
        Route::put('terms', function (\Illuminate\Http\Request $request) {
            \App\Models\AppSetting::set('vendor_terms', $request->vendor_terms);
            \App\Models\AppSetting::set('driver_terms', $request->driver_terms);

            // Bump version to force users to re-agree
            $current = \App\Models\AppSetting::get('terms_version', '1.0');
            $parts   = explode('.', $current);
            $parts[1] = (int)($parts[1] ?? 0) + 1;
            \App\Models\AppSetting::set('terms_version', implode('.', $parts));

            return response()->json(['message' => 'تم حفظ الشروط والأحكام.']);
        });

        // Get terms (admin)
        Route::get('terms', function () {
            return response()->json([
                'vendor_terms'  => \App\Models\AppSetting::get('vendor_terms', ''),
                'driver_terms'  => \App\Models\AppSetting::get('driver_terms', ''),
                'terms_version' => \App\Models\AppSetting::get('terms_version', '1.0'),
            ]);
        });

        // Get settings (admin)
        Route::get('settings', function () {
            return response()->json([
                'support_whatsapp' => \App\Models\AppSetting::get('support_whatsapp', '249912414288'),
                'bank_name'        => \App\Models\AppSetting::get('bank_name', 'بنك الخرطوم'),
                'account_name'     => \App\Models\AppSetting::get('account_name', 'نهى احمد'),
                'account_number'   => \App\Models\AppSetting::get('account_number', '8389213'),
                'phone_verification' => \App\Models\AppSetting::get('phone_verification', 'false'),
                ]);
        });

        // Update user service fee
        Route::put('users/{user}/fee', function (Request $request, \App\Models\User $user) {
            $request->validate([
                'service_fee_percentage' => 'required|numeric|min:0|max:100',
                'type'                   => 'required|in:vendors,drivers',
            ]);

            if ($request->type === 'vendors') {
                \App\Models\VendorProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    ['service_fee_percentage' => $request->service_fee_percentage]
                );
            } else {
                \App\Models\DriverProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    ['service_fee_percentage' => $request->service_fee_percentage]
                );
            }

            return response()->json(['message' => 'تم تحديث رسوم الخدمة.']);
        });
    });