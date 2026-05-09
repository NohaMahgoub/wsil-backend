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
use Illuminate\Http\Request;

// Public routes
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

//app version
Route::post('/version/check', [App\Http\Controllers\Api\AppVersionController::class, 'check']);

Route::get('/settings', function () {
    $path = storage_path('app/settings.json');
    $settings = file_exists($path)
        ? json_decode(file_get_contents($path), true)
        : [];

    return response()->json(array_merge([
        'support_whatsapp' => env('SUPPORT_WHATSAPP', '249900000000'),
        'bank_name'        => env('BANK_NAME', 'بنك الخرطوم'),
        'account_name'     => env('ACCOUNT_NAME', 'وصل للتوصيل'),
        'account_number'   => env('ACCOUNT_NUMBER', '1234567890'),
    ], $settings));
});

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);
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

        Route::put('settings', function(Request $request) {
            $settings = [
                'support_whatsapp' => $request->support_whatsapp,
                'bank_name'        => $request->bank_name,
                'account_name'     => $request->account_name,
                'account_number'   => $request->account_number,
            ];

            file_put_contents(
                storage_path('app/settings.json'),
                json_encode($settings, JSON_UNESCAPED_UNICODE)
            );

            return response()->json(['message' => 'تم حفظ الإعدادات.']);
        });
    });