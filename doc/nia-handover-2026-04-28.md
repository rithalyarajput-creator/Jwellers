# Nia Instagram Bot — Handover Notes (2026-04-28)

This document supplements [`nia-instagram-bot.md`](nia-instagram-bot.md) (the design + runbook) with the **actual production state** at end of session 2026-04-28 and the **pending action items**.

If you're a future Claude session or human picking this up: read [`nia-instagram-bot.md`](nia-instagram-bot.md) first for design context, then this doc for current state and what's left to do.

---

## 1. What's done

### Code (all in `D:\projects\forverkids_laravel` and deployed to `forverkids:domains/foreverkidss.in/forverkids_laravel/`)

- `app/DTOs/Messaging/` — IncomingMessageDTO, OutgoingReplyDTO, IntentDTO, ProductMatchDTO
- `app/Mappers/MetaWebhookMapper.php` — pure-function mapper for IG/FB/WhatsApp payloads
- `app/Jobs/ProcessIncomingMessage.php` — async job on queue `nia`
- `app/Services/Nia/` — IntentExtractor (Haiku), ProductLookupService, NiaToolHandler
- `app/Services/MessagingService.php` — DTO-driven, includes IG carousel, hard-routes sensitive intents to human handoff, **adds `appsecret_proof` to all Meta Send API calls**
- `app/Services/ClaudeService.php` — Anthropic tool-use loop (max 3 iterations), tools: `lookup_products`, `get_size_chart_url`, `create_order_link`
- `app/Http/Controllers/Api/WebhookController.php` — slim: map → dispatch → return 200
- `app/Http/Middleware/VerifyMetaWebhookSignature.php` — HMAC SHA-256 (already existed)
- `app/Logging/TokenScrubber.php` + `TokenScrubberTap.php` — redacts EAA*/IGAA*/sk-ant-*/Bearer in logs
- `config/logging.php` — TokenScrubberTap registered on `single` + `daily` channels
- 7 artisan commands shipped: `nia:setup-check`, `nia:debug-token`, `nia:rotate-verify-token`, `nia:exchange-token`, `nia:refresh-token`, `nia:subscribe-ig`, `nia:set-app-secret`
- Orphan `app/Http/Controllers/InstagramWebhookController.php` and its routes deleted
- `bootstrap/app.php` cleaned (`webhooks/instagram` removed from CSRF except)

### Production environment (Hostinger — `~/domains/foreverkidss.in/forverkids_laravel/.env`)

| Key | State |
|---|---|
| `META_APP_ID` | ✅ `1275953988014074` |
| `META_PAGE_ACCESS_TOKEN` | ✅ EAA System User token (never expires), bound to FB page "Dcrayons" id `122109001023211898` |
| `META_APP_SECRET` | ❌ **WRONG VALUE** — see § 3 below |
| `META_VERIFY_TOKEN` | ✅ set on prod (length 20) — matches what's in Meta dashboard |
| `META_PAGE_ID` | ✅ `122109001023211898` |
| `META_WHATSAPP_PHONE_NUMBER_ID` | empty (only needed if WhatsApp Business is enabled) |
| `ANTHROPIC_API_KEY` | ❌ empty — user said "do later" |

### Infrastructure

- ✅ Webhook URL `https://foreverkidss.in/api/webhook/meta` is publicly reachable
- ✅ Verify-token handshake verified (Meta dashboard's "Verify and Save" succeeds)
- ✅ Code is deployed; routes are registered (`GET/POST /api/webhook/meta`)
- ✅ Hostinger cron firing every minute: `* * * * * cd ~/domains/foreverkidss.in/forverkids_laravel && /usr/bin/php artisan queue:work --queue=nia --stop-when-empty --tries=3 --backoff=10 --timeout=55 >/dev/null 2>&1`
- ✅ Queue tables accessible (jobs, failed_jobs)
- ✅ Settings DB rows present: `nia_enabled=true`, `nia_model=claude-sonnet-4-6`, `nia_system_prompt=` (using default)
- ✅ TokenScrubberTap is active in `config/logging.php`

### End-to-end smoke test (verified twice)

A signed synthetic IG payload posted to `/api/webhook/meta`:
1. Returns HTTP 200 `{"status":"ok","queued":1}` in <500ms
2. Job lands in `jobs[queue=nia]`
3. Cron fires within ≤60s, drains the job
4. `ProcessIncomingMessage` runs in ~140ms
5. Lead created, inbound LeadChat row written
6. ClaudeService called → returns fallback ("I'm currently unavailable") because `ANTHROPIC_API_KEY` is empty — **expected**
7. Outbound LeadChat row written
8. Meta Send API call attempted — **fails** with `Invalid appsecret_proof` (see § 3)

---

## 2. Pending actions (in order)

### 2a. Fix `META_APP_SECRET` on production — CRITICAL, 60 seconds

The current value in production `.env` does not match Meta's. This is blocking real DMs (Meta-signed webhooks are 403'd at our HMAC middleware) and outbound replies (`Invalid appsecret_proof`).

