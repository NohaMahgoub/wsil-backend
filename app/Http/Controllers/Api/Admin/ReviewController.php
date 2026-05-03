<?php
namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    // List all reviews
    public function index(Request $request)
    {
        $reviews = Review::with([
            'reviewer:id,name',
            'reviewee:id,name',
            'delivery.order',
        ])
        ->when($request->reviewee_id, fn($q) =>
            $q->where('reviewee_id', $request->reviewee_id)
        )
        ->latest()
        ->paginate(20);

        return response()->json($reviews);
    }

    // Admin deletes an abusive review
    public function destroy(Review $review)
    {
        // Recalculate driver rating after deletion
        $revieweeId = $review->reviewee_id;
        $review->delete();

        $avg   = Review::where('reviewee_id', $revieweeId)->avg('rating');
        $count = Review::where('reviewee_id', $revieweeId)->count();

        $profile = \App\Models\DriverProfile::where('user_id', $revieweeId)->first();
        if ($profile) {
            $profile->update([
                'rating'        => round($avg ?? 0, 2),
                'total_reviews' => $count,
            ]);
        }

        return response()->json([
            'message' => 'Review deleted successfully.',
        ]);
    }
}