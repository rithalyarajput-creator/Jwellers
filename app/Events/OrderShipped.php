<?php

namespace App\Events;

use App\Models\Order;
use App\Models\OrderShipment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderShipped
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $trackingNumber = null,
        public ?OrderShipment $shipment = null,
    ) {
        // Auto-resolve the latest shipment for the order if not passed.
        // Keeps the call-site simple while ensuring listeners + mailables
        // get the richer fields (carrier, AWB, ETA) for free.
        $this->shipment ??= $order->shipments()->latest('id')->first();
    }
}