**Run:**
```
ssh forverkids "cd ~/domains/foreverkidss.in/forverkids_laravel && php artisan nia:set-app-secret"
```

The command prompts for the secret with hidden input, validates against `graph.facebook.com/{app_id}?access_token=app_id|secret`, and only writes if Meta accepts it. CEO must paste the **current** secret from https://developers.facebook.com/apps/1275953988014074/settings/basic/ → App Secret → Show.

**Verification after:**
```
ssh forverkids "cd ~/domains/foreverkidss.in/forverkids_laravel && php artisan nia:debug-token"
```
Expect: `is_valid: true`, `expires_at: 0 (never expires)`, no error in `application` field.

### 2b. Real DM smoke test

After 2a, CEO sends a DM from their personal IG to @foreverkids09. Within ≤60s the Lead + LeadChat rows should appear:

```
ssh forverkids 'cd ~/domains/foreverkidss.in/forverkids_laravel && php artisan tinker --execute="App\\Models\\LeadChat::join(\"leads\",\"leads.id\",\"=\",\"lead_chats.lead_id\")->where(\"leads.platform_id\",\"not like\",\"smoketest_%\")->latest(\"lead_chats.created_at\")->take(4)->get([\"lead_chats.message\",\"lead_chats.sender\",\"leads.platform_id\"])->each(function(\$c){echo \$c->sender.\": \".\$c->message.PHP_EOL;});"'
```

If no rows appear within 90s, check `storage/logs/laravel.log` for new "Nia: webhook payload received" entries. If those exist but no chats, the mapper filtered the payload. If those don't exist, Meta isn't delivering — investigate Meta App Dashboard → Webhooks → Recent Activity.

### 2c. Add `ANTHROPIC_API_KEY` to production

Without this, replies are the fallback string. Get key from https://console.anthropic.com → Settings → API Keys → Create Key. Add to `.env`:

```
ssh forverkids "cd ~/domains/foreverkidss.in/forverkids_laravel && nano .env"
# add line: ANTHROPIC_API_KEY=sk-ant-...
ssh forverkids "cd ~/domains/foreverkidss.in/forverkids_laravel && php artisan config:clear"
```

OR build a `nia:set-anthropic-key` command on the same pattern as `nia:set-app-secret` if the CEO prefers hidden-input flow.

### 2d. Optional: clean up smoke-test data

The DB currently contains 3 test leads + 6 test chats from the smoke tests run on 2026-04-28:

```sql
DELETE FROM lead_chats WHERE lead_id IN (SELECT id FROM leads WHERE platform_id LIKE 'smoketest_%');
DELETE FROM leads WHERE platform_id LIKE 'smoketest_%';
```

Plus remove the smoke-test script from prod:

```
ssh forverkids "rm ~/domains/foreverkidss.in/forverkids_laravel/.tmp_smoketest.php"
```

### 2e. Optional: regenerate System User token with `pages_messaging` + `pages_manage_metadata`

The current token has 13 IG/page scopes but is missing:
- `pages_messaging` — only matters if you want FB Messenger DMs (IG works without it)
- `pages_manage_metadata` — needed if you want `nia:subscribe-ig` API to work (you used the Meta dashboard UI to subscribe instead, which works fine)

Skip unless one of those becomes a need.

---

## 3. Active known issue: `META_APP_SECRET` mismatch

This is the blocker. Evidence:

```
2026-04-28 16:31:25 ERROR  Nia: messenger send failed
  "Invalid appsecret_proof provided in the API argument"
2026-04-28 16:46:06 ERROR  same
2026-04-28 17:02:02 ERROR  same
```

The `appsecret_proof` we send is `HMAC_SHA256(token, app_secret)`. Meta computes the expected proof using the **real current** app secret. Mismatch = rejection. Same secret governs the inbound `X-Hub-Signature-256` middleware verification, so Meta-originated webhooks are also being 403'd silently.

