# Foreverkids DevOps Runbook

The standing operating procedure for everything that touches production.
If you're about to do something on the EC2 box and it isn't documented
here, **stop and ask**.

Owner: **Ravi AI** (Head of DevOps), backed by **Leo** (CTO) and **Tara** (QA).

---

## 1. Architecture today

```
┌────────────────┐                 ┌─────────────────────────────┐
│ GoDaddy DNS    │ ──A record──▶  │ AWS EC2 (us-east-1)         │
│ foreverkidss.in│   54.90.144.100 │  i-06f43f4c1978d854f        │
└────────────────┘                 │  t3.micro, ubuntu user      │
                                   │  nginx 1.24 + php-fpm 8.3   │
                                   │  Laravel 12 app             │
                                   │  Redis (cache + sessions)   │
                                   │  Supervisor → queue workers │
                                   │  Meilisearch (product index)│
                                   │  MySQL 8 (local on box)     │
                                   └─────────────────────────────┘
```

**Hostinger box** is no longer in DNS. Treat as decommissioned. Do not touch.

**Staging:** does not exist yet. **Phase 2 work item.** Until then, every PR
that's risky must be tested locally with `php artisan serve` + `npm run dev`.

---

## 2. Standard production deploy

**You don't run this manually.** It's automated. But here's what happens so
you know what to expect.

### Trigger
1. PR is merged into `main`
2. `.github/workflows/deploy.yml` fires
3. Pre-flight job waits for CI to pass on the merge commit
4. Deploy job pauses for **manual approval**
   - Approver: anyone in the `production` environment reviewer list
   - Configured in: GitHub repo → Settings → Environments → production

### What the script does (`scripts/deploy/deploy.sh`)
1. Acquires file lock — refuses if another deploy is mid-flight
2. Snapshots current SHA → `storage/deploy-locks/previous-sha`
3. `git fetch && git reset --hard <new-sha>`
4. `composer install --no-dev --optimize-autoloader`
5. `npm ci && npm run build`
6. `php artisan down --secret=<token>` (maintenance mode, you can bypass with `?secret=<token>`)
7. `php artisan migrate --force`
8. Cache rebuild (clear all + warm `config:cache`, `route:cache`, `view:cache`)
9. `php artisan queue:restart`
10. `sudo systemctl reload php8.3-fpm` (recycles OPcache)
11. `sudo nginx -s reload`
12. `php artisan up`
13. Append to `storage/release-history.log`
14. Releases lock

### Smoke test
The workflow then `curl`s `https://foreverkidss.in/`. Expects 200 or 302
(302 is the pre-launch redirect — see PreLaunchPassword middleware). Anything
else fails the workflow and you get a Slack alert.

### Total time
~2-4 minutes for a small change. Up to 8 if `npm ci` cold-caches.

---

## 3. Rolling back

### When to roll back
- Smoke test fails
- Errors spike in Sentry within 5 min of deploy (when Sentry is wired up)
- Customer reports site down / checkout broken
- You can't fix forward in <15 minutes

### How

```bash
ssh fkids-aws
cd /var/www/foreverkids
bash scripts/deploy/rollback.sh
```

The script:
1. Reads `storage/deploy-locks/previous-sha`
2. Asks you to confirm
3. `git reset --hard` to that SHA
4. Reinstalls deps for that SHA
5. Rebuilds caches
6. Restarts workers + reloads PHP-FPM + nginx
7. Logs the rollback

### What rollback does NOT do
- **It does not reverse migrations.** If the bad deploy ran a destructive
  migration, the data damage is already done.
