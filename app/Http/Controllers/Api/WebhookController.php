<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingMessage;
use App\Mappers\MetaWebhookMapper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * Meta unified webhook endpoint for Instagram DM, Facebook Messenger,
 * and WhatsApp Business. Verification is signed by VerifyMetaWebhookSignature
 * middleware (HMAC SHA-256).
 *
 * The handler keeps the request fast (<500ms) by mapping the payload to DTOs
 * and dispatching a queue job per inbound message. Meta requires 200 within
 * ~5s or it marks the endpoint failed and retries.
 */
class WebhookController extends Controller
{
    /**
     * GET /api/webhook/meta — Meta verification handshake.
     */
    public function verify(Request $request): Response
    {
        $mode      = $request->query('hub_mode');
        $token     = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');
        $expected  = config('services.meta.verify_token');

        if ($mode === 'subscribe' && is_string($token) && is_string($expected) && hash_equals($expected, $token)) {
            Log::info('Nia: webhook verified');
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('Nia: webhook verification failed', ['mode' => $mode]);
        return response('Forbidden', 403);
    }

    /**
     * POST /api/webhook/meta — receive DM events.
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        Log::debug('Nia: webhook payload received', ['object' => $payload['object'] ?? 'unknown']);

        $messages = MetaWebhookMapper::fromPayload($payload);
        foreach ($messages as $dto) {
            ProcessIncomingMessage::dispatch($dto->toArray());
        }

        return response()->json([
            'status'    => 'ok',
            'queued'    => count($messages),
        ]);
    }
}
