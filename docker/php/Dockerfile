FROM php:8.2-fpm-alpine

# نصب وابستگی‌های سیستم
RUN apk add --no-cache \
    git curl zip unzip \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev icu-dev oniguruma-dev \
    supervisor

# نصب افزونه‌های PHP
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j$(nproc) \
    gd pdo pdo_mysql mysqli zip intl mbstring bcmath opcache exif pcntl

# نصب Composer
COPY --from=composer:2.6 /usr/bin/composer /usr/bin/composer

# تنظیمات PHP برای production
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.memory_consumption=192'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.fast_shutdown=1'; \
} > /usr/local/etc/php/conf.d/opcache.ini

# تنظیمات session دائمی
RUN { \
    echo 'session.save_handler = files'; \
    echo 'session.save_path = /var/www/html/storage/sessions'; \
    echo 'session.gc_maxlifetime = 7200'; \
    echo 'session.cookie_httponly = 1'; \
    echo 'session.use_strict_mode = 1'; \
    echo 'session.cookie_samesite = Lax'; \
} > /usr/local/etc/php/conf.d/sessions.ini

# تنظیمات عمومی PHP
RUN { \
    echo 'upload_max_filesize = 512M'; \
    echo 'post_max_size = 512M'; \
    echo 'max_execution_time = 300'; \
    echo 'max_input_time = 300'; \
    echo 'memory_limit = 256M'; \
    echo 'display_errors = Off'; \
    echo 'log_errors = On'; \
    echo 'error_log = /var/www/html/storage/logs/php-errors.log'; \
    echo 'date.timezone = Asia/Tehran'; \
} > /usr/local/etc/php/conf.d/signage.ini

COPY docker/php/conf.d/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

# ساخت پوشه‌های ضروری
RUN mkdir -p \
    storage/sessions \
    storage/logs \
    storage/cache \
    storage/temp \
    public/uploads/media \
    public/uploads/thumbnails

# کپی composer files ابتدا (کش Docker)
COPY composer.json composer.lock* ./

# نصب وابستگی‌ها
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-scripts 2>/dev/null \
    || echo "Composer install skipped (no composer.lock)"

# کپی همه فایل‌ها
COPY . .

# تنظیم دسترسی‌ها
RUN chown -R www-data:www-data \
    storage/ \
    public/uploads/ \
 && chmod -R 755 \
    storage/ \
    public/uploads/

# سلامت PHP-FPM
HEALTHCHECK --interval=30s --timeout=5s --start-period=30s --retries=3 \
    CMD php-fpm -t || exit 1

EXPOSE 9000

CMD ["php-fpm"]

# ─── FFmpeg برای Transcoder ───────────────────────────────────
RUN apk add --no-cache ffmpeg

# ─── Redis extension ──────────────────────────────────────────
RUN apk add --no-cache $PHPIZE_DEPS \
 && pecl install redis \
 && docker-php-ext-enable redis \
 && apk del $PHPIZE_DEPS

# ─── ابزارهای enterprise ──────────────────────────────────────
RUN apk add --no-cache \
    imagemagick \
    imagemagick-dev \
    ghostscript \
    shadow \
    openssl
