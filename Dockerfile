FROM php:8.4-fpm-alpine AS base

ENV DEBIAN_FRONTEND=noninteractive
WORKDIR /var/www

#Install System Dependencies
RUN apk add --no-cache \
    nodejs \
    npm \
    python3 \
    netcat-openbsd \
    fcgi \
    git curl zip unzip libzip-dev libpng-dev libjpeg-turbo-dev \
    freetype-dev libwebp-dev postgresql-dev icu-dev \
    oniguruma-dev \
    build-base autoconf bash shadow && \
    # Install PHP extensions
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install -j$(nproc) gd pdo pdo_mysql pdo_pgsql mbstring exif pcntl bcmath zip intl && \
    # Install redis extension via PECL
    pecl install redis && docker-php-ext-enable redis

#Copy Custom Config & Composer
COPY php/custom.ini /usr/local/etc/php/conf.d/custom.ini
COPY --from=composer:2.8.12 /usr/bin/composer /usr/bin/composer

ARG INSTALL_DEV=false
RUN composer config --global process-timeout 2000

# --- CACHE LAYER OPTIMIZATION STARTS ---

#Copy only dependency files first
COPY composer.json composer.lock package.json package-lock.json ./

RUN rm -f composer.lock

#Install PHP Dependencies (without scripts to avoid errors before code copy)
RUN if [ ${INSTALL_DEV} = true ]; \
    then \
        composer install --no-interaction --no-scripts --optimize-autoloader; \
    else \
        composer install --no-interaction --no-dev --no-scripts --optimize-autoloader; \
    fi

#Install Node Dependencies
RUN npm install

# --- CACHE LAYER OPTIMIZATION ENDS ---
#Copy the rest of the application code
COPY . .

# 7. Final Build Steps
RUN if [ ${INSTALL_DEV} = true ]; \
    then \
        # Finish composer setup for dev
        composer dump-autoload; \
    else \
        # Finish composer setup for prod & Build Assets
        composer dump-autoload --optimize; \
        npm run build; \
        # Remove node_modules in production to save space (Optional, keep if needed for SSR)
        # rm -rf node_modules; \
    fi

# 8. Setup Entrypoint
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
