#!/usr/bin/env bash
# SK Federations — production deploy (Hostinger post-pull / SSH).
# Does NOT run migrations. Does NOT create or overwrite .env.
# Generates public/build via Vite (requires Node/npm on the server).
set -euo pipefail

APP_NAME="SK Federations"
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$ROOT"

fail() {
    echo ""
    echo "======================================"
    echo "DEPLOY FAILED — ${APP_NAME}"
    echo "Step failed: $1"
    echo "======================================"
    exit 1
}

echo "======================================"
echo "${APP_NAME} Production Deployment"
echo "Root: ${ROOT}"
echo "======================================"

if [[ ! -f "${ROOT}/.env" ]]; then
    fail ".env missing. Configure the production .env on the server."
fi

if [[ ! -f "${ROOT}/composer.lock" ]]; then
    fail "composer.lock missing — refusing to install without a lockfile."
fi

if [[ ! -f "${ROOT}/package-lock.json" ]]; then
    fail "package-lock.json missing — refusing npm ci without a lockfile."
fi

command -v composer >/dev/null 2>&1 || fail "composer not found on PATH."
command -v php >/dev/null 2>&1 || fail "php not found on PATH."
command -v npm >/dev/null 2>&1 || fail "npm not found on PATH (required to generate public/build)."
command -v node >/dev/null 2>&1 || fail "node not found on PATH (required to generate public/build)."

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

echo ""
echo "[1/5] Installing PHP dependencies..."
composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    || fail "composer install"

echo ""
echo "[2/5] Installing Node dependencies..."
# Vite is in devDependencies — do not use --omit=dev.
npm ci --no-fund --no-audit \
    || fail "npm ci"

echo ""
echo "[3/5] Building frontend assets into public/build..."
# Keep existing public/build until a successful build replaces it.
npm run build \
    || fail "npm run build"

if [[ ! -f "${ROOT}/public/build/manifest.json" ]]; then
    fail "public/build/manifest.json missing after frontend build."
fi

echo "Verified: public/build/manifest.json"

echo ""
echo "[4/5] Clearing Laravel caches..."
php artisan optimize:clear \
    || fail "php artisan optimize:clear"

echo ""
echo "[5/5] Rebuilding Laravel caches..."
php artisan optimize \
    || fail "php artisan optimize"

echo ""
echo "======================================"
echo "Deployment completed successfully."
echo "public/build generated automatically."
echo "Migrations were NOT run."
echo "======================================"
