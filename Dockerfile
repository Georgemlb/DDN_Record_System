# Stage 1: Build frontend assets with Node + PHP (for Wayfinder if needed)
FROM node:22-alpine AS assets
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.ts tsconfig.json ./
COPY public ./public
RUN npm run build

# Stage 2: Production PHP runtime
FROM php:8.3-cli-alpine
WORKDIR /var/www/html

RUN apk add --no-cache \
    libpq-dev \
    sqlite-dev \
    oniguruma-dev \
    libzip-dev \
    icu-dev \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql pdo_sqlite mbstring zip intl pcntl bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY . .
COPY --from=assets /app/public/build ./public/build

RUN composer install --no-dev --optimize-autoloader --no-interaction \
    && chmod +x docker/start.sh

EXPOSE 8080

CMD ["sh", "docker/start.sh"]
