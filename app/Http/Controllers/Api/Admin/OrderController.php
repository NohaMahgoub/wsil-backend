<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DeliveryOrder;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = DeliveryOrder::with([
            'vendor:id,name,email',
            'delivery.driver:id,name',
        ])
        ->when($request->status, fn($q) => $q->where('status', $request->status))
        ->latest()
        ->paginate(20);

        return response()->json($orders);
    }

    public function show(DeliveryOrder $order)
    {
        $order->load([
            'vendor:id,name,email',
            'bids.driver.driverProfile',
            'delivery.driver:id,name',
            'delivery.dispute',
        ]);

        return response()->json($order);
    }
}