- **It does not restore user uploads** in `storage/app/public/`.
- **It does not revert config changes** made directly on the box (which you
  shouldn't have made anyway).

### Rolling back a bad migration

Don't do this without thinking. Two options:

**Safe:** write a forward-fix migration and deploy normally.
```bash
php artisan make:migration fix_for_bad_change
# write the inverse logic, commit, PR, merge, deploy
```

**Risky:** `php artisan migrate:rollback --step=1` then `rollback.sh`.
Only safe if the migration's `down()` method is correct AND the bad migration
was the most recent batch. Verify with `php artisan migrate:status` first.

---

## 4. Cache management

```bash
# nuclear — clear and rewarm everything
bash scripts/deploy/cache-rebuild.sh

# clear app cache only (Redis) — usually safe
php artisan cache:clear

# clear compiled blade views — when templates show stale content
php artisan view:clear

# clear route cache — when a route change isn't taking effect
php artisan route:clear

# clear config cache — when env var change isn't being read
php artisan config:clear
```

**Production should always have caches WARMED**, not cleared. If you clear
without rewarming, the next request rebuilds them and you take a 200ms hit.
Use `cache-rebuild.sh` which clears + rewarms in one shot.

**OPcache:** lives in php-fpm process memory. Cleared by reloading the
service: `sudo systemctl reload php8.3-fpm`. Do this after editing any PHP
file directly on the box (which, again, you shouldn't be doing).

---

## 5. Queue workers

Managed by Supervisor. Config lives in `/etc/supervisor/conf.d/foreverkids-worker.conf`.

```bash
# status of all workers
sudo supervisorctl status

# restart workers (also done by deploy script)
sudo supervisorctl restart foreverkids-worker:*

# graceful — picks up new code at next idle moment
php artisan queue:restart
```

Workers process: `default` (transactional), `nia` (Instagram bot), `media`
(image processing). Check `storage/logs/worker-*.log` if jobs aren't running.

---

## 6. Incident response

### Site is down

1. **Check the EC2 instance is up:**
   ```bash
   aws ec2 describe-instance-status --instance-ids i-06f43f4c1978d854f
   ```
2. **SSH in:** `ssh fkids-aws`
3. **Check nginx:** `sudo systemctl status nginx`
4. **Check php-fpm:** `sudo systemctl status php8.3-fpm`
5. **Check disk:** `df -h` — full disk silently breaks Laravel cache writes
6. **Check logs:**
   ```bash
   tail -100 /var/log/nginx/error.log
   tail -100 /var/log/php8.3-fpm.log
   tail -100 storage/logs/laravel.log
   ```
7. **Check the last deploy:** `tail storage/release-history.log` — was it
   recent? Roll back.
8. **Open RCA in Team AI** within 24h: `python -m src.cli.main rca create ...`

### Webhook not firing (Instagram / Shiprocket / PayU)

1. Check the webhook URL is reachable: `curl -I https://foreverkidss.in/webhooks/<vendor>`
2. Check Laravel log for incoming POSTs: `grep "<vendor>" storage/logs/laravel.log`
3. Check the queue worker for the relevant queue is running
4. Check the credential is not rotated (we have a track record — see
   the `project_foreverkids_nia_webhook_broken` and `shiprocket_creds_leaked`
   memories)

### Deploy fails halfway

The lock file (`storage/deploy-locks/deploy.lock`) will block re-runs.
Inspect first:
```bash
ssh fkids-aws
cd /var/www/foreverkids
ls -la storage/deploy-locks/
git status                # is the working tree partially-deployed?
php artisan migrate:status # were migrations only half-run?
```
If safe, remove the lock and re-run: `rm storage/deploy-locks/deploy.lock`.
If migrations are mid-run, **stop and call Ravi.**

---

## 7. Required GitHub repo settings

Configure these once, in the GitHub web UI:

### Branch protection for `main` (Settings → Branches → Add rule)
- ✅ Require a pull request before merging
- ✅ Require approvals: **1** minimum
- ✅ Dismiss stale approvals when new commits are pushed
- ✅ Require review from Code Owners
- ✅ Require status checks to pass before merging
  - Required: `PHP (Pint • Tests)`, `Node (Vite build)`, `Secrets scan (gitleaks)`
- ✅ Require branches to be up to date before merging
- ✅ Require linear history
- ✅ Do not allow bypassing the above settings

### Same for `develop` (when staging exists)

### Environments (Settings → Environments → New)
- Name: `production`
  - ✅ Required reviewers (at minimum: CEO + DevOps Head)
  - ✅ Wait timer: 0 (manual approval is the gate)
  - Secrets:
    - `AWS_SSH_HOST` = `54.90.144.100`
    - `AWS_SSH_USER` = `ubuntu`
    - `AWS_SSH_PORT` = `22`
    - `AWS_SSH_PRIVATE_KEY` = full contents of `fkids.pem`
    - `AWS_SSH_KNOWN_HOSTS` = output of `ssh-keyscan -H 54.90.144.100`
    - `DEPLOY_PATH` = path to repo on EC2 (verified 2026-05-07: `/var/www/foreverkids/foreverkids`)
    - `SLACK_WEBHOOK_URL` (optional) = Slack incoming webhook for #deploys

---

## 8. Test ratchet

The PHPUnit step in `.github/workflows/ci.yml` does NOT just run
`php artisan test` and gate on full pass. It runs the suite, captures
JUnit XML, then invokes `scripts/ci/test_ratchet.py` to compare against
a committed baseline at `tests/baseline-passing.txt`.

### How it works

| State | What happens |
|---|---|
| Baseline test still passes | ✅ Fine, expected |
| Baseline test now fails | ❌ **Regression** — CI fails the PR |
| Non-baseline test still fails | ⚠️ Tracked-as-broken, ignored by gate |
| Non-baseline test now passes | 🎉 "Free win" — should be added to baseline |

### When you fix a previously-broken test

Once the test is reliably green:

```bash
# Locally (or download junit.xml artifact from a green CI run):
python3 scripts/ci/test_ratchet.py storage/logs/junit.xml \
  tests/baseline-passing.txt --update

# Then commit the updated baseline file
git add tests/baseline-passing.txt
git commit -m "test: lock in <TestClass>::<test_method> in baseline"
```

The baseline file is sorted alphabetically and includes a counter
comment, so diffs stay small.

### The bigger picture

The 61-test gap (102 passing / 163 total at the time this was set up)
is being worked through by **Tara AI** in a dedicated
"Foreverkids Test Suite Green-Up" project in Team AI. Each PR fixes
1-3 tests, runs through this same pipeline, and ratchets the baseline
up. Target: full-suite green within 4-6 weeks at sustainable pace.

If you find a ratchet-failure and the baseline is wrong (e.g. a test
was renamed in a refactor), regenerate the baseline with `--update`
and call out the change in the PR description. Reviewers will check
that no genuinely-passing test was silently dropped.

---

## 9. Recommended additions (Phase 1 work)

Things this PR does **not** add but should land soon:

- **PHPStan / Larastan** — static analysis. Add to `composer.json`:
  ```json
  "require-dev": { "larastan/larastan": "^2.9" }
  ```
  Then add to `ci.yml`: `vendor/bin/phpstan analyse --memory-limit=2G`
- **Composer scripts** — convenience for devs. Add to `composer.json`:
  ```json
  "scripts": {
    "lint":    ["vendor/bin/pint --test"],
    "lint:fix":["vendor/bin/pint"],
    "analyse": ["vendor/bin/phpstan analyse --memory-limit=2G"],
    "ci":      ["@lint", "@analyse", "@test"]
  }
  ```
- **Sentry** for error capture (PHP + JS)
- **UptimeRobot** on `/`, `/category/shop-for-girls`, `/cart`, `/checkout`
- **Cloudflare** in front of the domain (faster, DDoS, free SSL backup)
- **Staging environment** — separate small EC2 instance, separate DB, deploys
  on push to `develop`. Estimated cost: ~₹2,000/month.

---

## 10. Quick reference

| Task                          | Command                                       |
|-------------------------------|-----------------------------------------------|
| SSH to prod                   | `ssh fkids-aws`                               |
| Tail Laravel log              | `tail -f storage/logs/laravel.log`            |
| Tail nginx error log          | `sudo tail -f /var/log/nginx/error.log`       |
| Tail PHP-FPM log              | `sudo tail -f /var/log/php8.3-fpm.log`        |
| Manual deploy                 | `bash scripts/deploy/deploy.sh`               |
| Manual rollback               | `bash scripts/deploy/rollback.sh`             |
| Rebuild caches                | `bash scripts/deploy/cache-rebuild.sh`        |
| Restart queue workers         | `php artisan queue:restart`                   |
| Reload PHP-FPM (clear OPcache)| `sudo systemctl reload php8.3-fpm`            |
| Reload nginx                  | `sudo nginx -t && sudo nginx -s reload`       |
| Maintenance mode on           | `php artisan down --secret=...`               |
| Maintenance mode off          | `php artisan up`                              |
| Migration status              | `php artisan migrate:status`                  |
| Last 10 releases              | `tail storage/release-history.log`            |
