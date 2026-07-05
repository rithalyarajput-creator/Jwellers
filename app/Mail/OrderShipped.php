<?php

namespace App\Mail;

use App\Models\Order;
use App\Models\OrderShipment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OrderShipped extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Order $order,
        public ?string $trackingNumber = null,
        public ?OrderShipment $shipment = null,
    ) {
        // Auto-resolve the latest shipment so the template gets carrier,
        // AWB, ETA without callers having to know about OrderShipment.
        $this->shipment ??= $order->shipments()->latest('id')->first();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your Order Has Been Shipped - #' . $this->order->order_number,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.order-shipped',
        );
    }
}
