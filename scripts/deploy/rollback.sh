#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Foreverkids production rollback
#
# Reverts to the SHA snapshotted by the last deploy. Use when:
#   - Smoke test fails post-deploy
#   - Errors spike in Sentry
#   - Customer reports a regression you can't fix in <15 min
#
# What this DOES:
#   - git reset --hard to previous SHA
#   - reinstall composer + npm deps for that SHA
#   - rebuild caches
#   - reload PHP-FPM + nginx
#
# What this does NOT do:
#   - reverse migrations (those need a manual `migrate:rollback` decision)
#   - touch the database
#   - touch user-uploaded storage
#
# If the bad deploy ran a destructive migration, ROLLBACK ALONE WON'T SAVE
# YOU. Consult the runbook (doc/devops-runbook.md → "Rolling back a bad
# migration") before running this.
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LOCK_DIR="$APP_ROOT/storage/deploy-locks"
PREV_FILE="$LOCK_DIR/previous-sha"
HISTORY_LOG="$APP_ROOT/storage/release-history.log"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"

log() { echo "[$(date -u +%H:%M:%S)] $*"; }

if [[ ! -f "$PREV_FILE" ]]; then
  echo "ERROR: no previous SHA recorded ($PREV_FILE missing). Cannot auto-rollback." >&2
  echo "Find the SHA from $HISTORY_LOG and check it out manually." >&2
  exit 1
fi

PREV_SHA="$(cat "$PREV_FILE")"
ROLLBACK_TS="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"
ROLLBACK_ACTOR="${ROLLBACK_ACTOR:-$(whoami)@$(hostname)}"

cd "$APP_ROOT"
CURRENT_SHA="$(git rev-parse HEAD)"

log "── ROLLBACK starting ──"
log "  Current (bad):  $CURRENT_SHA"
log "  Reverting to:   $PREV_SHA"
log "  Actor:          $ROLLBACK_ACTOR"

read -r -p "Confirm rollback to $PREV_SHA? [y/N] " confirm
if [[ "${confirm,,}" != "y" ]]; then
  log "Rollback aborted by user."
  exit 0
fi

# Maintenance mode
DEPLOY_TOKEN="$(openssl rand -hex 16)"
php artisan down --secret="$DEPLOY_TOKEN" --render="errors::503" || true

# Revert code
git reset --hard "$PREV_SHA"

# Reinstall deps for that SHA
composer install --no-dev --no-interaction --no-progress --optimize-autoloader --classmap-authoritative
npm ci --no-audit --no-fund --prefer-offline
npm run build

# Caches
bash "$APP_ROOT/scripts/deploy/cache-rebuild.sh"

# Workers + servers
php artisan queue:restart
sudo systemctl reload "$PHP_FPM_SERVICE" || log "WARN: could not reload $PHP_FPM_SERVICE"
sudo nginx -t && sudo nginx -s reload || log "WARN: could not reload nginx"

php artisan up

echo "$ROLLBACK_TS  ROLLBACK  from=$CURRENT_SHA  to=$PREV_SHA  $ROLLBACK_ACTOR" >> "$HISTORY_LOG"
log "── Rollback complete ──"
log "If a migration was applied with the bad deploy, it is STILL APPLIED."
log "Check storage/release-history.log and consider migrate:rollback if needed."
