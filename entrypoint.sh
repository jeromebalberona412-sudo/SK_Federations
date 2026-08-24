#!/bin/sh
set -e

echo "=========================================="
echo "Starting Laravel application entrypoint..."
echo "=========================================="

# ============================================
# Display environment variables for debugging
# ============================================
echo "=========================================="
echo "Environment Variables Check"
echo "=========================================="
echo "APP_ENV: ${APP_ENV:-not set}"
echo "APP_URL: ${APP_URL:-not set}"
echo "MAIL_MAILER: ${MAIL_MAILER:-not set}"
echo "MAIL_HOST: ${MAIL_HOST:-not set}"
echo "MAIL_PORT: ${MAIL_PORT:-not set}"
echo "MAIL_USERNAME: ${MAIL_USERNAME:-not set}"
echo "MAIL_FROM_ADDRESS: ${MAIL_FROM_ADDRESS:-not set}"
echo "=========================================="

# ============================================
# Test PHP environment variable access
# ============================================
echo "Testing PHP environment variable access..."
php -r "echo 'PHP APP_ENV: ' . getenv('APP_ENV') . PHP_EOL;"
php -r "echo 'PHP MAIL_MAILER: ' . getenv('MAIL_MAILER') . PHP_EOL;"
php -r "echo 'PHP MAIL_HOST: ' . getenv('MAIL_HOST') . PHP_EOL;"

# ============================================
# Create required system directories
# ============================================
echo "Creating system directories..."
mkdir -p /var/log/nginx || echo "Failed to create /var/log/nginx"
mkdir -p /var/lib/nginx || echo "Failed to create /var/lib/nginx"
mkdir -p /var/cache/nginx || echo "Failed to create /var/cache/nginx"
mkdir -p /run/nginx || echo "Failed to create /run/nginx"
mkdir -p /var/log/supervisor || echo "Failed to create /var/log/supervisor"
mkdir -p /var/run/supervisor || echo "Failed to create /var/run/supervisor"
mkdir -p /var/lib/php/sessions || echo "Failed to create /var/lib/php/sessions"

# ============================================
# Set proper permissions for system directories
# ============================================
echo "Setting system directory permissions..."
chown -R www-data:www-data /var/lib/nginx /var/cache/nginx /run/nginx || echo "Failed to chown nginx directories"
chown -R root:root /var/log/nginx /var/log/supervisor /var/run/supervisor || echo "Failed to chown log directories"
chown -R www-data:www-data /var/lib/php || echo "Failed to chown php directories"
chmod -R 755 /var/log/nginx /var/log/supervisor /var/run/supervisor || echo "Failed to chmod log directories"
chmod -R 755 /var/lib/php || echo "Failed to chmod php directories"

# ============================================
# Create storage directories FIRST (before chown)
# ============================================
echo "Ensuring storage directories exist..."
mkdir -p /var/www/html/storage/framework/{views,cache,sessions} || echo "Failed to create framework directories"
mkdir -p /var/www/html/storage/logs || echo "Failed to create logs directory"
mkdir -p /var/www/html/storage/app/public || echo "Failed to create app/public directory"
mkdir -p /var/www/html/storage/temp || echo "Failed to create temp directory"
mkdir -p /var/www/html/bootstrap/cache || echo "Failed to create bootstrap/cache directory"

# Pre-create laravel.log so PHP-FPM (www-data) always has write access
touch /var/www/html/storage/logs/laravel.log || echo "Failed to touch laravel.log"

# ============================================
# Set proper permissions for Laravel (AFTER mkdir)
# ============================================
echo "Setting Laravel permissions..."
chown -R www-data:www-data /var/www/html/storage || echo "Failed to chown storage"
chown -R www-data:www-data /var/www/html/bootstrap/cache || echo "Failed to chown bootstrap/cache"
chown -R www-data:www-data /var/www/html/public || echo "Failed to chown public"
chmod -R 777 /var/www/html/storage || echo "Failed to chmod storage"
chmod -R 777 /var/www/html/storage/temp || echo "Failed to chmod storage/temp"
chmod -R 777 /var/www/html/bootstrap/cache || echo "Failed to chmod bootstrap/cache"
chmod -R 775 /var/www/html/public || echo "Failed to chmod public"