**Why the secret is wrong:** the CEO pasted the original leaked secret value (`299684b94d2a26ac2fe04d3ab9831361`) multiple times in chat and claimed "rotated," but byte-for-byte comparison showed the same value each time. Either the rotation never happened, or the value pasted in chat was the OLD value while a new one was actually generated. Either way, the value in production `.env` is stale.

**The only fix** is to log into Meta dashboard, click **Show** on the App Secret (it will display the *current* value), and use `nia:set-app-secret` to deploy it.

---

## 4. Cron details

Two crons currently exist on the Hostinger account:

1. **inyake.com ML training** — every 6 hours (or whatever it is — preserve, do not touch)
   `/usr/bin/php /home/u322703740/domains/inyake.com/public_html/artisan ml:train`
2. **foreverkidss.in queue worker** — every minute (we added today)
   `cd /home/u322703740/domains/foreverkidss.in/forverkids_laravel && /usr/bin/php artisan queue:work --queue=nia --stop-when-empty --tries=3 --backoff=10 --timeout=55 >/dev/null 2>&1`

If you migrate to AWS, replace cron #2 with `supervisor` running `queue:work` continuously (better resilience, faster pickup). See § 5 of `nia-instagram-bot.md` for supervisor config.

---

## 5. Code paths to know

| Concern | File |
|---|---|
| Webhook entry | `app/Http/Controllers/Api/WebhookController.php` |
| Signature verification | `app/Http/Middleware/VerifyMetaWebhookSignature.php` |
| Payload → DTO | `app/Mappers/MetaWebhookMapper.php` |
| DTOs | `app/DTOs/Messaging/*.php` |
| Async processing | `app/Jobs/ProcessIncomingMessage.php` |
| Conversation orchestration | `app/Services/MessagingService.php` |
| `appsecret_proof` computation | `app/Services/MessagingService.php::appsecretProofParam()` |
| Anthropic tool-use loop | `app/Services/ClaudeService.php` |
| Tool definitions + execution | `app/Services/Nia/NiaToolHandler.php` |
| Product retrieval | `app/Services/Nia/ProductLookupService.php` |
| Cheap intent classifier | `app/Services/Nia/IntentExtractor.php` |
| Token redaction in logs | `app/Logging/TokenScrubber.php` + `TokenScrubberTap.php` |
| `.env` admin commands | `app/Console/Commands/*` (Nia*, Set*, Rotate*) |

---

## 6. AWS migration

A separate document, [`aws-migration-plan.md`](aws-migration-plan.md), captures the planned migration to AWS EC2 instance `i-06f43f4c1978d854f` at `54.90.144.100`. That migration is **NOT** started; it is awaiting CEO answers to scope and access questions.

When migration begins, the Nia bot's `.env` values, queue worker, and webhook subscription will need to be re-applied on AWS — see § 6 of `aws-migration-plan.md`.

---

## 7. Source of truth across systems

| System | What lives there |
|---|---|
| Local dev (`D:\projects\forverkids_laravel`) | Latest code, `.env` populated for local dev only, smoke-test scripts |
| Hostinger (live) | Production code (deployed 2026-04-28), production `.env` (with the stale `META_APP_SECRET`), real customer data |
| AWS (planned) | Empty — see migration plan |
| Team AI DB (`dcrayons_team_ai.db`) | Project `foreverkids-nia-ig-bot` (id=6), tasks, members |
| `C:\Users\pc\.claude\projects\d--projects-Team-AI\memory\` | Project memories (foreverkids ids, MARG parity, Nia creds rotation) |
| Meta dashboard | Webhook callback URL, verify token (matches `.env`), field subscriptions |
| Anthropic console | API key (not yet pasted into `.env`) |

---

## 8. Checklist for the next session

If you're picking this up cold:

- [ ] Read [`nia-instagram-bot.md`](nia-instagram-bot.md) for design
- [ ] Read this doc for current state
- [ ] Run `ssh forverkids "cd ~/domains/foreverkidss.in/forverkids_laravel && php artisan nia:setup-check --ping"` to see live state
- [ ] If `META_APP_SECRET` is still flagged WARN/FAIL, ask CEO to run `nia:set-app-secret`
- [ ] If `ANTHROPIC_API_KEY` is empty, ask CEO for the key (or build the hidden-input setter)
- [ ] Smoke-test by sending a real DM from CEO's personal IG to @foreverkids09 — check `lead_chats` table within 60s
- [ ] If migration to AWS is now in scope, see [`aws-migration-plan.md`](aws-migration-plan.md) and resolve the open questions there before doing anything
