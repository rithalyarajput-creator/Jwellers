# AWS Migration Plan — Foreverkids Laravel

**Status:** PLANNING — not yet started, awaiting answers from CEO before Phase 1.
**Created:** 2026-04-28 by Shivam AI (DCrayons Team AI) on instruction from CEO Rahul.

---

## 1. Why migrate

The CEO has allocated AWS infrastructure (an Elastic IP and EC2 instance, see "AWS Targets" below) and wants to move Foreverkids — and likely all DCrayons-managed Hostinger sites — to AWS for centralised control, predictable scaling, and tighter integration with the rest of the DCrayons stack (HRMS at `15.207.133.144`, Team AI, etc.).

This document captures the full plan so any future session, agent, or human can resume the migration without losing context.

---

## 2. Current source-of-truth (Hostinger)

| Item | Value |
|---|---|
| SSH host | `167.88.41.35` |
| SSH port | `65002` |
| SSH user | `u322703740` |
| SSH alias (in `~/.ssh/config`) | `forverkids` |
| Identity file | `~/.ssh/id_ed25519` |
| Live URL | `https://foreverkidss.in/` |
| Laravel root | `~/domains/foreverkidss.in/forverkids_laravel/` |
| Web docroot | `~/domains/foreverkidss.in/public_html/` |
| Database (assumed) | MySQL on Hostinger shared, schema `dcommerce` |
| PHP version | 8.3.30 |
| Queue connection | database |
| Other domains on same account | ~25 (jikra.in, inyake.com, getreviews.dcrayons.app, multisports.dcrayons.app, justburger.dcrayons.app, raalogistics.dcrayons.app, hrmsdemo.dcrayons.app, in.dcrayons.app, api.dcrayons.app, drrishabh.com, corpify.us, getsetnova.com, mechanicbazaar.com, radheybook.com, siriusshoppe.com, socrayons.dcrayons.app, spjbeauty.dcrayons.app, yugacare.in, proven.dcrayons.app, musandco.dcrayons.app, bstechnology.org, sandybrown-leopard-676607.hostingersite.com, foreverkids.dcrayons.app — note this is a SECOND foreverkids install at `~/domains/foreverkids.dcrayons.app/forverkids_laravel/` which appears to be older/staging) |
| Crons currently active | (a) inyake.com ML training, (b) foreverkidss.in `queue:work --queue=nia` |

---

## 3. AWS targets

| Item | Value |
|---|---|
| AWS account | Dcrayons (419324627456) |
| Region | us-east-1 (N. Virginia) |
| Elastic IP | `54.90.144.100` (allocation ID `eipalloc-05fe1248e5d14c01e`) |
| Public DNS | `ec2-54-90-144-100.compute-1.amazonaws.com` |
| EC2 instance ID | `i-06f43f4c1978d854f` |
| Network interface | `eni-0cf91c4e727b972af` |
| Private IP | `172.31.35.102` |
| Address pool | Amazon |
| Network border group | us-east-1 |

**Resolved 2026-04-28:**
- SSH user: `ubuntu`
- Key file: `D:\projects\forverkids_laravel\fkids.pem` (RSA, locked to user `pc` only via icacls)
- SSH alias: `fkids-aws` (added to `C:\Users\pc\.ssh\config`)
- Instance type: **t3.micro** (2 vCPU, 911 MiB RAM) — adequate for Foreverkids only at low traffic; resize to t3.medium for production load
- OS: Ubuntu 24.04.4 LTS
- Disk: 6.8 GB total, ~5 GB free
- State on takeover: fresh (no other apps)

---

## 4. Open questions blocking Phase 1

| ID | Question | Why we need it |
|---|---|---|
| A.1 | Migrate **just Foreverkids**, or all 25 sites in one effort? | Determines instance sizing, MySQL strategy, and timeline (Foreverkids alone ≈ 2–3 days; all 25 ≈ 2 weeks). |
| A.2 | Anyone else (devs, deploy bots) touching production during the window? | Prevents mid-migration writes from causing data drift. |
| B.3 | SSH credentials for `i-06f43f4c1978d854f` (user + .pem path or paste-friendly key install method) | Without this, no work on AWS can begin. |
| B.4 | Is the EC2 instance fresh or already has sites? | Affects whether we install stack from scratch or join existing setup. |
| B.5 | Instance type (e.g. `t3.medium`, `m5.large`)? | Determines whether MySQL can run on the same box or needs RDS. |
| C.6 | DB strategy: MySQL on EC2 box, separate EC2 DB box, or **RDS**? | Cost vs reliability decision. RDS is recommended for production e-commerce. |
| C.7 | Approximate Hostinger DB size? | Drives mysqldump time and bandwidth needed for cutover. |
| D.8 | Maintenance window — when can we have ~30 min of write-freeze on Foreverkids? | DNS cutover requires downtime; ideally a low-traffic Sunday night IST. |
| D.9 | DNS provider for `foreverkidss.in` (Hostinger DNS, Cloudflare, GoDaddy, Route 53?) | We must update the A record to `54.90.144.100` to complete cutover. |
| E.10 | Confirm: Hostinger copy stays live and untouched until AWS is verified, then 7-day soak, then decommission? | Rollback safety. Default answer should be yes. |

