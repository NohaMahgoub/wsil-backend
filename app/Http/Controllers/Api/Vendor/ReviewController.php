<?php
namespace App\Http\Controllers\Api\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\DeliveryOrder;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // Vendor reviews the driver after completion
    public function store(Request $request, DeliveryOrder $order)
    {
        if ($order->vendor_id !== $request->user()->id) {
            return response()->json(['message' => 'غير مصرح.'], 403);
        }

        $delivery = $order->delivery;

        if (!$delivery || $delivery->status !== 'completed') {
            return response()->json([
                'message' => 'يمكنك التقييم بعد اكتمال التوصيل فقط.',
            ], 422);
        }

        // Check 48hr window
        if (now()->isAfter($delivery->confirmed_at->addHours(48))) {
            return response()->json([
                'message' => 'انتهت مدة التقييم. كان لديك 48 ساعة لإرسال التقييم.',
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
        }  // ← missing closing brace was here

        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $review = Review::create([
            'delivery_id'   => $delivery->id,
            'reviewer_id'   => $request->user()->id,
            'reviewer_role' => 'vendor',
            'reviewee_id'   => $delivery->driver_id,
            'reviewee_role' => 'driver',
            'rating'        => $request->rating,
            'comment'       => $request->comment,
            'expires_at'    => now()->addHours(48), 
        ]);

        // Update driver's average rating
        $this->updateDriverRating($delivery->driver_id);

        return response()->json([
            'message' => 'تم إرسال التقييم بنجاح.',
            'review'  => $review,
        ], 201);
    }

    // Update driver average rating
    private function updateDriverRating(int $driverId): void
    {
        $avg   = Review::where('reviewee_id', $driverId)
                    ->where('reviewee_role', 'driver')
                    ->avg('rating');
        $count = Review::where('reviewee_id', $driverId)
                    ->where('reviewee_role', 'driver')
                    ->count();

        $profile = \App\Models\DriverProfile::where('user_id', $driverId)->first();
        if ($profile) {
            $profile->update([
                'rating'        => round($avg ?? 0, 2),
                'total_reviews' => $count,
            ]);
        }
    }
}