<?php

namespace App\Services\Nia;

use App\DTOs\Messaging\IntentDTO;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cheap pre-step: classify customer intent + extract structured filters
 * using Claude Haiku before invoking the main reply model.
 *
 * Cached for 1h by message hash so repeated identical questions skip the call.
 */
class IntentExtractor
{
    private const CACHE_TTL_SECONDS = 3600;
    private const MODEL = 'claude-haiku-4-5-20251001';

    public function extract(string $message): IntentDTO
    {
        $apiKey = config('services.anthropic.key');
        if (empty($apiKey)) {
            return new IntentDTO(intent: IntentDTO::INTENT_OTHER, confidence: 0.0);
        }

        $cacheKey = 'nia:intent:' . sha1($message);

        return Cache::remember($cacheKey, self::CACHE_TTL_SECONDS, function () use ($apiKey, $message) {
            return $this->callHaiku($apiKey, $message);
        });
    }

    private function callHaiku(string $apiKey, string $message): IntentDTO
    {
        $system = <<<'SYS'
You classify a customer's Instagram/Facebook/WhatsApp DM to ForeverKids (premium kids' clothing store).

Return ONLY valid JSON matching:
{"intent": "browse|sizing|price|order_status|returns|human|small_talk|other",
 "filters": {"age_band": "0-3m|3-12m|1-3y|3-6y|6-10y|10-15y|null",
             "gender": "girl|boy|unisex|null",
             "occasion": "party|casual|wedding|festive|school|null",
             "color": "string|null",
             "max_price": number_or_null,
             "category_keywords": ["frock","tshirt","etc"]},
 "ask_for_more": true_if_message_is_too_vague_to_search,
 "confidence": 0.0_to_1.0}

Rules:
- "browse" = wants to see products
- "sizing" = asking about size charts or fit
- "price" = asking how much something costs
- "order_status" = asking about an existing order
- "returns" = exchange / refund / return
- "human" = explicitly wants to speak to a person
- "small_talk" = greetings, thanks, no real ask
- Use null for any filter you cannot confidently extract. Do not guess.
SYS;

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post('https://api.anthropic.com/v1/messages', [
                    'model'      => self::MODEL,
                    'max_tokens' => 400,
                    'system'     => $system,
                    'messages'   => [[
                        'role'    => 'user',
                        'content' => $message,
                    ]],
                ]);

            if ($response->failed()) {
                Log::warning('Nia: intent extractor non-200', [
                    'status' => $response->status(),
                ]);
                return new IntentDTO(intent: IntentDTO::INTENT_OTHER, confidence: 0.0);
            }

            $text = $response->json('content.0.text', '');
            return $this->parseIntent($text);
        } catch (\Throwable $e) {
            Log::warning('Nia: intent extractor exception', ['error' => $e->getMessage()]);
            return new IntentDTO(intent: IntentDTO::INTENT_OTHER, confidence: 0.0);
        }
    }

    private function parseIntent(string $text): IntentDTO
    {
        // Haiku sometimes wraps JSON in code fences. Strip and find first {...}.
        if (preg_match('/\{.*\}/s', $text, $m)) {
            $text = $m[0];
        }

        $data = json_decode($text, true);
        if (!is_array($data)) {
            return new IntentDTO(intent: IntentDTO::INTENT_OTHER, confidence: 0.0);
        }

        $intent = (string) ($data['intent'] ?? IntentDTO::INTENT_OTHER);
        $filters = is_array($data['filters'] ?? null) ? $data['filters'] : [];
        // Drop nulls so downstream filters can use isset() cleanly.
        $filters = array_filter($filters, fn ($v) => $v !== null && $v !== '' && $v !== []);

        return new IntentDTO(
            intent:     $intent,
            filters:    $filters,
            askForMore: (bool) ($data['ask_for_more'] ?? false),
            confidence: (float) ($data['confidence'] ?? 0.5),
        );
    }
}
