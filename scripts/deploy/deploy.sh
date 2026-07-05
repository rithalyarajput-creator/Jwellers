#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Foreverkids production deploy script
#
# Runs ON the production EC2 host. Invoked by .github/workflows/deploy.yml or
# manually by a DevOps engineer. Idempotent and safe to re-run.
#
# Steps:
#   1. Lock (refuse concurrent deploys)
#   2. Snapshot current SHA for rollback
#   3. git pull on main
#   4. composer install (no-dev, optimized)
#   5. npm ci + npm run build
#   6. artisan down (maintenance mode, secret bypass token)
#   7. artisan migrate --force
#   8. cache-rebuild.sh (clear + warm caches)
#   9. queue:restart + reload php-fpm + nginx -s reload
#  10. artisan up
#  11. log to release-history.log
#  12. release lock
#
# Required env (set by workflow or shell):
#   DEPLOY_SHA      git SHA being deployed (for the history log)
#   DEPLOY_ACTOR    who triggered this deploy
#
# Optional env:
#   PHP_FPM_SERVICE  default: php8.2-fpm
#   QUEUE_RESTART    default: 1  (set 0 to skip)
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

# ── Config ──────────────────────────────────────────────────────────────────
APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
LOCK_DIR="$APP_ROOT/storage/deploy-locks"
LOCK_FILE="$LOCK_DIR/deploy.lock"
HISTORY_LOG="$APP_ROOT/storage/release-history.log"
PHP_FPM_SERVICE="${PHP_FPM_SERVICE:-php8.3-fpm}"
QUEUE_RESTART="${QUEUE_RESTART:-1}"
DEPLOY_SHA="${DEPLOY_SHA:-$(cd "$APP_ROOT" && git rev-parse HEAD)}"
DEPLOY_ACTOR="${DEPLOY_ACTOR:-$(whoami)@$(hostname)}"
DEPLOY_TS="$(date -u +"%Y-%m-%dT%H:%M:%SZ")"

mkdir -p "$LOCK_DIR"

log() { echo "[$(date -u +%H:%M:%S)] $*"; }

# ── Lock ────────────────────────────────────────────────────────────────────
exec 9>"$LOCK_FILE"
if ! flock -n 9; then
  echo "ERROR: another deploy is already running (lock: $LOCK_FILE)" >&2
  exit 1
fi
trap 'flock -u 9; rm -f "$LOCK_FILE"' EXIT

cd "$APP_ROOT"
log "── Foreverkids deploy starting ──"
log "  SHA:    $DEPLOY_SHA"
log "  Actor:  $DEPLOY_ACTOR"
log "  Time:   $DEPLOY_TS"

# ── Snapshot previous SHA for rollback ──────────────────────────────────────
PREVIOUS_SHA="$(git rev-parse HEAD)"
echo "$PREVIOUS_SHA" > storage/deploy-locks/previous-sha
log "Previous SHA snapshotted → $PREVIOUS_SHA (for rollback)"

# ── Pull latest ─────────────────────────────────────────────────────────────
log "Fetching from origin..."
git fetch --prune origin

log "Checking out main @ $DEPLOY_SHA..."
git checkout main
git reset --hard "$DEPLOY_SHA"

# ── PHP deps ────────────────────────────────────────────────────────────────
log "Installing PHP dependencies..."
composer install \
  --no-dev \
  --no-interaction \
  --no-progress \
  --optimize-autoloader \
  --classmap-authoritative

# ── JS deps + Vite build ────────────────────────────────────────────────────
log "Installing JS dependencies..."
npm ci --no-audit --no-fund --prefer-offline

log "Building frontend assets (Vite)..."
npm run build

# ── Maintenance mode (with secret bypass for our own smoke tests) ───────────
DEPLOY_TOKEN="$(openssl rand -hex 16)"
log "Entering maintenance mode (bypass token: ${DEPLOY_TOKEN:0:6}…)"
php artisan down --secret="$DEPLOY_TOKEN" --render="errors::503" || true

# ── Migrations ──────────────────────────────────────────────────────────────
log "Running migrations..."
php artisan migrate --force --no-interaction

# ── Cache rebuild (delegated) ───────────────────────────────────────────────
log "Rebuilding caches..."
bash "$APP_ROOT/scripts/deploy/cache-rebuild.sh"

# ── Queue restart ───────────────────────────────────────────────────────────
if [[ "$QUEUE_RESTART" == "1" ]]; then
  log "Restarting queue workers (signals supervisor)..."
  php artisan queue:restart
fi

# ── Reload PHP-FPM (clears OPcache by recycling workers) ────────────────────
log "Reloading $PHP_FPM_SERVICE..."
sudo systemctl reload "$PHP_FPM_SERVICE" || log "WARN: could not reload $PHP_FPM_SERVICE (skipping)"

# ── Reload nginx (picks up new public/build symlink targets) ────────────────
log "Reloading nginx..."
sudo nginx -t && sudo nginx -s reload || log "WARN: could not reload nginx (skipping)"

# ── Exit maintenance mode ───────────────────────────────────────────────────
log "Exiting maintenance mode..."
php artisan up

# ── Permissions sanity (storage + bootstrap/cache must be writable) ─────────
log "Re-asserting writable perms on storage + bootstrap/cache..."
chmod -R ug+w storage bootstrap/cache 2>/dev/null || true

# ── History log ─────────────────────────────────────────────────────────────
echo "$DEPLOY_TS  $DEPLOY_SHA  $DEPLOY_ACTOR  prev=$PREVIOUS_SHA" >> "$HISTORY_LOG"

log "── Deploy succeeded ──"
log "Rollback command: bash $APP_ROOT/scripts/deploy/rollback.sh"