# ============================================
# Ensure PHP temp directory exists and is writable
# ============================================
echo "Setting up PHP temp directory..."
export TMPDIR="/tmp/php-tmp"
export TEMP="/tmp/php-tmp"
export TMP="/tmp/php-tmp"
mkdir -p "$TMPDIR" || echo "Failed to create PHP temp dir"
chown -R www-data:www-data "$TMPDIR" || echo "Failed to chown PHP temp dir"
chmod -R 777 "$TMPDIR" || echo "Failed to chmod PHP temp dir"
echo "PHP temp directory configured: $TMPDIR"

# ============================================
# Verify Laravel files exist
# ============================================
echo "Verifying Laravel installation..."
if [ ! -f "/var/www/html/artisan" ]; then
    echo "ERROR: artisan file not found!"
    ls -la /var/www/html/
    exit 1
fi
echo "artisan file found"

if [ ! -f "/var/www/html/composer.json" ]; then
    echo "ERROR: composer.json not found!"
    exit 1
fi
echo "composer.json found"

# ============================================
# Handle storage link (idempotent)
# ============================================
echo "Setting up storage link..."
if [ -L "/var/www/html/public/storage" ]; then
    echo "Storage symlink already exists, skipping creation"
elif [ -d "/var/www/html/public/storage" ]; then
    echo "Storage exists as a directory, removing and recreating as symlink"
    rm -rf /var/www/html/public/storage || echo "Failed to remove existing storage directory"
    su-exec www-data php artisan storage:link || echo "Storage link creation failed"
else
    echo "Creating storage symlink"
    su-exec www-data php artisan storage:link || echo "Storage link creation failed"
fi

# ============================================
# Run package:discover (skipped during composer install)
# ============================================
echo "Running package:discover..."
su-exec www-data php artisan package:discover --ansi || echo "Package discovery failed or already run"

# ============================================
# Wait for database if DB_HOST is set
# ============================================
if [ -n "$DB_HOST" ]; then
    echo "Waiting for database connection..."
    max_attempts=30
    attempt=0
    while [ $attempt -lt $max_attempts ]; do
        if nc -z "$DB_HOST" "${DB_PORT:-5432}"; then
            echo "Database is ready!"
            break
        fi
        attempt=$((attempt + 1))
        echo "Waiting for database... (attempt $attempt/$max_attempts)"
        sleep 2
    done
    if [ $attempt -eq $max_attempts ]; then
        echo "Warning: Database connection not established after $max_attempts attempts"
    fi
else
    echo "DB_HOST not set, skipping database wait"
fi

# ============================================
# Run migrations if enabled
# ============================================
if [ "$RUN_MIGRATIONS" = "true" ]; then
    echo "Running database migrations..."
    su-exec www-data php artisan migrate --force || echo "Migrations failed or already run"
else
    echo "RUN_MIGRATIONS not set to true, skipping migrations"
fi

# ============================================
# Verify writable directories as www-data user
# ============================================
echo "Verifying directory write permissions..."

# Display current permissions
echo "Current permissions:"
ls -la /var/www/html/storage/framework/ || echo "Failed to list framework directory"
ls -la /var/www/html/bootstrap/ || echo "Failed to list bootstrap directory"

if ! su-exec www-data test -w /var/www/html/storage/framework/views 2>/dev/null; then
    echo "WARNING: storage/framework/views not writable by www-data, attempting fix..."
    chmod -R 777 /var/www/html/storage/framework/views || echo "Failed to chmod views directory"
    chown -R www-data:www-data /var/www/html/storage/framework/views || echo "Failed to chown views directory"
fi

