<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // List all vendors
    public function vendors(Request $request)
    {
        $vendors = User::role('vendor')
            ->with(['vendorProfile', 'wallet'])
            ->withCount('deliveryOrders')
            ->latest()
            ->paginate(20);

        return response()->json($vendors);
    }

    // List all drivers
    public function drivers(Request $request)
    {
        $drivers = User::role('driver')
            ->with(['driverProfile', 'wallet'])
            ->latest()
            ->paginate(20);

        return response()->json($drivers);
    }

    // View single user
    public function show(User $user)
    {
        $user->load(['vendorProfile', 'driverProfile', 'wallet.transactions']);

        return response()->json($user);
    }

    // Suspend a user
    public function suspend(User $user)
    {
        if ($user->hasRole('admin')) {
            return response()->json(['message' => 'Cannot suspend an admin.'], 422);
        }

        $user->update(['is_suspended' => true]);

        return response()->json(['message' => 'User suspended successfully.']);
    }

    // Restore a user
    public function restore(User $user)
    {
        $user->update(['is_suspended' => false]);

        return response()->json(['message' => 'User restored successfully.']);
    }

    public function approveDriver(Request $request, User $user)
    {
        if ($user->role !== 'driver') {
            return response()->json(['message' => 'المستخدم ليس سائقاً.'], 422);
        }

        $user->update([
            'approval_status' => 'approved',
            'approved_at'     => now(),
            'approved_by'     => $request->user()->id,
        ]);

        // Send notification
        try {
            $service = new \App\Services\NotificationService();
            $service->sendToUser(
                user:  $user,
                title: '✅ تم قبول طلبك',
                body:  'تم قبول حسابك في وصل. يمكنك الآن بدء العمل!',
                data:  ['type' => 'driver_approved'],
            );
        } catch (\Exception $e) {}

        return response()->json([
            'message' => 'تم قبول السائق بنجاح.',
        ]);
    }

    public function rejectDriver(Request $request, User $user)
    {
        if ($user->role !== 'driver') {
            return response()->json(['message' => 'المستخدم ليس سائقاً.'], 422);
        }

        $request->validate([
            'reason' => 'nullable|string',
        ]);

        $user->update([
            'approval_status' => 'rejected',
            'approved_at'     => now(),
            'approved_by'     => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'تم رفض السائق.',
        ]);
    }
}