<?php
namespace App\Console\Commands;

use App\Models\Delivery;
use Illuminate\Console\Command;

class ExpireReviews extends Command
{
    protected $signature   = 'reviews:expire';
    protected $description = 'Close the review window for deliveries older than 48 hours';

    public function handle(): void
    {
        // This is handled at the application level
        // when checking canBeReviewed() on the delivery
        // No DB changes needed — just log expired ones
        $expired = Delivery::where('status', 'completed')
            ->where('confirmed_at', '<=', now()->subHours(48))
            ->whereDoesntHave('reviews')
            ->count();

        $this->info("Review window expired for {$expired} deliveries with no reviews.");
    }
}