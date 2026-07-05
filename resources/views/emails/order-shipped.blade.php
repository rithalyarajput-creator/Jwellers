@component('mail::message')
# Your Order Is On Its Way!

Hi {{ $order->recipient_name }},

Exciting news -- your order **#{{ $order->order_number }}** has been shipped and is on its way to you!

{{-- Shipment block: carrier + AWB + ETA, only rendered if we have shipment data.
     `$shipment` is resolved by the OrderShipped Mailable constructor;
     trackingNumber is also passed for backwards-compat with older callers. --}}
@php
    $awb = $trackingNumber ?? $shipment?->tracking_number;
    $carrierName = $shipment?->display_carrier ?: '';
    $carrierLink = $shipment?->carrier_tracking_url;
    $eta = $shipment?->eta ?? $order->expected_delivery_date;
    $signedTrackUrl = \Illuminate\Support\Facades\URL::signedRoute(
        'track-order.signed',
        ['order' => $order->order_number]
    );
@endphp

@if($awb || $carrierName)
**Shipment details:**

| | |
|:---|:---|
@if($carrierName)| Courier | {{ $carrierName }} |@endif

@if($awb)| Tracking number | {{ $awb }} |@endif

@if($eta)| Estimated delivery | {{ $eta->format('D, M d, Y') }} |@endif

@endif

@component('mail::button', ['url' => $signedTrackUrl])
Track Your Order
@endcomponent

@if($carrierLink)
You can also follow your package directly with the courier:

[Live updates on {{ $carrierName ?: 'the courier' }} →]({{ $carrierLink }})

@endif

---

## Order Summary

**Order Number:** #{{ $order->order_number }}
**Shipped On:** {{ $order->shipped_at ? $order->shipped_at->format('M d, Y \a\t h:i A') : now()->format('M d, Y \a\t h:i A') }}

@component('mail::table')
| Item | Qty | Price |
|:-----|:---:|------:|
@foreach ($order->items as $item)
| {{ $item->product_name }}@if($item->variant_name) ({{ $item->variant_name }})@endif | {{ $item->quantity }} | {{ format_price($item->total) }} |
@endforeach
| | **Total:** | **{{ format_price($order->total) }}** |
@endcomponent

@if($order->shipping_address_snapshot)
**Delivering To:**
{{ $order->shipping_address_snapshot['name'] ?? '' }}
{{ $order->shipping_address_snapshot['address_line_1'] ?? '' }}
@if(!empty($order->shipping_address_snapshot['address_line_2'])){{ $order->shipping_address_snapshot['address_line_2'] }}@endif
{{ $order->shipping_address_snapshot['city'] ?? '' }}, {{ $order->shipping_address_snapshot['state'] ?? '' }} {{ $order->shipping_address_snapshot['postal_code'] ?? '' }}
@endif

We will notify you once your order has been delivered. If you have any questions about your shipment, our support team is here to help.

Happy shopping!

Warm regards,
**ForeverKids**
@endcomponent