Until these are answered, **do not start Phase 1**. Park further work and re-prompt the CEO.

---

## 4b. Phase 1 status — DONE 2026-04-28

```
Stack installed and tuned for t3.micro:
  ✅ 2 GB swapfile + swappiness=10
  ✅ nginx 1.24 (server block: foreverkidss.in @ /var/www/foreverkids/forverkids_laravel/public)
  ✅ PHP 8.3.6 + FPM (pool: dynamic, 5 max workers, 500 max requests/worker)
  ✅ MySQL 8.0.45 (innodb_buffer_pool 256MB, max_connections 30)
  ✅ Redis 7.0.15 (maxmemory 64MB, allkeys-lru, bound to localhost)
  ✅ Supervisor (Nia worker config staged in /tmp; applied in Phase 2)
  ✅ Composer 2.9.7, Node 20.20.2, certbot
  ✅ Database `dcommerce` created (empty)
  ✅ MySQL user `foreverkids@localhost` created
       password lives in /root/.foreverkids_dbpass (root-only, chmod 400)

Internal HTTP 200 confirmed on http://127.0.0.1/

Configs deployed (originals in D:\projects\forverkids_laravel\.aws-deploy\):
  /etc/nginx/sites-available/foreverkidss.in   (was nginx-foreverkids.conf)
  /etc/mysql/mysql.conf.d/foreverkids.cnf       (was mysql-foreverkids.cnf)
  /etc/php/8.3/fpm/pool.d/www.conf              (tuning applied via sed; backup at www.conf.bak)
  /etc/redis/redis.conf                          (foreverkids overrides appended at end)
```

**Phase 1 closed 2026-04-28:** SG inbound rules added (80, 443 from 0.0.0.0/0).

## 4c. Phase 2 status — DONE 2026-04-28

```
Code synced from Hostinger to AWS via tar-pipe through laptop:
  ✅ ~/domains/foreverkidss.in/forverkids_laravel/ (1108 files, 55 MB)
     → /var/www/foreverkids/forverkids_laravel/
  ✅ ~/domains/foreverkidss.in/public_html/ (70 files, 46 MB)
     → /var/www/foreverkids/public_html/
  excluded: vendor/, node_modules/, storage/logs/*.log, framework cache/sessions/views,
            .env.backup-*, .tmp_*.php

  ✅ composer install --no-dev --optimize-autoloader
  ✅ php artisan storage:link (already present from rsync)
  ✅ chown -R www-data:www-data + chmod 775 storage bootstrap/cache + chmod 600 .env
  ✅ php artisan config:cache + route:cache + view:cache

.env on AWS (at /var/www/foreverkids/forverkids_laravel/.env):
  APP_ENV=production, APP_DEBUG=false
  APP_URL=http://54.90.144.100  ← swap to https://foreverkidss.in at cutover
  DB_HOST=127.0.0.1, DB_DATABASE=dcommerce, DB_USERNAME=foreverkids
  DB_PASSWORD=<from /root/.foreverkids_dbpass>
  META_APP_ID=1275953988014074
  META_APP_SECRET=<copied from Hostinger — known stale, see § 9 of nia-handover-2026-04-28.md>
  META_PAGE_ACCESS_TOKEN=<EAA System User token>
  META_VERIFY_TOKEN=<copied from Hostinger>
  META_PAGE_ID=122109001023211898
  ANTHROPIC_API_KEY=  empty — user said "do later"
  QUEUE_CONNECTION=database
  CACHE_STORE=database
  SESSION_DRIVER=database
```

## 4d. Phase 3 status — DONE 2026-04-28 (rehearsal)

```
DB migrated via PHP proc_open mysqldump (exec disabled on Hostinger):
  ✅ Source: u322703740_foreverkidss on Hostinger localhost
  ✅ Dump: 760 KB gzipped, 7.6 MB uncompressed, 117 tables
  ✅ Transfer: Hostinger → laptop /tmp → AWS /tmp (cleaned up after)
  ✅ Import on AWS: 15 seconds, 117 tables, 19.7 MB on disk
  ✅ Sample row counts: products=10362, orders=11, users=17, leads=3,
     lead_chats=6, settings=63, categories=18

NOTE: This was a rehearsal dump. At cutover (Phase 5) we'll dump again from
the Hostinger live state to capture all changes since 2026-04-28 12:54 UTC.
```

