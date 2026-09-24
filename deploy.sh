

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

# --------------------------------------
# Production environment checks
# --------------------------------------

if [[ ! -f "${ROOT}/.env" ]]; then
    fail ".env missing. Configure the production .env on the server."
fi

if [[ ! -f "${ROOT}/composer.lock" ]]; then
    fail "composer.lock missing — refusing to install without a lockfile."
fi

if [[ ! -f "${ROOT}/package-lock.json" ]]; then
    fail "package-lock.json missing — refusing npm ci without a lockfile."
fi

# --------------------------------------
# Required commands
# --------------------------------------

command -v composer >/dev/null 2>&1 \
    || fail "composer not found on PATH."

command -v php >/dev/null 2>&1 \
    || fail "php not found on PATH."

command -v npm >/dev/null 2>&1 \
    || fail "npm not found on PATH."

command -v node >/dev/null 2>&1 \
    || fail "node not found on PATH."

# --------------------------------------
# Laravel writable directories
# --------------------------------------

mkdir -p \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs \
    bootstrap/cache

# --------------------------------------
# 1. Composer
# --------------------------------------

echo ""
echo "[1/5] Installing PHP dependencies..."

composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    || fail "composer install"

# --------------------------------------
# 2. NPM
# --------------------------------------

echo ""
echo "[2/5] Installing Node dependencies..."

# Vite is normally in devDependencies.
# Do NOT use --omit=dev.

npm ci \
    --no-fund \
    --no-audit \
    || fail "npm ci"

# --------------------------------------
# 3. Frontend build
# --------------------------------------

echo ""
echo "[3/5] Building frontend assets..."

npm run build \
    || fail "npm run build"

if [[ ! -f "${ROOT}/public/build/manifest.json" ]]; then
    fail "public/build/manifest.json missing after frontend build."
fi

# --------------------------------------
# 4. Clear Laravel cache
# --------------------------------------

echo ""
echo "[4/5] Clearing Laravel caches..."

php artisan optimize:clear \
    || fail "php artisan optimize:clear"

# --------------------------------------
# 5. Rebuild Laravel cache
# --------------------------------------

echo ""
echo "[5/5] Rebuilding Laravel caches..."

php artisan optimize \
    || fail "php artisan optimize"

# --------------------------------------
# Complete
# --------------------------------------

echo ""
echo "======================================"
echo "Deployment completed successfully."
echo "Migrations were NOT run."
echo "======================================"