# Nia — Instagram DM Bot for ForeverKids

Nia is the AI sales/support assistant that reads Instagram, Facebook Messenger, and WhatsApp DMs, replies in real time using the live product catalog, and hands off to a human when a customer needs one.

This document covers what Nia does, the end-to-end flow, the Meta setup runbook, and how to run, tune, and observe her.

---

## 1. Scope

**Nia handles:**
- Product browse / discovery (e.g. "do you have a red frock for my 4yo?")
- Sizing questions
- Pricing
- Soft sales — guides the customer to a product page or carousel
- Captures lead context across conversations (`leads.notes`)

**Nia does NOT handle (explicit handoff to humans):**
- Order status / "where is my order"
- Returns / refunds / exchanges
- Anything where the customer types `human`, `agent`, `person`, `representative`, `talk to someone`
- Cases where Claude's tool-use loop runs out without finding a clear answer (fallback to human)

When a handoff fires, the lead is tagged `callback_requested` plus `handoff_<reason>`, and a soft "I'll connect you with our team" reply is sent immediately.

---

## 2. End-to-end DM flow

```
┌────────────┐   1. DM sent     ┌──────────────────┐
│ Customer IG│ ───────────────► │ Meta Graph webhook│
└────────────┘                  └─────────┬─────────┘
                                          │ POST /api/webhook/meta
                                          ▼
            ┌─────────────────────────────────────────────────┐
            │ VerifyMetaWebhookSignature middleware           │ HMAC SHA-256
            └────────────────────────┬────────────────────────┘
                                     ▼
            ┌─────────────────────────────────────────────────┐
            │ Api\WebhookController::handle                   │
            │   - MetaWebhookMapper::fromPayload($payload)    │ DTO list
            │   - dispatch(ProcessIncomingMessage) per DTO    │
            │   - return 200 (target P95 <500ms)              │
            └────────────────────────┬────────────────────────┘
                                     ▼
            ┌─────────────────────────────────────────────────┐
            │ Queue 'nia' worker (php artisan queue:work)     │
            │   ProcessIncomingMessage::handle                │
            └────────────────────────┬────────────────────────┘
                                     ▼
            ┌─────────────────────────────────────────────────┐
            │ MessagingService::processIncoming(dto)          │
            │   - dedupe via platform_message_id              │
            │   - find/create Lead (platform + platform_id)   │
            │   - store inbound LeadChat (sender='customer')  │
            │   - if 'nia_enabled' setting OFF → stop         │
            │   - if human keyword OR sensitive regex →       │
            │     handoff(), tag, soft reply, return          │
            │   - ClaudeService::generateReply(lead, text)    │
            └────────────────────────┬────────────────────────┘
                                     ▼
            ┌─────────────────────────────────────────────────┐
            │ ClaudeService — Anthropic Messages API          │
            │ tool-use loop (max 3 iterations):               │
            │   - tool: lookup_products(filters)              │
            │   - tool: get_size_chart_url()                  │
            │   - tool: create_order_link(slug, size)         │
            │ stop_reason='end_turn' → return text + matches  │
            └────────────────────────┬────────────────────────┘
                                     ▼
            ┌─────────────────────────────────────────────────┐
            │ MessagingService                                │
            │   - processAiCommands()  // [NIA_QUALIFIED] etc │
            │   - store outbound LeadChat (sender='bot')      │
            │   - sendReply(OutgoingReplyDTO)                 │
            │     ├─ text via Send API                        │
            │     └─ generic-template carousel (if products)  │
            └─────────────────────────────────────────────────┘
```

---

## 3. Meta setup runbook

### 3.1 One-time app provisioning (Foreverkids09-IG)

