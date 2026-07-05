<?php

namespace App\Http\Controllers;

use App\Events\OrderPlaced;
use App\Models\Cart;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\ShiprocketCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Receives the order webhook Shiprocket Checkout fires after a customer
 * completes payment. The payload is documented at
 * https://documenter.getpostman.com/view/25617008/2sB34bL3ig (Order Webhook).
 *
 * This is the AUTHORITATIVE source of truth for Shiprocket-Checkout orders —
 * we do NOT create orders from the browser-side return URL because the user
 * can close the tab before that fires. Webhooks are retried by Shiprocket, so
 * the handler must be idempotent on `metadata->shiprocket_checkout_order_id`.
 *
 * Once the Order row is written, it appears in /admin/orders automatically
 * (admin uses the same Order model as native checkout, with payment_method
 * = 'shiprocket_checkout' in metadata to distinguish in the listing).
 */
class ShiprocketCheckoutWebhookController extends Controller
{
    public function __construct(private ShiprocketCheckoutService $service)
    {
    }

    public function handle(Request $request): JsonResponse
    {
        if (! $this->authenticateWebhook($request)) {
            return response()->json(['ok' => false, 'error' => 'unauthorized'], 401);
        }

        $data = $request->all();

        // Shiprocket sends multiple webhook stages (Cart Initiated, Phone
        // Received, Payment Initiated, Order Placed). The shape varies — sometimes
        // fields are top-level, sometimes nested under `data` / `result` / `payload`.
        // Try several common paths so we don't miss the SUCCESS event.
        [$shiprocketOrderId, $status, $eventType] = $this->extractIds($data);

        Log::info('Shiprocket Checkout webhook received', [
            'order_id'   => $shiprocketOrderId ?: null,
            'status'     => $status ?: null,
            'event_type' => $eventType ?: null,
            'top_keys'   => array_keys($data),
            // Truncated raw body for forensics — caps to avoid log bloat.
            'body_preview' => substr($request->getContent(), 0, 1500),
        ]);

        if ($shiprocketOrderId === '') {
            return response()->json(['ok' => true, 'note' => 'no order_id, ignored (likely funnel-stage event)']);
        }

        // Idempotency: if we've already processed this Shiprocket order, return OK.
        $existing = Order::query()
            ->whereJsonContains('metadata->shiprocket_checkout_order_id', $shiprocketOrderId)
            ->first();
        if ($existing) {
            if ($status === 'SUCCESS' && $existing->status === 'pending') {
                $existing->updateStatus('confirmed', null, 'Shiprocket Checkout: payment confirmed via webhook');
            }
            return response()->json(['ok' => true, 'note' => 'already processed', 'order' => $existing->order_number]);
        }

        // Only create local orders for SUCCESS status — INITIATED / CREATED haven't been paid yet.
        if ($status !== 'SUCCESS') {
            return response()->json(['ok' => true, 'note' => "status={$status}, awaiting SUCCESS"]);
        }

        try {
            // The Fastrr webhook carries paymentDetails.transactionId, but
            // fetchOrderDetails needs the canonical sr_order_id returned by
            // /api/v1/access-token/checkout. We persisted that sr_order_id on the
            // originating cart at token-creation time — recover it via cart_attributes.
            $payload = $data;
            $ourCartId = data_get($data, 'cart_attributes.cart_id') ?? data_get($data, 'cart_data.custom_attributes.cart_id');
            $canonicalSrId = $shiprocketOrderId;
            if (! empty($ourCartId)) {
                $ourCart = Cart::find((int) $ourCartId);
                $storedSrId = $ourCart?->metadata['sr_checkout_order_id'] ?? null;
                if ($storedSrId) {
                    $canonicalSrId = (string) $storedSrId;
                }
            }

            // Always fetch the canonical record from Shiprocket when state /
            // postal_code / address_line_1 are missing — Fastrr webhooks omit
            // state, but fetchOrderDetails returns the full address. Also fetch
            // when items / shipping_address are entirely absent (some events
            // are bare notifications).
            $shipping = $payload['shipping_address'] ?? [];
            $needsFetch = empty($payload['cart_data']['items'] ?? null)
                       || empty($shipping)
                       || empty($shipping['state'] ?? null)
                       || empty($shipping['line1'] ?? $shipping['address1'] ?? null)
                       || empty($shipping['pincode'] ?? $shipping['zip'] ?? null);
            if ($needsFetch) {
                $fetched = $this->service->fetchOrderDetails($canonicalSrId);
                if ($fetched) {
                    Log::info('Shiprocket Checkout webhook: enriched via fetchOrderDetails', [
                        'webhook_id'      => $shiprocketOrderId,
                        'canonical_sr_id' => $canonicalSrId,
                    ]);
                    $payload = $fetched;
                } else {
                    Log::warning('Shiprocket Checkout webhook: fetchOrderDetails came back empty', [
                        'webhook_id'      => $shiprocketOrderId,
                        'canonical_sr_id' => $canonicalSrId,
                    ]);
                }
            }

            // Use canonical sr_order_id as the dedup key — survives across the
            // multiple webhook events Fastrr fires for one purchase.
            $dedupKey = $canonicalSrId ?: $shiprocketOrderId;
            $order = DB::transaction(function () use ($payload, $dedupKey) {
                return $this->createOrderFromWebhook($payload, $dedupKey);
            });

            event(new OrderPlaced($order));

            return response()->json(['ok' => true, 'order' => $order->order_number]);
        } catch (\Throwable $e) {
            Log::error('Shiprocket Checkout webhook: order creation failed', [
                'sr_order' => $shiprocketOrderId,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Extract [order_id, status, event_type] from common Shiprocket webhook shapes.
     * Returns ['', '', ''] when none match (e.g. early funnel-stage events).
     */
    private function extractIds(array $data): array
    {
        // FASTRR_FIELD_FIX_APPLIED — Shiprocket Fastrr payload shape:
        //   - latest_stage: INIT | PHONE_VERIFIED | ADDRESS_PROVIDED | PAYMENT_INITIATED | ORDER_PLACED | PAYMENT_FAILED
        //   - cart_id:      "69fb3a8f0e54003a627c73e7" (unique per checkout session)
        //   - paymentDetails.transactionId: "28480581177" (PayU/etc gateway txn)
        //
        // Legacy (kept as fallback): order_id / status / event_type fields used
        // by older Shiprocket Checkout API versions.

        // Modern Fastrr path
        $latestStage = (string) (data_get($data, 'latest_stage') ?? '');
        $cartId      = (string) (data_get($data, 'cart_id') ?? '');
        $txnId       = (string) (data_get($data, 'paymentDetails.transactionId') ?? '');

        if ($latestStage !== '' && $cartId !== '') {
            // Use payment txn id as the canonical key when present (survives cart
            // recreation), else fall back to cart_id.
            $orderId = $txnId !== '' ? $txnId : $cartId;
            // Map Fastrr stage → our internal SUCCESS marker.
            $status  = $latestStage === 'ORDER_PLACED' ? 'SUCCESS'
                     : ($latestStage === 'PAYMENT_FAILED' ? 'FAILED' : strtoupper($latestStage));
            return [$orderId, $status, $latestStage];
        }

        // Legacy paths
        $paths = [
            ['order_id', 'status'],
            ['data.order_id', 'data.status'],
            ['result.order_id', 'result.status'],
            ['payload.order_id', 'payload.status'],
            ['order.order_id', 'order.status'],
            ['order.id', 'order.status'],
        ];
        foreach ($paths as [$idKey, $statusKey]) {
            $id = data_get($data, $idKey);
            if (! empty($id)) {
                return [
                    (string) $id,
                    strtoupper((string) (data_get($data, $statusKey) ?? '')),
                    (string) (data_get($data, 'event') ?? data_get($data, 'event_type') ?? data_get($data, 'stage') ?? ''),
                ];
            }
        }
        return ['', '', (string) (data_get($data, 'event') ?? data_get($data, 'event_type') ?? data_get($data, 'stage') ?? '')];
    }

    /**
     * Webhook authentication.
     *
     * Shiprocket Checkout's dashboard lets you configure a custom header
     * (name + value) it includes on every webhook — this is the primary
     * auth method for the order webhook. We also accept HMAC signatures
     * (X-Api-HMAC-SHA256 of raw body) for parity with the catalog webhooks
     * which use that scheme.
     *
     * If neither token nor secret is configured, calls are logged as a loud
     * warning and allowed through — initial-setup safety valve only.
     */
    private function authenticateWebhook(Request $request): bool
    {
        $headerName  = (string) config('services.shiprocket_checkout.webhook_header_name', '');
        $expectedTok = (string) config('services.shiprocket_checkout.webhook_token', '');
        $apiSecret   = (string) config('services.shiprocket_checkout.secret', '');

        // Method 1 — custom header shared-secret (matches dashboard config)
        if ($headerName !== '' && $expectedTok !== '') {
            $received = (string) ($request->header($headerName) ?? '');
            if ($received !== '' && hash_equals($expectedTok, $received)) {
                return true;
            }
            // Header configured but missing/wrong → reject. Log enough to debug
            // a misconfigured Shiprocket dashboard without leaking the secret.
            if ($received === '') {
                Log::warning('Shiprocket Checkout webhook: expected header missing', [
                    'header_name'    => $headerName,
                    'received_keys'  => array_keys($request->headers->all()),
                ]);
            } else {
                Log::warning('Shiprocket Checkout webhook: header token mismatch', [
                    'header_name' => $headerName,
                ]);
            }
        }

        // Method 2 — HMAC SHA256 (Base64) of raw body
        $signature = $request->header('X-Api-HMAC-SHA256') ?? $request->header('x-api-hmac-sha256');
        if ($apiSecret !== '' && $signature) {
            if ($this->service->verifyWebhookSignature($request->getContent(), $signature)) {
                return true;
            }
            Log::warning('Shiprocket Checkout webhook: HMAC signature mismatch');
        }

        // No auth configured at all — initial-setup-only safety valve.
        if ($headerName === '' && $expectedTok === '' && $apiSecret === '') {
            Log::warning('Shiprocket Checkout webhook: no auth configured, allowing through');
            return true;
        }

        return false;
    }

    private function createOrderFromWebhook(array $data, string $shiprocketOrderId): Order
    {
        // Fastrr ships items at top level (not nested under cart_data).
        $shipping = $data['shipping_address'] ?? [];
        $billing  = $data['billing_address']  ?? $shipping;
        $items    = $data['cart_data']['items'] ?? ($data['items'] ?? []);
        $customAttrs = $data['cart_data']['custom_attributes'] ?? ($data['cart_attributes'] ?? []);

        if (empty($items)) {
            throw new \RuntimeException('Webhook payload has no cart items');
        }

        // User: link to existing user if email matches; else leave as guest.
        $email = $data['email'] ?? ($shipping['email'] ?? null);
        $user  = $email ? User::where('email', $email)->first() : null;

        // Shipping/billing snapshots in the shape native checkout uses.
        $shippingSnapshot = $this->mapAddressSnapshot($shipping, $email, $data['phone'] ?? null);
        $billingSnapshot  = $this->mapAddressSnapshot($billing,  $email, $data['phone'] ?? null);

        // Resolve the originating cart so we can clear it once the order lands.
        $cart = $this->resolveCart($customAttrs);

        // Build OrderItems by looking each variant_id back up in our DB.
        // Shiprocket sends only variant_id + quantity in cart_data; the catalog
        // feed is the source of truth for product names/images.
        $orderItems = $this->buildOrderItems($items, $cart);

        $subtotal = (float) ($data['subtotal_price'] ?? collect($orderItems)->sum('total'));
        $shippingCharges = (float) ($data['shipping_charges'] ?? 0);
        $totalDiscount   = (float) ($data['total_discount']    ?? 0);
        $totalPayable    = (float) ($data['total_amount_payable'] ?? max(0, $subtotal + $shippingCharges - $totalDiscount));

        $paymentType   = strtoupper((string) ($data['payment_type'] ?? 'PREPAID'));
        $paymentStatus = $this->mapPaymentStatus($data['payment_status'] ?? null);

        $order = Order::create([
            'user_id'                   => $user?->id,
            'status'                    => 'confirmed',
            'payment_status'            => $paymentStatus,
            'subtotal'                  => $subtotal,
            'discount'                  => $totalDiscount,
            'tax'                       => 0, // GST-inclusive in line prices
            'shipping_cost'             => $shippingCharges,
            'total'                     => $totalPayable,
            'paid_amount'               => $paymentType === 'PREPAID' ? $totalPayable : 0,
            'currency'                  => 'INR',
            'shipping_address_snapshot' => $shippingSnapshot,
            'billing_address_snapshot'  => $billingSnapshot,
            // `source` column is an enum (web/mobile/pos/api). Use 'web' and
            // mark the SR-Checkout origin via metadata.payment_method.
            'source'                    => 'web',
            'expected_delivery_date'    => ! empty($data['edd']) ? date('Y-m-d', strtotime((string) $data['edd'])) : null,
            'metadata' => [
                'payment_method'                  => 'shiprocket_checkout',
                'shiprocket_checkout_order_id'    => $shiprocketOrderId,
                'shiprocket_fastrr_order_id'      => $data['fastrr_order_id'] ?? null,
                'shiprocket_platform_order_id'    => $data['platform_order_id'] ?? null,
                'shiprocket_payment_type'         => $paymentType,
                'shiprocket_payment_status'       => $data['payment_status'] ?? null,
                'shiprocket_source'               => $data['source'] ?? null,
                'shiprocket_shipping_plan'        => $data['shipping_plan'] ?? null,
                'shiprocket_rto_prediction'       => $data['rto_prediction'] ?? null,
                'shiprocket_coupon_codes'         => $data['coupon_codes'] ?? null,
                'shiprocket_loyalty_points'       => $data['loyalty_points_applied'] ?? null,
                'shiprocket_payments'             => $data['payments'] ?? null,
                'shiprocket_tags'                 => $data['tags'] ?? null,
                'guest_email'                     => ! $user ? $email : null,
                'guest_phone'                     => ! $user ? ($data['phone'] ?? null) : null,
                'guest_name'                      => ! $user ? trim(($shipping['first_name'] ?? '') . ' ' . ($shipping['last_name'] ?? '')) : null,
            ],
            'confirmed_at' => now(),
        ]);

        // Persist line items
        foreach ($orderItems as $itemData) {
            $order->items()->create($itemData);
        }

        // Status history entry
        $order->statusHistory()->create([
            'status'  => 'confirmed',
            'comment' => 'Order confirmed via Shiprocket Checkout webhook',
        ]);

        // Decrement stock for each item — guarded stock decrement
        // (atomic + bounded, won't go below zero so MySQL UNSIGNED won't underflow).
        // If a row has insufficient stock we log + continue rather than blow up the
        // whole webhook — the order has been paid; we can't refuse it now.
        foreach ($orderItems as $itemData) {
            $qty = (int) $itemData['quantity'];
            if (! empty($itemData['variant_id'])) {
                $affected = ProductVariant::where('id', $itemData['variant_id'])
                    ->where('stock_quantity', '>=', $qty)
                    ->decrement('stock_quantity', $qty);
                if (! $affected) {
                    Log::warning('Shiprocket Checkout webhook: insufficient variant stock — order accepted but stock NOT decremented', [
                        'variant_id' => $itemData['variant_id'],
                        'qty'        => $qty,
                    ]);
                }
            } elseif (! empty($itemData['product_id'])) {
                $affected = Product::where('id', $itemData['product_id'])
                    ->where('stock_quantity', '>=', $qty)
                    ->decrement('stock_quantity', $qty);
                if (! $affected) {
                    Log::warning('Shiprocket Checkout webhook: insufficient product stock — order accepted but stock NOT decremented', [
                        'product_id' => $itemData['product_id'],
                        'qty'        => $qty,
                    ]);
                }
            }
        }

        // Clear the originating cart so the storefront UI matches the order.
        if ($cart) {
            $cart->items()->delete();
            $cart->update(['coupon_id' => null, 'discount' => 0, 'subtotal' => 0, 'total' => 0]);
        }

        Log::info('Shiprocket Checkout: order created', [
            'order_number' => $order->order_number,
            'sr_order_id'  => $shiprocketOrderId,
            'total'        => $totalPayable,
        ]);

        return $order;
    }

    private function mapAddressSnapshot(array $addr, ?string $emailFallback, ?string $phoneFallback): array
    {
        // Shiprocket sends two different address shapes:
        //   - fetchOrderDetails / docs:  line1, line2, pincode
        //   - Fastrr live webhook:       address1, address2, zip
        // We normalise both into our canonical snapshot.
        $name = trim(($addr['first_name'] ?? '') . ' ' . ($addr['last_name'] ?? ''));
        if ($name === '' && ! empty($addr['name'])) {
            $name = (string) $addr['name'];
        }
        return [
            'name'           => $name !== '' ? $name : null,
            'email'          => $addr['email'] ?? $emailFallback,
            'phone'          => $addr['phone'] ?? $phoneFallback,
            'address_line_1' => $addr['line1']   ?? $addr['address1'] ?? null,
            'address_line_2' => $addr['line2']   ?? $addr['address2'] ?? null,
            'city'           => $addr['city']    ?? null,
            'state'          => $addr['state']   ?? null,
            'postal_code'    => $addr['pincode'] ?? $addr['zip']      ?? null,
            'country'        => $addr['country'] ?? 'India',
            'landmark'       => $addr['landmark'] ?? null,
        ];
    }

    private function mapPaymentStatus(?string $srStatus): string
    {
        return match (strtolower((string) $srStatus)) {
            'success' => 'paid',
            'pending' => 'pending',
            'failed'  => 'failed',
            default   => 'pending',
        };
    }

    private function buildOrderItems(array $items, ?Cart $originatingCart): array
    {
        $rows = [];
        foreach ($items as $item) {
            $variantId = $item['variant_id'] ?? null;
            $qty       = (int) ($item['quantity'] ?? 1);
            if (! $variantId || $qty < 1) {
                continue;
            }

            // Try matching variant first, then product (catalog feed uses
            // product.id as the synthesised variant.id).
            $variant = ProductVariant::find($variantId);
            $product = $variant?->product ?? Product::find($variantId);

            if (! $product) {
                Log::warning('Shiprocket webhook: unknown variant_id', ['variant_id' => $variantId]);
                continue;
            }

            $price = (float) ($variant->price ?? $product->price);
            $rows[] = [
                'product_id'       => $product->id,
                'variant_id'       => $variant?->id,
                'seller_id'        => $product->seller_id ?? null,
                'product_name'     => $product->name,
                'variant_name'     => $variant?->name,
                'sku'              => $variant?->sku ?: ($product->sku ?: ('FK-' . $product->id)),
                'mrp'              => (float) ($product->mrp ?? $price),
                'price'            => $price,
                'quantity'         => $qty,
                'tax'              => 0,
                'discount'         => 0,
                'total'            => round($price * $qty, 2),
                'product_snapshot' => [
                    'name'        => $product->name,
                    'slug'        => $product->slug,
                    'image_url'   => $product->primary_image_url,
                    'sku'         => $product->sku,
                    'hsn_code'    => $product->hsn_code ?? null,
                    'is_taxable'  => $product->is_taxable ?? false,
                    'tax_rate'    => $product->tax_rate ?? 0,
                ],
                'status'           => 'pending',
            ];
        }
        return $rows;
    }

    private function resolveCart(array $customAttrs): ?Cart
    {
        $cartId    = $customAttrs['cart_id']    ?? null;
        $sessionId = $customAttrs['session_id'] ?? null;
        $userId    = $customAttrs['user_id']    ?? null;

        if (! empty($cartId) && is_numeric($cartId)) {
            return Cart::find((int) $cartId);
        }
        if (! empty($sessionId)) {
            return Cart::where('session_id', $sessionId)->first();
        }
        if (! empty($userId) && is_numeric($userId)) {
            return Cart::where('user_id', (int) $userId)->first();
        }
        return null;
    }
}
