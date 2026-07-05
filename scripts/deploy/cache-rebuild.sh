#!/usr/bin/env bash
# ─────────────────────────────────────────────────────────────────────────────
# Foreverkids cache rebuild
#
# Two-phase: CLEAR everything, then WARM what should be cached in production.
# Called by deploy.sh + rollback.sh, but also safe to run manually when:
#   - You changed config/* files but didn't deploy (rare — config edits should
#     go through PR)
#   - Blade views are showing stale content (compiled view cache)
#   - A route change isn't taking effect
#
# Run from the app root or anywhere — auto-resolves APP_ROOT.
# ─────────────────────────────────────────────────────────────────────────────

set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$APP_ROOT"

log() { echo "[cache] $*"; }

# ── Phase 1: CLEAR ──────────────────────────────────────────────────────────
log "Clearing all caches..."
php artisan optimize:clear   # config + route + view + event + compiled + cache

# Belt-and-braces: optimize:clear sometimes misses these on edge versions
php artisan cache:clear      # application cache (Redis)
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan event:clear || true   # may not exist on all Laravel versions

# ── Phase 2: WARM (production only) ─────────────────────────────────────────
APP_ENV_VALUE="$(php -r "echo getenv('APP_ENV') ?: 'production';")"

if [[ "$APP_ENV_VALUE" == "production" || "$APP_ENV_VALUE" == "staging" ]]; then
  log "Warming caches for $APP_ENV_VALUE..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  php artisan event:cache || true
else
  log "Skipping cache warm (APP_ENV=$APP_ENV_VALUE — dev mode)"
fi

log "Cache rebuild complete."
