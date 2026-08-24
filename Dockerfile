# ============================================
# Stage 1: Frontend Build (Node.js + Vite)
# ============================================
FROM node:20-alpine AS frontend-builder

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install all dependencies (including devDependencies for Vite)
RUN npm ci

# Copy application files for Vite build
COPY . .

# Build frontend assets
RUN npm run build

# Clean up node_modules to reduce layer size
RUN npm cache clean --force && rm -rf /root/.npm /root/.cache

# ============================================
# Stage 2: Production (PHP 8.3-FPM + Nginx)
# ============================================
FROM php:8.3-fpm-alpine AS production

# Install Alpine system dependencies
RUN apk add --no-cache \
    # Build tools for PHP extensions
    autoconf \
    g++ \
    make \
    # Image processing libraries
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libwebp-dev \
    # Database libraries
    libpq-dev \
    # Compression
    libzip-dev \
    # Unicode/multibyte
    oniguruma-dev \
    # Web server and process manager
    nginx \
    supervisor \
    # Misc
    curl \
    zip \
    unzip \
    git \
    bash \
    # Network tools for database connection check
    netcat-openbsd \
    # User switching tool for permission verification
    su-exec

# Configure and install PHP extensions
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    && docker-php-ext-install -j$(nproc) \
    gd \
    pdo \
    pdo_pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    zip \
    opcache \
    && rm -rf /var/lib/apt/lists/*

# Install Composer globally
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first (better layer caching)
COPY composer.json composer.lock ./

# Copy application files BEFORE composer install
# This ensures artisan exists when composer runs post-autoload-dump
COPY . .

# Install PHP dependencies with --no-scripts to prevent artisan commands during build
# package:discover will run in entrypoint when environment is ready
RUN composer install \
    --no-dev \
    --no-scripts \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    && rm -rf /root/.composer

# Copy built frontend assets from frontend stage
COPY --from=frontend-builder /app/public/build ./public/build

# Create necessary directories for Laravel
RUN mkdir -p \
    storage/framework/{views,cache,sessions} \
    storage/logs \
    storage/app/public \
    bootstrap/cache \
    public/storage \
    storage/temp

# Pre-create laravel.log so PHP-FPM (www-data) always has write access
RUN touch storage/logs/laravel.log

# Create necessary directories for Nginx
RUN mkdir -p \
    /var/log/nginx \
    /var/lib/nginx \
    /var/cache/nginx \
    /run/nginx

# Create necessary directories for Supervisor
RUN mkdir -p \
    /var/log/supervisor \
    /var/run/supervisor

# Create necessary directories for PHP sessions
RUN mkdir -p \
    /var/lib/php/sessions

# Set permissions for Laravel
RUN chown -R www-data:www-data \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /var/www/html/public/storage \
    && chmod -R 777 \
    /var/www/html/storage \
    /var/www/html/bootstrap/cache \
    /var/www/html/public/storage \
    /var/www/html/storage/temp

# Set permissions for Nginx and Supervisor
RUN chown -R www-data:www-data /var/lib/nginx /var/cache/nginx /run/nginx \
    && chown -R root:root /var/log/nginx /var/log/supervisor /var/run/supervisor \
    && chmod -R 755 /var/log/nginx /var/log/supervisor /var/run/supervisor

# Set permissions for PHP sessions
RUN chown -R www-data:www-data /var/lib/php \
    && chmod -R 755 /var/lib/php

# Create dedicated writable PHP temp directory (prevents tempnam() fallback
# to sys_get_temp_dir() which triggers an E_NOTICE on PHP 8.1+)
RUN mkdir -p /tmp/php-tmp \
    && chown -R www-data:www-data /tmp/php-tmp \
    && chmod -R 777 /tmp/php-tmp

# Fix PHP-FPM default error_log configuration in Alpine image
# Remove default pool configs (www.conf, zz-docker.conf) that may have /proc/self/fd/2
# references which cause exit status 78 on Alpine. Our custom configs replace them entirely.
RUN rm -f /usr/local/etc/php-fpm.d/www.conf /usr/local/etc/php-fpm.d/zz-docker.conf \
    && sed -i 's|error_log = /proc/self/fd/2|error_log = /tmp/php-fpm.log|g' /usr/local/etc/php-fpm.conf 2>/dev/null || true

# Copy Nginx configuration
COPY nginx.conf /etc/nginx/nginx.conf

# Copy Supervisor configuration
COPY supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Copy PHP-FPM configuration
COPY php-fpm-global.conf /usr/local/etc/php-fpm.conf
COPY php-fpm.conf /usr/local/etc/php-fpm.d/www.conf

# Create PHP configuration file for temp directory
RUN echo "sys_temp_dir = /tmp/php-tmp" > /usr/local/etc/php/conf.d/temp-dir.ini \
    && echo "upload_tmp_dir = /tmp/php-tmp" >> /usr/local/etc/php/conf.d/temp-dir.ini \
    && echo "session.save_path = /var/lib/php/sessions" >> /usr/local/etc/php/conf.d/temp-dir.ini

# Copy entrypoint script
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# Expose port (Render uses dynamic PORT environment variable)
EXPOSE 8080

# Set entrypoint
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

# Start Supervisor
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
