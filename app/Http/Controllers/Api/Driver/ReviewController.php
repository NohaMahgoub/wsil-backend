<?php
namespace App\Http\Controllers\Api\Driver;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Driver reviews the vendor after completion
    public function store(Request $request, DeliveryOrder $order)
    {
        $delivery = Delivery::where('order_id', $order->id)
            ->whereNotIn('status', ['cancelled'])
            ->latest()
            ->first();

        if (!$delivery || $delivery->driver_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        // Allow review for delivered or completed
        if (!in_array($delivery->status, ['delivered', 'completed'])) {
            return response()->json([
                'message' => 'يمكنك التقييم بعد تسليم الطلب فقط.',
            ], 422);
        }

        // Check 48hr window
        $checkTime = $delivery->confirmed_at ?? $delivery->delivered_at ?? now();
        if (now()->isAfter($checkTime->addHours(48))) {
            return response()->json([
                'message' => 'انتهت مدة التقييم.',
            ], 422);
        }

        // Check if already reviewed
        $existing = Review::where('delivery_id', $delivery->id)
            ->where('reviewer_id', $request->user()->id)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'لقد قمت بتقييم هذا التوصيل مسبقاً.',
            ], 422);
        }

        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $review = Review::create([
            'delivery_id'   => $delivery->id,
            'reviewer_id'   => $request->user()->id,
            'reviewer_role' => 'driver',
            'reviewee_id'   => $order->vendor_id,
            'reviewee_role' => 'vendor',
            'rating'        => $request->rating,
            'comment'       => $request->comment,
            'expires_at'    => now()->addHours(48), 
        ]);

        return response()->json([
            'message' => 'تم إرسال التقييم بنجاح.',
            'review'  => $review,
        ], 201);
    }

    // Driver views reviews they received
    public function received(Request $request)
    {
        $reviews = Review::where('reviewee_id', $request->user()->id)
            ->where('reviewee_role', 'driver')
            ->with('reviewer:id,name', 'delivery.order')
            ->latest()
            ->paginate(15);

        return response()->json($reviews);
    }

    // Driver views reviews they gave
    public function given(Request $request)
    {
        $reviews = Review::where('reviewer_id', $request->user()->id)
            ->where('reviewer_role', 'driver')
            ->with('reviewee:id,name', 'delivery.order')
            ->latest()
            ->paginate(15);

        return response()->json($reviews);
    }

    // Driver views all their reviews (index)
    public function index(Request $request)
    {
        $reviews = Review::where('reviewee_id', $request->user()->id)
            ->where('reviewee_role', 'driver')
            ->with('reviewer:id,name', 'delivery.order')
            ->latest()
            ->paginate(15);

        return response()->json($reviews);
    }
}