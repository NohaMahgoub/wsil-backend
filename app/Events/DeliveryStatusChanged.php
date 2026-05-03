<?php
namespace App\Events;

use App\Models\Delivery;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DeliveryStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int    $orderId,
        public string $status,
        public string $message,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("order.{$this->orderId}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id'  => $this->orderId,
            'status'    => $this->status,
            'message'   => $this->message,
            'timestamp' => now()->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'status.changed';
    }
}