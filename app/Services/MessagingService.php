<?php

namespace App\Services;

use App\DTOs\Messaging\IncomingMessageDTO;
use App\DTOs\Messaging\OutgoingReplyDTO;
use App\DTOs\Messaging\ProductMatchDTO;
use App\Models\Lead;
use App\Models\LeadChat;
use App\Models\Setting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Orchestrates the full Nia conversation:
 *   dedupe -> find/create lead -> store inbound -> Claude (tool-use) ->
 *   process AI commands -> store outbound -> send reply (text or carousel)
 *
 * Handoff to humans is triggered by:
 *   - [SCHEDULE_CALL] command from Claude
 *   - Returns/refund/order regex on inbound text
 *   - 'human' / 'agent' / 'person' keyword from customer
 *   - Claude tool-use loop exhausted (handoff flag set in result)
 */
class MessagingService
{
    private const HUMAN_KEYWORD_PATTERN = '/\b(human|agent|person|representative|talk to someone)\b/i';
    private const SENSITIVE_PATTERN     = '/\b(refund|return|exchange|cancel(?:lation)?|order\s*(?:status|id|#)|where.{0,10}order)\b/i';

    public function __construct(
        private readonly ClaudeService $claude,
    ) {
    }

    public function processIncoming(IncomingMessageDTO $dto): array
    {
        if ($dto->messageId && LeadChat::where('platform_message_id', $dto->messageId)->exists()) {
            Log::info('Nia: duplicate message skipped', ['message_id' => $dto->messageId]);
            return ['status' => 'duplicate'];
        }

        $lead = $this->findOrCreateLead($dto);

        LeadChat::create([
            'lead_id'             => $lead->id,
            'sender'              => 'customer',
            'message'             => $dto->text,
            'platform_message_id' => $dto->messageId,
        ]);

        if (!Setting::get('nia_enabled', true)) {
            return ['status' => 'bot_disabled', 'lead_id' => $lead->id];
        }

        // Hard-route sensitive intents to human BEFORE calling Claude.
        if ($this->isHumanHandoffRequested($dto->text)) {
            return $this->handoff($lead, $dto, 'keyword');
        }

        $result = $this->claude->generateReply($lead, $dto->text);
        $cleanReply = $this->processAiCommands($lead, $result['text']);

        LeadChat::create([
            'lead_id' => $lead->id,
            'sender'  => 'bot',
            'message' => $cleanReply,
        ]);

        $reply = new OutgoingReplyDTO(
            platform:            $dto->platform,
            recipientPlatformId: $dto->senderPlatformId,
            text:                $cleanReply,
            products:            $result['products'],
        );

        $sent = $this->sendReply($reply);

        if ($result['handoff']) {
            $this->tagForHandoff($lead, 'tool_loop_exhausted');
        }

        return [
            'status'     => $sent ? 'sent' : 'send_failed',
            'lead_id'    => $lead->id,
            'reply'      => $cleanReply,
            'products'   => count($result['products']),
            'tool_calls' => $result['tool_calls'],
        ];
    }

    public function findOrCreateLead(IncomingMessageDTO $dto): Lead
    {
        $lead = Lead::firstOrCreate(
            ['platform' => $dto->platform, 'platform_id' => $dto->senderPlatformId],
            ['stage' => 'new'],
        );

        // Capture display name on first encounter (mostly WhatsApp).
        if ($dto->senderName && empty($lead->name)) {
            $lead->update(['name' => $dto->senderName]);
        }

        return $lead;
    }

