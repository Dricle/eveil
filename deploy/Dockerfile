# The shipped image: app, workers and scheduler all run from this one build.
#
# FrankenPHP rather than php-fpm behind nginx: one process serves HTTP and runs
# PHP, so a self-hosted instance is one container instead of two plus a config
# file nobody wants to maintain. Caddy also handles TLS on its own, which is the
# other half of "up in a few minutes".
FROM dunglas/frankenphp:1-php8.5-alpine AS base

RUN install-php-extensions \
    pdo_pgsql \
    redis \
    intl \
    zip \
    gd \
    bcmath \
    pcntl \
    opcache

WORKDIR /app

# --- Build stage: PHP and Node in one place, deliberately.
#
# `yarn build` shells out to `php artisan wayfinder:generate`, so an assets stage
# without PHP cannot build the front end. Installing Node here is less trouble
# than splitting the build and hand-maintaining the generated route modules.
FROM base AS build

RUN apk add --no-cache nodejs npm && npm install -g corepack && corepack enable

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Dependencies first, so a code change does not re-resolve them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json yarn.lock .yarnrc.yml .npmrc ./
COPY .yarn ./.yarn
RUN yarn install --immutable

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && yarn build \
    && rm -rf node_modules .yarn

# --- Runtime: the built app, and nothing that built it.
FROM base AS runtime

COPY --from=build /app /app

# Caddy needs to write the compiled views and the framework caches; everything
# else stays read-only as far as the app is concerned.
RUN chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+w storage bootstrap/cache

COPY deploy/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

ENTRYPOINT ["entrypoint"]

# Overridden by the worker and scheduler services, which run the same image.
CMD ["frankenphp", "php-server", "--listen", ":80", "--root", "public/"]
