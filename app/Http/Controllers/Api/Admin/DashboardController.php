<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use App\Models\Dispute;
use App\Models\TopupRequest;
use App\Models\User;
use App\Models\Delivery;
use App\Models\WalletTransaction;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        // Stats
        $totalRevenue = WalletTransaction::where('type', 'debit')
            ->where('description', 'like', '%service fee%')
            ->orWhere('description', 'like', '%escrow%')
            ->sum('amount');

        $activeOrders    = DeliveryOrder::whereIn('status', ['active', 'assigned', 'delivered'])->count();
        $pendingTopups   = TopupRequest::where('status', 'pending')->count();
        $pendingDisputes = Dispute::where('status', 'open')->count();
        $pendingWithdrawals = \App\Models\WithdrawalRequest::where('status', 'pending')->count();
        $totalUsers      = User::whereIn('role', ['vendor', 'driver'])->count();
        $totalVendors    = User::where('role', 'vendor')->count();
        $totalDrivers    = User::where('role', 'driver')->count();
        $pendingDrivers  = User::where('role', 'driver')->where('approval_status', 'pending')->count();


        // Pending actions count
        $pendingActions = $pendingTopups + $pendingDisputes + $pendingWithdrawals;

        // Recent orders
        $recentOrders = DeliveryOrder::with([
            'vendor:id,name',
            'delivery.driver:id,name',
        ])
        ->latest()
        ->take(5)
        ->get();

        // Pending top-ups
        $pendingTopupsList = TopupRequest::with('vendor:id,name')
            ->where('status', 'pending')
            ->latest()
            ->take(5)
            ->get();

        // Open disputes
        $openDisputesList = Dispute::with([
            'delivery.order',
            'delivery.driver:id,name',
            'delivery.vendor:id,name',
        ])
        ->where('status', 'open')
        ->latest()
        ->take(3)
        ->get();

        return response()->json([
            'stats' => [
                'total_revenue'       => $totalRevenue,
                'active_orders'       => $activeOrders,
                'pending_actions'     => $pendingActions,
                'total_users'         => $totalUsers,
                'total_vendors'       => $totalVendors,
                'total_drivers'       => $totalDrivers,
                'pending_drivers'     => $pendingDrivers,
                'pending_topups'      => $pendingTopups,
                'pending_disputes'    => $pendingDisputes,
                'pending_withdrawals' => $pendingWithdrawals,
            ],
            'recent_orders'      => $recentOrders,
            'pending_topups'     => $pendingTopupsList,
            'open_disputes'      => $openDisputesList,
        ]);
    }
}
