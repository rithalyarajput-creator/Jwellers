<?php

namespace App\Services;

use App\Models\Cart;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Shiprocket Checkout (hosted one-page checkout).
 *
 * Distinct from App\Services\ShiprocketService — that one is the post-order
 * Shipping API (AWB / pickup / tracking, email+password auth). This service
 * authenticates with the Checkout product's API key + secret (HMAC-SHA256
 * Base64) and produces an *access token* the frontend hands to Shiprocket's
 * HeadlessCheckout JS snippet to launch the hosted checkout overlay.
 *
 * Source of truth: https://documenter.getpostman.com/view/25617008/2sB34bL3ig
 *
 * Flow:
 *   1. POST /api/v1/access-token/checkout with cart payload + redirect_url.
 *   2. Response gives {result: {token, expires_at, data: {order_id}}}.
 *   3. Frontend loads channels/shopify.js + shopify.css, then calls
 *      HeadlessCheckout.addToCart(event, token, {fallbackUrl, isInitiatedFromApp}).
 *   4. After success, Shiprocket redirects browser to redirect_url?oid=...&ost=SUCCESS.
 *   5. Server-side, Shiprocket also POSTs a webhook to the registered URL
 *      with the full order — see ShiprocketCheckoutWebhookController.
 */
class ShiprocketCheckoutService
{
    public const UI_SCRIPT_PROD = 'https://checkout-ui.shiprocket.com/assets/js/channels/shopify.js';
    public const UI_STYLE_PROD  = 'https://checkout-ui.shiprocket.com/assets/styles/shopify.css';
    public const UI_SCRIPT_STAGING = 'https://customcheckoutfastrr.netlify.app/assets/js/channels/shopify.js';
    public const UI_STYLE_STAGING  = 'https://customcheckoutfastrr.netlify.app/assets/styles/shopify.css';

    public function isEnabled(): bool
    {
        return (bool) config('services.shiprocket_checkout.enabled')
            && config('services.shiprocket_checkout.key')
            && config('services.shiprocket_checkout.secret');
    }

    public function isStaging(): bool
    {
        return str_contains(
            (string) config('services.shiprocket_checkout.base_url'),
            'fastrr-api-dev.pickrr.com'
        );
    }

    public function uiScriptUrl(): string
    {
        return $this->isStaging() ? self::UI_SCRIPT_STAGING : self::UI_SCRIPT_PROD;
    }

    public function uiStyleUrl(): string
    {
        return $this->isStaging() ? self::UI_STYLE_STAGING : self::UI_STYLE_PROD;
    }

    /**
     * Build the cart_data payload Shiprocket Checkout expects.
     *
     * Each item carries its own catalog_data (override price + name + image)
     * so that any price changes between catalog sync runs cannot cause a
     * mismatch between what the user sees and what Shiprocket charges. Coupon
     * discounts are passed via cart_discount (Shiprocket replaces, not stacks).
     *
     * Variant ID convention matches our catalog feed
     * (App\Http\Controllers\Api\ShiprocketCatalogController::mapProduct):
     *   - real variant → product_variants.id
     *   - no variant   → products.id (synthesised variant in the catalog)
     */
    public function buildCartData(Cart $cart): array
    {
        $cart->loadMissing(['items.product', 'items.variant', 'coupon']);

        $items = $cart->items->map(function ($item) {
            $variantId = $item->variant_id ? (int) $item->variant_id : (int) $item->product_id;

            return [
                'variant_id'   => (string) $variantId,
                'quantity'     => (int) $item->quantity,
                'catalog_data' => [
                    'price'     => round((float) $item->price, 2),
                    'name'      => (string) ($item->product->name ?? ''),
                    'image_url' => (string) ($item->product->primary_image_url ?? ''),
                ],
            ];
        })->values()->toArray();

        $cartData = [
            'items'             => $items,
            'mobile_app'        => false,
            'custom_attributes' => [
                'cart_id'    => (string) $cart->id,
                'session_id' => (string) ($cart->session_id ?? ''),
                'user_id'    => (string) ($cart->user_id ?? ''),
            ],
        ];

        if ($cart->coupon && (float) $cart->discount > 0) {
            $cartData['cart_discount'] = [
                'coupon_code' => (string) $cart->coupon->code,
                'amount'      => round((float) $cart->discount, 2),
            ];
        }

        return $cartData;
    }

    /**
     * Stable hash of the cart contents for idempotency.
     * Same contents within 90s reuse the same token instead of creating a new SR session.
     */
    public function cartReference(Cart $cart): string
    {
        $signature = collect($cart->items)
            ->map(fn ($i) => "{$i->product_id}:{$i->variant_id}:{$i->quantity}:{$i->price}")
            ->sort()
            ->implode('|');

        return hash('sha256', "cart:{$cart->id}|coupon:{$cart->coupon_id}|total:{$cart->total}|sig:{$signature}");
    }