if ! su-exec www-data test -w /var/www/html/bootstrap/cache 2>/dev/null; then
    echo "WARNING: bootstrap/cache not writable by www-data, attempting fix..."
    chmod -R 777 /var/www/html/bootstrap/cache || echo "Failed to chmod bootstrap/cache"
    chown -R www-data:www-data /var/www/html/bootstrap/cache || echo "Failed to chown bootstrap/cache"
fi

echo "Testing write access to critical directories..."
su-exec www-data touch /var/www/html/storage/framework/views/.write-test && \
    su-exec www-data rm /var/www/html/storage/framework/views/.write-test && \
    echo "✓ storage/framework/views is writable by www-data" || \
    echo "✗ WARNING: storage/framework/views is NOT writable by www-data"

su-exec www-data touch /var/www/html/bootstrap/cache/.write-test && \
    su-exec www-data rm /var/www/html/bootstrap/cache/.write-test && \
    echo "✓ bootstrap/cache is writable by www-data" || \
    echo "✗ WARNING: bootstrap/cache is NOT writable by www-data"

# Display PHP temp directory configuration
echo "PHP temp directory configuration:"
php -r "echo 'sys_get_temp_dir(): ' . sys_get_temp_dir() . PHP_EOL;"
php -r "echo 'sys_temp_dir ini: ' . ini_get('sys_temp_dir') . PHP_EOL;"
php -r "echo 'upload_tmp_dir ini: ' . ini_get('upload_tmp_dir') . PHP_EOL;"
echo "Laravel VIEW_COMPILED_PATH: ${VIEW_COMPILED_PATH:-not set, using default}"

# Test that Laravel can determine the compiled view path
echo "Testing Laravel view configuration..."
php artisan tinker --execute="echo 'Compiled view path: ' . config('view.compiled') . PHP_EOL; echo 'Path exists: ' . (file_exists(config('view.compiled')) ? 'yes' : 'no') . PHP_EOL; echo 'Path writable: ' . (is_writable(config('view.compiled')) ? 'yes' : 'no') . PHP_EOL;" || echo "Failed to check Laravel view config"

# ============================================
# Clear and cache configurations (run as www-data)
# ============================================
echo "Optimizing application..."
su-exec www-data php artisan config:clear || echo "Config clear failed"
su-exec www-data php artisan cache:clear || echo "Cache clear failed"
su-exec www-data php artisan route:clear || echo "Route clear failed"
su-exec www-data php artisan view:clear || echo "View clear failed"
su-exec www-data php artisan event:clear || echo "Event clear failed"

# Only cache if APP_KEY is set (not during image build)
if [ -n "$APP_KEY" ]; then
    echo "Caching configurations..."
    su-exec www-data php artisan config:cache || echo "Config cache failed"
    su-exec www-data php artisan route:cache || echo "Route cache failed"
    # DO NOT cache views during startup - let them compile on-demand
    # view:cache creates precompiled Blade templates that may have permission issues
    echo "Skipping view:cache - views will compile on first request"
    su-exec www-data php artisan event:cache || echo "Event cache failed"
else
    echo "APP_KEY not set, skipping config caching"
fi

# ============================================
# Optimize Composer autoloader
# ============================================
echo "Optimizing Composer autoloader..."
composer dump-autoload --optimize || echo "Composer optimization failed"

# ============================================
# Configure Nginx to use Render PORT
# ============================================
if [ -n "$PORT" ]; then
    echo "Configuring Nginx to use port $PORT..."
    sed -i "s/listen 8080;/listen $PORT;/g" /etc/nginx/nginx.conf || echo "Failed to configure Nginx port"
else
    echo "PORT not set, using default 8080"
fi

# ============================================
# Verify Supervisor configuration
# ============================================
echo "Verifying Supervisor configuration..."
if [ ! -f "/etc/supervisor/conf.d/supervisord.conf" ]; then
    echo "ERROR: Supervisor configuration file not found!"
    exit 1
fi
echo "Supervisor configuration found"

# ============================================
# Start services
# ============================================
echo "=========================================="
echo "Starting Supervisor..."
echo "=========================================="
exec "$@"