1. **Meta Developer Console → Apps → Foreverkids09-IG** (App ID `1275953988014074`).
2. Add product: **Webhooks**, **Instagram Graph API**, **Messenger** (and **WhatsApp** if used).
3. **App Settings → Basic → App Secret** → copy fresh secret into `.env` as `META_APP_SECRET`. Rotate any time the secret is suspected leaked.
4. Generate a **Page Access Token** (long-lived) for the IG-linked Facebook page → paste into `.env` as `META_PAGE_ACCESS_TOKEN`.
5. Note the FB Page ID linked to the IG business account → paste into `.env` as `META_PAGE_ID`.
6. Generate a random 32-char string (NOT the app secret) → paste as `META_VERIFY_TOKEN`.

### 3.2 .env keys (single-tenant)

```
ANTHROPIC_API_KEY=sk-ant-...
META_PAGE_ACCESS_TOKEN=EAA...   # rotate if exposed
META_APP_SECRET=...             # rotate if exposed
META_VERIFY_TOKEN=...           # random 32 chars, NOT the app secret
META_PAGE_ID=...                # FB Page id linked to the IG business account
META_WHATSAPP_PHONE_NUMBER_ID=  # optional; only if WhatsApp Business is enabled
```

### 3.3 Webhook callback URL

In Meta dashboard → **Webhooks → Edit Subscription**:
- **Callback URL:** `https://<your-domain>/api/webhook/meta`
- **Verify Token:** value of `META_VERIFY_TOKEN`
- Click **Verify and Save** — Meta hits `GET /api/webhook/meta` with `hub_challenge`, our controller echoes it back.

### 3.4 Subscribe webhook fields

After verification, run once on the host:

```
php artisan nia:subscribe-ig
```

This subscribes the IG/FB page to: `messages`, `messaging_postbacks`, `message_reactions`, `messaging_referrals`. Idempotent — safe to run again after a token rotation.

### 3.5 Queue worker (production)

Nia processing is async. Worker must be running for replies to go out:

```
php artisan queue:work --queue=nia --tries=3 --backoff=10 --timeout=60
```

Add to Supervisor (`/etc/supervisor/conf.d/nia.conf`):

```
[program:nia-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/foreverkids/artisan queue:work --queue=nia --tries=3 --backoff=10 --timeout=60
autostart=true
autorestart=true
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/nia-worker.log
stopwaitsecs=3600
```

Reload: `supervisorctl reread && supervisorctl update && supervisorctl start nia-worker:*`.

---

## 4. Operating Nia

### 4.1 Disable Nia for a campaign or incident

```php
\App\Models\Setting::set('nia_enabled', false, 'boolean', 'chatbot');
```

While disabled, inbound messages are still stored to `lead_chats` but no AI reply is generated and no message is sent.

Re-enable: same call with `true`.

### 4.2 Edit her tone or persona

```php
\App\Models\Setting::set('nia_system_prompt', "<your full prompt>", 'string', 'chatbot');
```

When `nia_system_prompt` is empty, Nia uses the default in `ClaudeService::defaultSystemPrompt()`. The current customer block, tool list, and `[NIA_QUALIFIED] / [SCHEDULE_CALL] / [LEAD_CONTEXT]` command list are appended automatically.

### 4.3 Switch model

```php
\App\Models\Setting::set('nia_model', 'claude-sonnet-4-6', 'string', 'chatbot');
```

Recommended:
- **claude-sonnet-4-6** — default. Best balance of quality + latency for tool-use chat.
- **claude-haiku-4-5-20251001** — cheaper, lower quality. Good for high-volume promotional periods.
- **claude-opus-4-7** — highest quality. Use for VIP windows; slower.

Intent extraction always runs on Haiku (`IntentExtractor::MODEL`).

### 4.4 Read past conversations

```sql
-- Last 50 conversations
SELECT l.id, l.platform, l.platform_id, l.name, l.stage, l.tags,
       (SELECT message FROM lead_chats WHERE lead_id = l.id ORDER BY created_at DESC LIMIT 1) AS last_message,
       (SELECT created_at FROM lead_chats WHERE lead_id = l.id ORDER BY created_at DESC LIMIT 1) AS last_at
FROM leads l
ORDER BY last_at DESC
LIMIT 50;

-- Full transcript for one lead
SELECT sender, message, created_at
FROM lead_chats
WHERE lead_id = ?
ORDER BY created_at;
```