    /**
     * Create a Shiprocket Checkout access token for the given cart.
     *
     * Returns ['token', 'expires_at', 'sr_order_id', 'reference'].
     * Throws on failure — caller catches and falls back to native checkout.
     */
    public function createSession(Cart $cart): array
    {
        if (! $this->isEnabled()) {
            throw new \RuntimeException('Shiprocket Checkout is not enabled');
        }
        if ($cart->isEmpty()) {
            throw new \RuntimeException('Cannot create checkout session for an empty cart');
        }

        $reference = $this->cartReference($cart);

        $cached = Cache::get("sr_checkout_session:{$reference}");
        if ($cached) {
            return $cached + ['cached' => true, 'reference' => $reference];
        }

        $payload = [
            'cart_data'    => $this->buildCartData($cart),
            'redirect_url' => route('checkout.shiprocket.return'),
            'timestamp'    => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];

        $body      = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = base64_encode(hash_hmac(
            'sha256',
            $body,
            (string) config('services.shiprocket_checkout.secret'),
            true
        ));

        $baseUrl  = rtrim((string) config('services.shiprocket_checkout.base_url'), '/');
        $endpoint = $baseUrl . '/api/v1/access-token/checkout';

        $response = Http::withHeaders([
                'X-Api-Key'         => (string) config('services.shiprocket_checkout.key'),
                'X-Api-HMAC-SHA256' => $signature,
                'Content-Type'      => 'application/json',
                'Accept'            => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->timeout(15)
            ->post($endpoint);

        if ($response->failed() || ! ($response->json('ok') === true)) {
            Log::error('Shiprocket Checkout: createSession failed', [
                'status'   => $response->status(),
                'response' => $response->body(),
                'cart_id'  => $cart->id,
            ]);
            throw new \RuntimeException(
                'Shiprocket Checkout API: ' . ($response->json('error') ?: $response->status())
            );
        }

        $token      = $response->json('result.token');
        $expiresAt  = $response->json('result.expires_at');
        $srOrderId  = $response->json('result.data.order_id');

        if (! $token) {
            Log::error('Shiprocket Checkout: no token in response', ['response' => $response->json()]);
            throw new \RuntimeException('Shiprocket Checkout did not return a token');
        }

        $session = [
            'token'       => $token,
            'expires_at'  => $expiresAt,
            'sr_order_id' => $srOrderId,
        ];

        Cache::put("sr_checkout_session:{$reference}", $session, now()->addSeconds(90));

        // Persist sr_order_id on the cart so the webhook handler can recover it
        // later (webhook payload only carries paymentDetails.transactionId, not
        // the canonical sr_order_id that fetchOrderDetails needs).
        $cartMeta = $cart->metadata ?? [];
        $cartMeta['sr_checkout_order_id'] = $srOrderId;
        $cartMeta['sr_checkout_token_at'] = now()->toIso8601String();
        $cart->update(['metadata' => $cartMeta]);

        Log::info('Shiprocket Checkout: token created', [
            'cart_id'     => $cart->id,
            'sr_order_id' => $srOrderId,
            'reference'   => $reference,
        ]);

        return $session + ['cached' => false, 'reference' => $reference];
    }

    /**
     * Fetch order details from Shiprocket by their order_id.
     * Used by the webhook handler as a verification step and by the return-URL
     * handler when ost=SUCCESS arrives but the webhook hasn't landed yet.
     */
    public function fetchOrderDetails(string $shiprocketOrderId): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $payload = [
            'order_id'  => $shiprocketOrderId,
            'timestamp' => now()->utc()->format('Y-m-d\TH:i:s.u\Z'),
        ];
        $body      = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = base64_encode(hash_hmac(
            'sha256',
            $body,
            (string) config('services.shiprocket_checkout.secret'),
            true
        ));

        $baseUrl = rtrim((string) config('services.shiprocket_checkout.base_url'), '/');
        $response = Http::withHeaders([
                'X-Api-Key'         => (string) config('services.shiprocket_checkout.key'),
                'X-Api-HMAC-SHA256' => $signature,
                'Content-Type'      => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->timeout(15)
            ->post($baseUrl . '/api/v1/custom-platform-order/details');

        if ($response->failed() || ! ($response->json('ok') === true)) {
            Log::warning('Shiprocket Checkout: fetchOrderDetails failed', [
                'status'   => $response->status(),
                'sr_order' => $shiprocketOrderId,
            ]);
            return null;
        }

        return $response->json('result');
    }

    /**
     * Verify an inbound webhook signature.
     * Shiprocket signs with the same key/secret as the API.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signature): bool
    {
        $secret = (string) config('services.shiprocket_checkout.secret');
        if ($secret === '' || $signature === null || $signature === '') {
            return false;
        }
        $expected = base64_encode(hash_hmac('sha256', $rawBody, $secret, true));
        return hash_equals($expected, $signature);
    }
}