## 4e. Phase 4 status — READY for CEO smoke test

Supervisor queue worker installed and running:
  /etc/supervisor/conf.d/nia-worker.conf
  nia-worker:nia-worker_00 RUNNING (replaces Hostinger cron pattern)

Public HTTP from external smoke tests:
  GET  /  with Host: foreverkidss.in       → 302 → /coming-soon (correct)
  GET  /up                                  → 200
  GET  /api/v1/products                     → 200
  GET  /api/webhook/meta with bogus token   → 403 (signature middleware blocking)

**CEO action for Phase 4:** add to `C:\Windows\System32\drivers\etc\hosts`:
```
54.90.144.100  foreverkidss.in
54.90.144.100  www.foreverkidss.in
```
Flush DNS (`ipconfig /flushdns`), open `https://foreverkidss.in` in browser. The site will load from AWS while everyone else stays on Hostinger. Test storefront, cart, checkout, admin login, Nia (DM `foreverkids09` after entering preview password — it should land in AWS `lead_chats` table).

When done testing, REMOVE the `/etc/hosts` lines so the world keeps hitting Hostinger until the formal cutover.

## 4f. Phase 5 — DNS cutover (NOT YET STARTED)

Trigger when:
- Phase 4 smoke test passed
- ANTHROPIC_API_KEY in production .env
- META_APP_SECRET corrected to current value
- Maintenance window scheduled

Procedure: see § 5 of this doc.

## 5. Phased plan

```
Phase 1 — Provision AWS host                                   ½ day
  - SSH into EC2 with the provided .pem
  - Install: nginx 1.24+, PHP 8.3 + extensions matching Hostinger
    (mbstring, mysql, intl, gd, zip, bcmath, opcache, redis, curl, xml)
  - Install MySQL 8 locally OR connect to RDS endpoint
  - Install Redis (optional but recommended for cache + sessions)
  - Install certbot for Let's Encrypt
  - Install supervisor for queue worker (preferred over cron on AWS)
  - Configure systemd units, open security group: 80/443 + SSH from your IP
  - Confirm `php -v`, `nginx -t`, `mysql --version` all green

Phase 2 — Sync code + storage                                  ½ day
  rsync -avz --exclude='.env' --exclude='node_modules' --exclude='vendor' \
    forverkids:domains/foreverkidss.in/forverkids_laravel/ \
    aws-host:/var/www/foreverkids/forverkids_laravel/
  rsync -avz forverkids:domains/foreverkidss.in/public_html/ \
    aws-host:/var/www/foreverkids/public_html/
  ssh aws-host "cd /var/www/foreverkids/forverkids_laravel && composer install --no-dev && npm ci && npm run build"
  Recreate storage symlink on AWS:
    cd /var/www/foreverkids/forverkids_laravel
    php artisan storage:link

Phase 3 — DB migration rehearsal                               ½ day
  On Hostinger:
    mysqldump --single-transaction --quick --routines --triggers \
      --default-character-set=utf8mb4 dcommerce > /tmp/dump-rehearsal.sql
    scp the file to AWS host
  On AWS:
    mysql -u root -p dcommerce < /tmp/dump-rehearsal.sql
  Verify row counts in: products, categories, orders, leads, lead_chats, users, settings.
  Time the export+import end-to-end — this is the hard floor on cutover downtime.

Phase 4 — Smoke test on AWS via /etc/hosts                     ½ day
  On your laptop:
    Add to C:\Windows\System32\drivers\etc\hosts:
      54.90.144.100  foreverkidss.in
  Reload browser DNS cache.
  Test on AWS while Hostinger stays live for everyone else:
    - Storefront loads, products load, images load
    - Add to cart, checkout flow (use a test gateway key — DO NOT charge real cards)
    - Admin login, order management, settings
    - Nia webhook: POST signed payload to AWS, verify queue + DB writes
    - Queue worker firing (supervisor not cron)
    - Email send
    - SSL via Let's Encrypt (issue cert against the temp hostname or wait until cutover)

Phase 5 — DNS cutover (~30 min downtime)
  T-24h: Lower DNS TTL on the foreverkidss.in A record to 60s.
  T-0:
    1. Put Hostinger storefront into maintenance mode (or coming-soon redirect)
    2. Final mysqldump from Hostinger → import to AWS
    3. Confirm AWS Nia: php artisan nia:setup-check --ping
    4. Confirm AWS supervisor: queue worker is running, not waiting
    5. Issue/install Let's Encrypt cert for foreverkidss.in on AWS
    6. Update DNS A record: foreverkidss.in → 54.90.144.100
    7. Watch DNS propagation: dig +short foreverkidss.in
    8. Once propagation confirmed, smoke-test from a clean phone
    9. Verify Meta webhook delivery still works (POST will land on AWS now)
    10. Tail laravel.log for 30 min looking for errors

Phase 6 — Soak + decommission                                  1 week
  - Keep Hostinger files in place but disable the foreverkidss.in queue cron
    (so no double-processing if any traffic accidentally hits Hostinger)
  - Monitor AWS for 7 days: error rate, response time, queue depth, failed_jobs
  - At day 7+: archive Hostinger copy → remove
```