    public function sendReply(OutgoingReplyDTO $reply): bool
    {
        $token = config('services.meta.page_access_token');
        if (empty($token)) {
            Log::error('Nia: META_PAGE_ACCESS_TOKEN not configured');
            return false;
        }

        try {
            return match ($reply->platform) {
                'whatsapp'           => $this->sendWhatsApp($reply, $token),
                'instagram',
                'facebook'           => $this->sendMessenger($reply, $token),
                default              => false,
            };
        } catch (\Throwable $e) {
            Log::error('Nia: send reply failed', [
                'platform' => $reply->platform,
                'error'    => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function processAiCommands(Lead $lead, string $reply): string
    {
        $clean = $reply;

        if (str_contains($clean, '[NIA_QUALIFIED]')) {
            $lead->update(['stage' => 'qualified']);
            $clean = str_replace('[NIA_QUALIFIED]', '', $clean);
        }

        if (str_contains($clean, '[SCHEDULE_CALL]')) {
            $this->tagForHandoff($lead, 'schedule_call');
            $clean = str_replace('[SCHEDULE_CALL]', '', $clean);
        }

        if (preg_match_all('/\[LEAD_CONTEXT:(.+?)\]/', $clean, $matches)) {
            foreach ($matches[1] as $context) {
                $context = trim($context);
                $existing = $lead->notes ?? '';
                $lead->update(['notes' => trim($existing . "\n" . $context)]);
            }
            $clean = preg_replace('/\[LEAD_CONTEXT:.+?\]/', '', $clean);
        }

        return trim($clean);
    }

    private function isHumanHandoffRequested(string $text): bool
    {
        return (bool) preg_match(self::HUMAN_KEYWORD_PATTERN, $text)
            || (bool) preg_match(self::SENSITIVE_PATTERN, $text);
    }

    private function handoff(Lead $lead, IncomingMessageDTO $dto, string $reason): array
    {
        $this->tagForHandoff($lead, $reason);

        $message = "Got it — I'll connect you with our team right away. Someone will reply here shortly. 🙏";
        LeadChat::create([
            'lead_id' => $lead->id,
            'sender'  => 'bot',
            'message' => $message,
        ]);

        $reply = new OutgoingReplyDTO(
            platform:            $dto->platform,
            recipientPlatformId: $dto->senderPlatformId,
            text:                $message,
        );
        $this->sendReply($reply);

        return [
            'status'  => 'handoff',
            'lead_id' => $lead->id,
            'reason'  => $reason,
        ];
    }

    private function tagForHandoff(Lead $lead, string $reason): void
    {
        $tags = $lead->tags ?? [];
        if (!in_array('callback_requested', $tags, true)) {
            $tags[] = 'callback_requested';
        }
        $reasonTag = 'handoff_' . $reason;
        if (!in_array($reasonTag, $tags, true)) {
            $tags[] = $reasonTag;
        }
        $lead->update(['tags' => $tags]);
        Log::info('Nia: lead tagged for human handoff', [
            'lead_id' => $lead->id,
            'reason'  => $reason,
        ]);
    }

    private function sendMessenger(OutgoingReplyDTO $reply, string $token): bool
    {
        $endpoint = "https://graph.facebook.com/v21.0/me/messages?access_token={$token}"
            . $this->appsecretProofParam($token);

        // Always send the text first so the customer sees something even if the
        // carousel call fails (Meta sometimes rejects template-only DMs).
        $textOk = $this->postMessenger($endpoint, [
            'recipient' => ['id' => $reply->recipientPlatformId],
            'message'   => ['text' => $reply->text],
        ]);

        if (!$reply->hasCarousel()) {
            return $textOk;
        }

        $carousel = $this->buildGenericTemplate($reply->products);
        if ($carousel === null) {
            return $textOk;
        }

        $this->postMessenger($endpoint, [
            'recipient' => ['id' => $reply->recipientPlatformId],
            'message'   => ['attachment' => $carousel],
        ]);

        return $textOk;
    }

    /**
     * Return "&appsecret_proof=..." if the app secret is configured, else "".
     * Meta requires this for server-to-server calls with System User tokens
     * when "Require App Secret" is enabled on the app.
     */
    private function appsecretProofParam(string $token): string
    {
        $appSecret = config('services.meta.app_secret');
        if (empty($appSecret)) {
            return '';
        }
        $proof = hash_hmac('sha256', $token, $appSecret);
        return '&appsecret_proof=' . $proof;
    }

    private function postMessenger(string $endpoint, array $payload): bool
    {
        $response = Http::post($endpoint, $payload);
        if ($response->failed()) {
            Log::error('Nia: messenger send failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        }
        return true;
    }

    /**
     * Build a Meta Send API generic template attachment from product matches.
     * Meta caps elements at 10; we expect at most 5 from ProductLookupService.
     *
     * @param ProductMatchDTO[] $products
     */
    private function buildGenericTemplate(array $products): ?array
    {
        $elements = [];
        foreach ($products as $p) {
            if (!$p->primaryImageUrl) {
                continue;
            }
            $elements[] = [
                'title'     => mb_substr($p->name, 0, 80),
                'subtitle'  => $this->formatSubtitle($p),
                'image_url' => $p->primaryImageUrl,
                'default_action' => [
                    'type' => 'web_url',
                    'url'  => $p->url,
                ],
                'buttons' => [[
                    'type'  => 'web_url',
                    'url'   => $p->url,
                    'title' => 'View product',
                ]],
            ];
            if (count($elements) >= 10) {
                break;
            }
        }

        if (empty($elements)) {
            return null;
        }

        return [
            'type'    => 'template',
            'payload' => [
                'template_type' => 'generic',
                'elements'      => $elements,
            ],
        ];
    }

    private function formatSubtitle(ProductMatchDTO $p): string
    {
        $price = '₹' . number_format($p->price, 0);
        if ($p->mrp && $p->mrp > $p->price) {
            $price .= ' (' . $p->discountPercentage() . '% off)';
        }
        $stock = $p->inStock ? '' : ' • Out of stock';
        return mb_substr($price . $stock, 0, 80);
    }

    private function sendWhatsApp(OutgoingReplyDTO $reply, string $token): bool
    {
        $phoneNumberId = config('services.meta.whatsapp_phone_number_id');
        if (empty($phoneNumberId)) {
            Log::error('Nia: META_WHATSAPP_PHONE_NUMBER_ID not configured');
            return false;
        }

        $response = Http::withToken($token)->post(
            "https://graph.facebook.com/v21.0/{$phoneNumberId}/messages",
            [
                'messaging_product' => 'whatsapp',
                'to'                => $reply->recipientPlatformId,
                'type'              => 'text',
                'text'              => ['body' => $reply->text],
            ],
        );

        if ($response->failed()) {
            Log::error('Nia: whatsapp send failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
            return false;
        }
        return true;
    }
}