### 4.5 Handoff tags to triage

Filter leads tagged for human action:

```sql
SELECT id, platform, platform_id, name, tags, notes, updated_at
FROM leads
WHERE JSON_CONTAINS(tags, '"callback_requested"')
ORDER BY updated_at DESC;
```

Tag suffix tells you why:
- `handoff_keyword` — customer asked for human / sensitive keyword (returns, refund, order status)
- `handoff_schedule_call` — Claude emitted `[SCHEDULE_CALL]`
- `handoff_tool_loop_exhausted` — Claude couldn't find anything in 3 tool iterations

---

## 5. KPIs (weekly review with Isha)

| KPI | Target | Source |
|---|---|---|
| DM auto-reply rate | ≥95% of inbound text DMs | `lead_chats` count by sender |
| Webhook ack P95 | <500 ms | application logs / load test |
| Reply latency P95 | <10 s | `lead_chats.created_at` delta |
| Tool-call frequency | ≥1 `lookup_products` per browse intent | log telemetry |
| Hallucinated SKUs | 0 (audit sample) | manual UAT |
| Qualified-lead rate | trend ↑ week over week | `leads.stage='qualified'` |
| Handoff rate | <20% steady state | `tags LIKE '%callback_requested%'` |

---

## 6. Important platform limits

- **Meta 24-hour messaging window.** After the customer's last message, the page can only reply within 24h without a `MESSAGE_TAG`. Outside that window, replies will be rejected by Send API. Nia does not send unsolicited messages, so this is mainly relevant for human follow-up.
- **Echo messages.** Meta sends back every message we send. The mapper drops `is_echo` events to prevent loops.
- **Attachment-only messages** (stickers, images) are currently skipped. Re-enable in `MetaWebhookMapper::fromMessagingEvent` once Nia learns to caption images.
- **WhatsApp** uses a different payload shape (`changes[].value.messages[]`). Same mapper, different branch.

---

## 7. Troubleshooting

| Symptom | First thing to check |
|---|---|
| Meta dashboard shows webhook in red | `META_VERIFY_TOKEN` mismatch; signature middleware blocking POST. Tail `storage/logs/laravel.log` and grep for `Nia: webhook`. |
| No replies going out | Queue worker not running. `php artisan queue:work --queue=nia` manually to confirm. Check `failed_jobs` table. |
| Replies are generic and never mention real products | Anthropic tool-use disabled or model lacks tools support. Confirm `nia_model` is set to a tool-use-capable model. |
| Replies cite SKUs that don't exist | Critical. Disable Nia (`nia_enabled=false`) immediately. Likely a regression in `ProductLookupService::search`. |
| Logs leak tokens | `TokenScrubberTap` not loaded. Check `config/logging.php` has `'tap' => [App\Logging\TokenScrubberTap::class]` on active channels. |

---

## 8. Code map

| Concern | File |
|---|---|
| Webhook entry | `app/Http/Controllers/Api/WebhookController.php` |
| Signature verification | `app/Http/Middleware/VerifyMetaWebhookSignature.php` |
| Payload → DTO | `app/Mappers/MetaWebhookMapper.php` |
| DTOs | `app/DTOs/Messaging/*.php` |
| Async processing | `app/Jobs/ProcessIncomingMessage.php` |
| Conversation orchestration | `app/Services/MessagingService.php` |
| Anthropic tool-use loop | `app/Services/ClaudeService.php` |
| Tool definitions + execution | `app/Services/Nia/NiaToolHandler.php` |
| Product retrieval | `app/Services/Nia/ProductLookupService.php` |
| Cheap intent classifier | `app/Services/Nia/IntentExtractor.php` |
| Token redaction in logs | `app/Logging/TokenScrubber.php` + `TokenScrubberTap.php` |
| One-time IG subscription | `app/Console/Commands/SubscribeInstagramWebhook.php` |
| Settings seed | `database/seeders/ChatbotSettingsSeeder.php` |