---

## 6. Specific things that MUST be re-applied on AWS

These are configuration items that won't survive a plain rsync:

1. **`.env`** — do NOT rsync. Recreate on AWS by hand or via a deploy script:
   - `APP_KEY=` keep the same as Hostinger so encrypted cookies and tokens stay valid
   - `APP_URL=https://foreverkidss.in`
   - `DB_HOST=<aws-mysql-host>` (`localhost` if EC2-local, RDS endpoint if RDS)
   - `DB_DATABASE=dcommerce`
   - `QUEUE_CONNECTION=database` (or upgrade to redis if Phase 1 installed it)
   - `META_APP_ID=1275953988014074`
   - `META_PAGE_ACCESS_TOKEN=<EAA System User token, never expires>`
   - `META_APP_SECRET=<the CURRENT secret from Meta dashboard — verified via nia:set-app-secret>`
   - `META_VERIFY_TOKEN=<the same value already entered in Meta dashboard webhook config>`
   - `META_PAGE_ID=122109001023211898`
   - `ANTHROPIC_API_KEY=<from console.anthropic.com>`
   - `ANTHROPIC_MODEL=claude-haiku-4-5-20251001` (default for cheap calls; nia_model setting overrides for replies)
2. **Storage symlink** — `php artisan storage:link` again.
3. **Storage uploads** — `~/domains/foreverkidss.in/forverkids_laravel/storage/app/public/` and `~/domains/foreverkidss.in/public_html/images/` must rsync over.
4. **Queue worker** — supervisor on AWS instead of Hostinger cron. Sample config in `doc/nia-instagram-bot.md` § 3.5.
5. **Cache + config rebuild** — `php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache`.
6. **File permissions** — `chown -R www-data:www-data /var/www/foreverkids/forverkids_laravel/storage bootstrap/cache` and `chmod -R 775` on those.
7. **Cron for `php artisan schedule:run`** if any scheduled tasks are registered — `* * * * * cd /var/www/foreverkids/forverkids_laravel && php artisan schedule:run >> /dev/null 2>&1`.
8. **Meta webhook** — the URL stays the same (`https://foreverkidss.in/api/webhook/meta`) so Meta config doesn't need changes. But after cutover, run `php artisan nia:setup-check --ping` to confirm AWS receives the next webhook.

---

## 7. Things to NOT do

- Do not delete Hostinger files for at least 7 days post-cutover.
- Do not run database migrations on AWS until you've imported the Hostinger dump (otherwise you'll create empty tables that conflict with the import).
- Do not use `composer install` (no `--no-dev`) on production. Saves 30%+ in deps.
- Do not use shared MySQL credentials between Hostinger and AWS. Issue a new `foreverkids` MySQL user on AWS.
- Do not try to run all 25 sites on a `t3.micro`. Sizing matters.

---

## 8. If migrating ALL 25 sites

This document focuses on Foreverkids. If the directive is all sites, treat each domain as its own mini-migration with its own:

- vhost / nginx server block
- database schema
- `.env`
- DNS A record
- SSL cert (or wildcard if supported)
- queue worker (if applicable)

Total effort: 10–15 person-days. Strongly recommend grouping by tier (e-commerce critical → marketing sites → demos) and doing the critical tier first.

A separate `doc/aws-migration-all-sites.md` should be authored before that effort starts.

---

## 9. Owner & escalation

- Lead: Leo AI (CTO) — coordinates the migration, owns the plan
- Hands-on: Ravi AI (Head of DevOps) — server provisioning, deploy scripts
- QA gate: Tara AI — pre-cutover regression suite + post-cutover monitoring
- Stakeholder: Rahul (CEO)
- Reference for Nia-side specifics: Ankit AI (built the integration)

If migration causes >2h of unexpected downtime, escalate to Rahul immediately and consider rollback by reverting the DNS A record back to Hostinger's IP.
