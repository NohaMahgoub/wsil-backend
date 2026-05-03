<?php
namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DriverLocationUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int   $orderId,
        public int   $driverId,
        public float $lat,
        public float $lng,
    ) {}

    // Broadcast on a private channel per order
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->orderId}"),
        ];
    }

    // What data to send to the frontend
    public function broadcastWith(): array
    {
        return [
            'order_id'  => $this->orderId,
            'driver_id' => $this->driverId,
            'lat'       => $this->lat,
            'lng'       => $this->lng,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'location.updated';
    }
}