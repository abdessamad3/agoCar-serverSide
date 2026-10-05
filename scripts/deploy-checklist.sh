#!/usr/bin/env bash
# =============================================================================
# AGOCAR — Production Deploy Checklist
# Run this from the project root before going live:
#   bash scripts/deploy-checklist.sh
# =============================================================================

set -uo pipefail

PASS=0
FAIL=0

ok()   { echo "  [OK]  $*"; ((PASS++)) || true; }
fail() { echo "  [!!]  $*"; ((FAIL++)) || true; }
info() { echo "        $*"; }

echo ""
echo "=============================================="
echo "  AGOCAR Production Readiness Checklist"
echo "=============================================="
echo ""

# ---------------------------------------------------------------------------
# 1. Environment variables
# ---------------------------------------------------------------------------
echo "[ 1 ] Environment"

ENV_FILE=".env.local"
[ -f ".env" ] && ENV_FILE=".env"
[ -f ".env.local" ] && ENV_FILE=".env.local"

APP_ENV_VAL=$(grep -E '^APP_ENV=' "$ENV_FILE" 2>/dev/null | cut -d= -f2 | tr -d '"')
APP_DEBUG_VAL=$(grep -E '^APP_DEBUG=' "$ENV_FILE" 2>/dev/null | cut -d= -f2 | tr -d '"')

if [ "$APP_ENV_VAL" = "prod" ]; then
    ok "APP_ENV=prod"
else
    fail "APP_ENV=$APP_ENV_VAL (must be prod)"
fi

if [ "$APP_DEBUG_VAL" = "0" ] || [ -z "$APP_DEBUG_VAL" ]; then
    ok "APP_DEBUG=0 (or unset, defaults to 0 in prod)"
else
    fail "APP_DEBUG=$APP_DEBUG_VAL (must be 0)"
fi

DB_URL=$(grep -E '^DATABASE_URL=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)
if echo "$DB_URL" | grep -q "127\.0\.0\.1\|localhost"; then
    info "DATABASE_URL points to localhost — OK for same-server deployment"
    ok "DATABASE_URL present"
else
    ok "DATABASE_URL present"
fi

# JWT keys
if [ -f "config/jwt/private.pem" ] && [ -f "config/jwt/public.pem" ]; then
    ok "JWT key files exist"
else
    fail "JWT key files missing (run: php bin/console lexik:jwt:generate-keypair)"
fi

# ---------------------------------------------------------------------------
# 2. Symfony cache
# ---------------------------------------------------------------------------
echo ""
echo "[ 2 ] Symfony Cache"

if [ -d "var/cache/prod" ]; then
    ok "Production cache directory exists"
else
    fail "var/cache/prod missing — run: php bin/console cache:clear --env=prod"
fi

if php bin/console cache:warmup --env=prod --no-debug > /dev/null 2>&1; then
    ok "Cache warmup successful"
else
    fail "Cache warmup failed — check logs/prod.log"
fi

# ---------------------------------------------------------------------------
# 3. Database connectivity and migrations
# ---------------------------------------------------------------------------
echo ""
echo "[ 3 ] Database"

if php bin/console doctrine:schema:validate --env=prod --no-debug > /dev/null 2>&1; then
    ok "Doctrine schema valid"
else
    fail "Doctrine schema invalid — run: php bin/console doctrine:migrations:migrate --env=prod"
fi

PENDING=$(php bin/console doctrine:migrations:status --env=prod --no-debug 2>/dev/null | grep "Not migrated" | grep -oE '[0-9]+' | head -1)
if [ "${PENDING:-0}" = "0" ]; then
    ok "All migrations applied"
else
    fail "$PENDING migration(s) pending — run: php bin/console doctrine:migrations:migrate --env=prod --no-interaction"
fi

# ---------------------------------------------------------------------------
# 4. CORS origin
# ---------------------------------------------------------------------------
echo ""
echo "[ 4 ] CORS"

CORS_VAL=$(grep -E '^CORS_ALLOW_ORIGIN=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)
if echo "$CORS_VAL" | grep -qE "localhost|127\.0\.0\.1"; then
    fail "CORS_ALLOW_ORIGIN still allows localhost — update to production domain"
    info "Example: CORS_ALLOW_ORIGIN='^https://yourdomain\\.com$'"
else
    ok "CORS_ALLOW_ORIGIN=$CORS_VAL"
fi

# ---------------------------------------------------------------------------
# 5. HTTPS / Security headers (web server check)
# ---------------------------------------------------------------------------
echo ""
echo "[ 5 ] HTTPS"

DOMAIN="${PROD_DOMAIN:-}"
if [ -n "$DOMAIN" ]; then
    PROTO=$(curl -s -o /dev/null -w "%{url_effective}" --max-time 5 -L "http://$DOMAIN" 2>/dev/null | grep -oE '^https?')
    if [ "$PROTO" = "https" ]; then
        ok "HTTP redirects to HTTPS on $DOMAIN"
    else
        fail "HTTPS redirect not working on $DOMAIN"
    fi
    HSTS=$(curl -s -I --max-time 5 "https://$DOMAIN" 2>/dev/null | grep -i "strict-transport-security")
    if [ -n "$HSTS" ]; then
        ok "HSTS header present"
    else
        fail "HSTS header missing — add to nginx/apache config"
    fi
else
    info "Set PROD_DOMAIN=yourdomain.com to run HTTPS checks"
    info "  PROD_DOMAIN=yourdomain.com bash scripts/deploy-checklist.sh"
fi

# ---------------------------------------------------------------------------
# 6. Mailer
# ---------------------------------------------------------------------------
echo ""
echo "[ 6 ] Mailer"
MAILER=$(grep -E '^MAILER_DSN=' "$ENV_FILE" 2>/dev/null | cut -d= -f2-)
if [ -n "$MAILER" ]; then
    ok "MAILER_DSN configured"
else
    fail "MAILER_DSN not set"
fi

# ---------------------------------------------------------------------------
# 7. Backup cron
# ---------------------------------------------------------------------------
echo ""
echo "[ 7 ] Backup Cron"
if crontab -l 2>/dev/null | grep -q "backup.sh"; then
    ok "Backup cron registered"
else
    fail "Backup cron not found — add to crontab:"
    info "  0 2 * * * /var/www/autoloc/scripts/backup.sh >> /var/log/autoloc-backup.log 2>&1"
fi

# ---------------------------------------------------------------------------
# 8. File permissions
# ---------------------------------------------------------------------------
echo ""
echo "[ 8 ] Permissions"
for dir in var/cache var/log public/uploads; do
    if [ -w "$dir" ]; then
        ok "$dir is writable"
    else
        fail "$dir is not writable — run: chmod -R 775 $dir"
    fi
done

# ---------------------------------------------------------------------------
# Summary
# ---------------------------------------------------------------------------
echo ""
echo "=============================================="
TOTAL=$((PASS + FAIL))
echo "  Passed: $PASS / $TOTAL"
if [ "$FAIL" -gt 0 ]; then
    echo "  Failed: $FAIL — fix before deploying"
    echo "=============================================="
    exit 1
else
    echo "  All checks passed. Ready for production."
    echo "=============================================="
    exit 0
fi
