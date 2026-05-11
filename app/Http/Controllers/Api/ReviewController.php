<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;

class ReviewController extends Controller
{
    public function driverReviews($driverId)
    {
        $driver = User::find($driverId);
        if (!$driver) {
            return response()->json(['message' => 'السائق غير موجود.'], 404);
        }

        $reviews = Review::where('reviewee_id', $driverId)
            ->where('reviewee_role', 'driver')
            ->with('reviewer:id,name')
            ->latest()
            ->take(10)
            ->get()
            ->map(fn($r) => [
                'id'            => $r->id,
                'rating'        => $r->rating,
                'comment'       => $r->comment,
                'reviewer'      => $r->reviewer?->name ?? 'بائع',
                'reviewer_role' => $r->reviewer_role,
                'created_at'    => $r->created_at->toDateString(),
            ]);

        $count = Review::where('reviewee_id', $driverId)
            ->where('reviewee_role', 'driver')
            ->count();

        return response()->json([
            'average_rating' => round($reviews->avg('rating') ?? 0, 1),
            'total_reviews'  => $count,
            'reviews'        => $reviews,
        ]);
    }
}