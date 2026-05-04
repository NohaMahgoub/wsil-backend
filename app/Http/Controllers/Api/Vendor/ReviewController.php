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
            return response()->json(['message' => 'Unauthorized.'], 403);
        }

        $delivery = $order->delivery;

        if (! $delivery || $delivery->status !== 'completed') {
            return response()->json([
                'message' => 'You can only review a completed delivery.',
            ], 422);
        }

        // Check 48hr window
        if (now()->isAfter($delivery->confirmed_at->addHours(48))) {
            return response()->json([
                'message' => 'Review window has expired. You had 48 hours to leave a review.',
            ], 422);
        }

        // Check if already reviewed
        $existing = Review::where('delivery_id', $delivery->id)
            ->where('reviewer_id', $request->user()->id)
            ->exists();

        if ($existing) {
            return response()->json([
                'message' => 'You have already reviewed this delivery.',
            ], 422);
        }

        $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $review = Review::create([
            'delivery_id'  => $delivery->id,
            'reviewer_id'  => $request->user()->id,
            'reviewee_id'  => $delivery->driver_id,
            'rating'       => $request->rating,
            'comment'      => $request->comment,
            'expires_at'   => $delivery->confirmed_at->addHours(48),
        ]);

        // Update driver's average rating
        $this->updateDriverRating($delivery->driver_id);

        return response()->json([
            'message' => 'Review submitted successfully.',
            'review'  => $review,
        ], 201);
    }

    // Update driver average rating
    private function updateDriverRating(int $driverId): void
    {
        $avg = Review::where('reviewee_id', $driverId)->avg('rating');
        $count = Review::where('reviewee_id', $driverId)->count();

        $profile = \App\Models\DriverProfile::where('user_id', $driverId)->first();
        if ($profile) {
            $profile->update([
                'rating'        => round($avg, 2),
                'total_reviews' => $count,
            ]);
        }
    }
}