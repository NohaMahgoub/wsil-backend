<?php
namespace App\Console\Commands;

use App\Models\Delivery;
use App\Models\DeliveryOrder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AutoReleaseDeliveries extends Command
{
    protected $signature   = 'deliveries:auto-release';
    protected $description = 'Auto release payment to driver if vendor does not confirm within 24 hours';

    public function handle(): void
    {
        $deliveries = Delivery::where('status', 'delivered')
            ->where('auto_release_at', '<=', now())
            ->with(['driver', 'order'])
            ->get();

        foreach ($deliveries as $delivery) {
            DB::transaction(function () use ($delivery) {
                // Release money to driver
                $delivery->driver->wallet->credit(
                    amount:      $delivery->delivery_price,
                    description: "Auto-released payment for delivery #{$delivery->order->id}",
                    reference:   "AUTO-DELIVERY-{$delivery->id}",
                );

                // Update statuses
                $delivery->update([
                    'status'       => 'completed',
                    'confirmed_at' => now(),
                ]);

                $delivery->order->update(['status' => 'completed']);
            });

            $this->info("Auto-released delivery #{$delivery->id}");
        }

        $this->info("Done. {$deliveries->count()} deliveries auto-released.");
    }
}