<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderShipment extends Model
{
    protected $fillable = [
        'order_id',
        'delivery_partner_id',
        'tracking_number',
        'carrier',
        'carrier_code',
        'label_url',
        'weight',
        'dimensions',
        'status',
        'tracking_history',
        'shipped_at',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'decimal:2',
            'dimensions' => 'array',
            'tracking_history' => 'array',
            'shipped_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function deliveryPartner(): BelongsTo
    {
        return $this->belongsTo(DeliveryPartner::class);
    }

    // ── Customer-facing tracking accessors ────────────────────────────
    //
    // The OrderShipped email uses these to give the customer a clickable
    // carrier-direct link + an internal tracking page. The internal link
    // is generated in the view via URL::signedRoute('track-order.signed',...)
    // — no schema change needed; signature is APP_KEY-derived.

    /**
     * Carrier-direct tracking URL.
     *
     * Returns a deep link into the carrier's own tracking page when we
     * know the URL format, NULL otherwise. The email template falls back
     * to the internal signed tracking page when this is null.
     */
    public function getCarrierTrackingUrlAttribute(): ?string
    {
        if (empty($this->tracking_number)) {
            return null;
        }

        $awb = urlencode((string) $this->tracking_number);
        $code = strtolower((string) ($this->carrier_code ?: $this->carrier));

        return match (true) {
            str_contains($code, 'shiprocket')   => "https://shiprocket.co/tracking/{$awb}",
            str_contains($code, 'dtdc')         => "https://www.dtdc.in/tracking?awb={$awb}",
            str_contains($code, 'bluedart')     => "https://www.bluedart.com/tracking?awb={$awb}",
            str_contains($code, 'delhivery')    => "https://www.delhivery.com/tracking?wbn={$awb}",
            str_contains($code, 'ekart')        => "https://ekart.flipkart.com/tracking?awb={$awb}",
            str_contains($code, 'xpressbees')   => "https://www.xpressbees.com/track?awb={$awb}",
            str_contains($code, 'shadowfax')    => "https://tracker.shadowfax.in/tracking/{$awb}",
            str_contains($code, 'ecom')         => "https://ecomexpress.in/tracking/?awb_field={$awb}",
            default => null,
        };
    }

    /**
     * Best-available delivery ETA for display in customer comms.
     *
     * Carrier-provided ETAs vary in reliability; we lean on the order's
     * `expected_delivery_date` which is set at order placement and
     * refined by Shiprocket webhooks. Returns a Carbon instance so
     * Blade can format with `->format('M d, Y')` etc.
     */
    public function getEtaAttribute(): ?\Carbon\Carbon
    {
        return $this->order?->expected_delivery_date;
    }

    /**
     * Human-friendly carrier name for display.
     * Prefers the explicit `carrier` field; falls back to a Title-Cased
     * carrier_code; returns empty string if neither is set so templates
     * can use `{{ $shipment->display_carrier ?: 'the courier' }}`.
     */
    public function getDisplayCarrierAttribute(): string
    {
        if (! empty($this->carrier)) {
            return (string) $this->carrier;
        }
        if (! empty($this->carrier_code)) {
            return ucwords(str_replace(['_', '-'], ' ', (string) $this->carrier_code));
        }
        return '';
    }

    public function addTrackingEvent(string $status, string $location, ?string $description = null): void
    {
        $history = $this->tracking_history ?? [];
        $history[] = [
            'status' => $status,
            'location' => $location,
            'description' => $description,
            'timestamp' => now()->toIso8601String(),
        ];

        $this->update([
            'tracking_history' => $history,
            'status' => $status,
        ]);
    }
}
