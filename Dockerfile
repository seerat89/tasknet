FROM composer:2 AS composer

FROM php:8.5-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-install -j"$(nproc)" pdo_pgsql pgsql zip intl bcmath pcntl \
    && rm -rf /var/lib/apt/lists/*

# Fail the build clearly if any extension TaskNet needs is unavailable
# (sodium, mbstring, pdo_sqlite and sqlite3 come from the base image; verified here, not assumed).
RUN php -r '$missing = array_filter(["sodium", "pdo_pgsql", "pgsql", "zip", "mbstring", "pdo_sqlite", "sqlite3", "intl", "bcmath", "pcntl"], fn ($extension) => ! extension_loaded($extension)); if ($missing) { fwrite(STDERR, "Missing required PHP extensions: ".implode(", ", $missing).PHP_EOL); exit(1); }'

COPY --from=composer /usr/bin/composer /usr/local/bin/composer

ENV COMPOSER_ALLOW_SUPERUSER=1

WORKDIR /var/www/html

EXPOSE 8000
