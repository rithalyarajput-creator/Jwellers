<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\Order;
use App\Services\ShiprocketCheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShiprocketCheckoutController extends Controller
{
    public function __construct(private ShiprocketCheckoutService $service)
    {
    }

    /**
     * Create a Shiprocket Checkout access token for the current cart.
     *
     * Frontend POSTs here when the user clicks "Buy Now" / "Checkout via
     * Shiprocket". Response carries the token + the JS/CSS URLs the frontend
     * needs to load before invoking HeadlessCheckout.addToCart().
     *
     * Server-side prices are the source of truth — we never trust posted values.
     */
    public function initiate(Request $request): JsonResponse
    {
        if (! $this->service->isEnabled()) {
            return $this->fallbackResponse('Express checkout is currently unavailable.');
        }

        $cart = $this->resolveCart();
        if (! $cart || $cart->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Your cart is empty.',
            ], 422);
        }

        $cart->recalculate();
        $cart->refresh();
        $cart->load(['items.product', 'items.variant', 'coupon']);

        try {
            $session = $this->service->createSession($cart);

            return response()->json([
                'success'      => true,
                'token'        => $session['token'],
                'expires_at'   => $session['expires_at'] ?? null,
                'sr_order_id'  => $session['sr_order_id'] ?? null,
                'reference'    => $session['reference'],
                'ui_script'    => $this->service->uiScriptUrl(),
                'ui_style'     => $this->service->uiStyleUrl(),
                'fallback_url' => route('checkout.index'),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Shiprocket Checkout: initiate failed, falling back', [
                'error'   => $e->getMessage(),
                'cart_id' => $cart->id,
            ]);

            return $this->fallbackResponse(
                'Couldn\'t start express checkout. Redirecting to standard checkout.'
            );
        }
    }

    /**
     * Browser-side return URL Shiprocket appends ?oid=&ost= to after checkout.
     * For SUCCESS we send the user to a thank-you page; for FAILED/CANCELLED
     * we send them back to cart with a flash message.
     *
     * The actual order is created server-side by the webhook (see
     * ShiprocketCheckoutWebhookController). We do NOT create orders here —
     * webhooks are the authoritative source.
     */
    public function return(Request $request): SymfonyResponse
    {
        $shiprocketOrderId = (string) $request->query('oid', '');
        $status            = strtoupper((string) $request->query('ost', ''));

        Log::info('Shiprocket Checkout: return hit', [
            'oid' => $shiprocketOrderId,
            'ost' => $status,
        ]);

        if ($status === 'SUCCESS' && $shiprocketOrderId !== '') {
            // The webhook may not have arrived yet. Look up the local order if
            // it has been written; otherwise show a generic thank-you page.
            $order = Order::query()
                ->whereJsonContains('metadata->shiprocket_checkout_order_id', $shiprocketOrderId)
                ->first();

            if ($order) {
                return redirect()
                    ->route('checkout.success', $order)
                    ->with('success', 'Order placed successfully.');
            }

            // Webhook still in flight — show a friendly "Order placed, finalising"
            // page that polls /checkout/shiprocket/order-status every 2s and
            // auto-advances to /checkout/success/{order} once the webhook lands.
            // (Better UX than dumping the user back on the cart page with a flash.)
            return response()->view('checkout.processing', [
                'srOrderId' => $shiprocketOrderId,
            ]);
        }

        return redirect()
            ->route('cart.index')
            ->with('error', 'Express checkout was not completed. Your cart is intact.');
    }

    private function fallbackResponse(string $message): JsonResponse
    {
        return response()->json([
            'success'      => false,
            'fallback_url' => route('checkout.index'),
            'message'      => $message,
        ], 200); // 200 so the frontend treats fallback as a planned outcome.
    }

    private function resolveCart(): ?Cart
    {
        if (auth()->check()) {
            return Cart::with(['items.product', 'items.variant', 'coupon'])
                ->where('user_id', auth()->id())
                ->first();
        }

        return Cart::with(['items.product', 'items.variant', 'coupon'])
            ->where('session_id', session()->getId())
            ->first();
    }


    /**
     * AJAX endpoint polled by the processing view. Looks up the local order by
     * the Shiprocket order id stored in metadata. Returns redirect URL once found.
     *
     *   GET /checkout/shiprocket/order-status?oid=<sr_order_id>
     *
     * Response: { found: bool, order_id?: int, redirect_url?: string }
     */
    public function orderStatus(Request $request): JsonResponse
    {
        $oid = (string) $request->query('oid', '');
        if ($oid === '') {
            return response()->json(['found' => false], 422);
        }

        $order = Order::query()
            ->whereJsonContains('metadata->shiprocket_checkout_order_id', $oid)
            ->first();

        if (! $order) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found'        => true,
            'order_id'     => $order->id,
            'redirect_url' => route('checkout.success', $order),
        ]);
    }

}
