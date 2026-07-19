<?php

namespace App\Services;

use App\Models\Lead;
use App\Models\Setting;
use App\Services\Nia\NiaToolHandler;
use App\Services\Nia\ProductLookupService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Generates Nia's reply. Uses Anthropic Messages API with tool-use so Claude
 * can query the live product catalog before recommending anything.
 *
 * Returns a structured result containing the cleaned reply text plus any
 * product matches surfaced via lookup_products tool calls (so MessagingService
 * can render a carousel).
 */
class ClaudeService
{
    private const MAX_HISTORY = 20;
    private const MAX_TOOL_ITERATIONS = 3;
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    public function __construct(
        private readonly NiaToolHandler $tools,
        private readonly ProductLookupService $products,
    ) {
    }

    /**
     * @return array{
     *     text: string,
     *     products: \App\DTOs\Messaging\ProductMatchDTO[],
     *     handoff: bool,
     *     tool_calls: int
     * }
     */
    public function generateReply(Lead $lead, string $message): array
    {
        $apiKey = config('services.anthropic.key');

        if (empty($apiKey)) {
            Log::error('Nia: Anthropic API key not configured');
            return $this->errorReply("I'm currently unavailable. Please try again later!");
        }

        $systemPrompt = $this->buildSystemPrompt($lead);
        $messages = $this->buildMessageHistory($lead, $message);
        $model = Setting::get('nia_model', 'claude-sonnet-4-6');

        $surfacedProducts = [];
        $toolCalls = 0;

        for ($i = 0; $i < self::MAX_TOOL_ITERATIONS; $i++) {
            $response = $this->callApi($apiKey, $model, $systemPrompt, $messages);
            if ($response === null) {
                return $this->errorReply("I'm having trouble right now. Let me get back to you shortly!");
            }

            $stopReason = $response['stop_reason'] ?? null;
            $contentBlocks = $response['content'] ?? [];

            // Append the assistant turn (with tool_use blocks) to the running conversation.
            $messages[] = ['role' => 'assistant', 'content' => $contentBlocks];

            if ($stopReason !== 'tool_use') {
                $text = $this->extractText($contentBlocks);
                return [
                    'text'       => $text,
                    'products'   => $surfacedProducts,
                    'handoff'    => false,
                    'tool_calls' => $toolCalls,
                ];
            }

            // Execute every tool_use block in this turn.
            $toolResults = [];
            foreach ($contentBlocks as $block) {
                if (($block['type'] ?? '') !== 'tool_use') {
                    continue;
                }
                $toolCalls++;
                $name = (string) ($block['name'] ?? '');
                $input = (array) ($block['input'] ?? []);
                $result = $this->tools->execute($name, $input, $lead);

                if ($name === 'lookup_products' && !empty($result['products'])) {
                    $surfacedProducts = $this->mergeMatches($surfacedProducts, $input);
                }

                $toolResults[] = [
                    'type'        => 'tool_result',
                    'tool_use_id' => $block['id'] ?? '',
                    'content'     => json_encode($result, JSON_UNESCAPED_SLASHES),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        // Loop exhausted without natural stop — return whatever text Claude last produced.
        $finalText = $this->extractText($contentBlocks ?? []);
        Log::warning('Nia: tool-use loop hit max iterations', [
            'lead_id'    => $lead->id,
            'tool_calls' => $toolCalls,
        ]);
        return [
            'text'       => $finalText !== '' ? $finalText : "Let me connect you with our team for a faster answer.",
            'products'   => $surfacedProducts,
            'handoff'    => true,
            'tool_calls' => $toolCalls,
        ];
    }

    /**
     * Re-run the lookup with the same input so we capture DTOs (Claude only
     * sees the JSON, not the DTOs).
     *
     * @return \App\DTOs\Messaging\ProductMatchDTO[]
     */
    private function mergeMatches(array $existing, array $input): array
    {
        $matches = $this->products->search($input);
        $byId = [];
        foreach (array_merge($existing, $matches) as $m) {
            $byId[$m->id] = $m;
        }
        return array_values($byId);
    }

    private function callApi(string $apiKey, string $model, string $systemPrompt, array $messages): ?array
    {
        try {
            $response = Http::timeout(25)
                ->withHeaders([
                    'x-api-key'         => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ])
                ->post(self::API_URL, [
                    'model'      => $model,
                    'max_tokens' => 1024,
                    'system'     => $systemPrompt,
                    'tools'      => NiaToolHandler::toolDefinitions(),
                    'messages'   => $messages,
                ]);

            if ($response->failed()) {
                Log::error('Nia: Anthropic API error', [
                    'status' => $response->status(),
                    'body'   => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('Nia: Anthropic connection timeout', ['error' => $e->getMessage()]);
            return null;
        } catch (\Throwable $e) {
            Log::error('Nia: Anthropic call exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function extractText(array $contentBlocks): string
    {
        $parts = [];
        foreach ($contentBlocks as $block) {
            if (($block['type'] ?? '') === 'text' && !empty($block['text'])) {
                $parts[] = $block['text'];
            }
        }
        return trim(implode("\n", $parts));
    }

    private function errorReply(string $text): array
    {
        return [
            'text'       => $text,
            'products'   => [],
            'handoff'    => false,
            'tool_calls' => 0,
        ];
    }

    private function buildSystemPrompt(Lead $lead): string
    {
        $customPrompt = Setting::get('nia_system_prompt', '');
        $prompt = !empty($customPrompt) ? $customPrompt : $this->defaultSystemPrompt();

        $prompt .= "\n\n## Current Customer\n";
        $prompt .= "- Platform: {$lead->platform}\n";
        $prompt .= '- Name: ' . ($lead->name ?? 'Unknown') . "\n";
        $prompt .= "- Stage: {$lead->stage}\n";
        if ($lead->notes) {
            $prompt .= "- Previous context: {$lead->notes}\n";
        }
        if ($lead->tags) {
            $prompt .= '- Tags: ' . implode(', ', (array) $lead->tags) . "\n";
        }

        $prompt .= "\n## Tools (MUST USE)\n"
            . "- lookup_products: ALWAYS call this before recommending anything. Never invent SKUs, prices, or availability.\n"
            . "- get_size_chart_url: Use when the customer asks about sizing.\n"
            . "- create_order_link: Use when sharing a specific product link, especially with a chosen size.\n";

        $prompt .= "\n## Special Commands (stripped before send)\n"
            . "- [NIA_QUALIFIED] — strong buying intent or ready to purchase.\n"
            . "- [SCHEDULE_CALL] — customer asked for a callback OR you cannot help and want a human to take over.\n"
            . "- [LEAD_CONTEXT:short note] — save context about this customer.\n";

        return $prompt;
    }

    private function defaultSystemPrompt(): string
    {
        return <<<'PROMPT'
You are Nia, the friendly AI sales and support assistant for Jwellers — a premium jewellery e-commerce store in India.

## Your Personality
- Warm, caring, and enthusiastic about helping customers find the perfect piece.
- Professional but conversational — this is social media messaging, keep it natural.
- Smart, persuasive but never pushy. Guide customers towards making a purchase.
- Concise: keep responses under 100 words for chat platforms. No long paragraphs.
- Use emojis sparingly and naturally (1-2 per message max).
- Never fabricate product details, prices, or policies.

## What You Do
- Answer questions about products, sizes, availability, pricing — using tools.
- Recommend products based on the customer's style, occasion, metal, and budget.
- Qualify leads by understanding their needs, budget, and timeline.
- Handle objections gracefully (price concerns, sizing doubts, shipping questions).
- Close sales by guiding customers to the website or sharing product links.
- Schedule callbacks when customers prefer speaking with a human.
- Track and remember context about each customer across conversations.

## Store Information
- Website: https://jwellers.in
- Free shipping on orders above ₹499
- 7-day return policy (unused items with tags)
- Payments: UPI, cards, net banking, wallets, COD (up to ₹5,000)
- Sizes: Ring sizes and chain lengths available on request
- Contact: available via Instagram, Facebook, and WhatsApp

## Response Style
- Plain text only. Use bullet points (- ) for lists.
- Bold (**text**) only for prices, coupon codes, or critical info.
- No markdown headers. Keep it conversational.
- End messages with a soft call-to-action when appropriate.
- If unsure about something, be honest, use [SCHEDULE_CALL] and offer to connect with the team.
PROMPT;
    }

    private function buildMessageHistory(Lead $lead, string $currentMessage): array
    {
        $chats = $lead->chats()
            ->orderBy('created_at', 'desc')
            ->limit(self::MAX_HISTORY)
            ->get()
            ->sortBy('created_at')
            ->values();

        $messages = [];
        $lastRole = null;

        foreach ($chats as $chat) {
            $role = $chat->sender === 'customer' ? 'user' : 'assistant';
            if ($role === $lastRole && !empty($messages)) {
                // Coalesce consecutive same-role into one turn (tool-use needs alternation).
                $messages[count($messages) - 1]['content'] .= "\n" . $chat->message;
            } else {
                $messages[] = ['role' => $role, 'content' => $chat->message];
                $lastRole = $role;
            }
        }

        if ($lastRole === 'user' && !empty($messages)) {
            $messages[count($messages) - 1]['content'] .= "\n" . $currentMessage;
        } else {
            $messages[] = ['role' => 'user', 'content' => $currentMessage];
        }

        return $messages;
    }
}
