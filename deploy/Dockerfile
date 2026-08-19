# The shipped image: nginx, PHP-FPM, the queue workers and the scheduler, all in
# one container run by supervisord.
#
# One container rather than four services from one image: a self-hosted instance
# is one machine, and this way `docker compose up -d` brings up something that
# works without anybody reading a process diagram. Supervisor restarts each
# process on its own, so a worker dying does not take the site with it.
FROM php:8.5-fpm

RUN apt-get update && apt-get install -y --no-install-recommends \
    nginx \
    supervisor \
    curl \
    unzip \
    libpq-dev \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libicu-dev \
    && rm -rf /var/lib/apt/lists/*

# `pdo`, `mbstring` and `opcache` are already built into this image — asking for
# them here fails the build with "cannot stat 'modules/*'", because a static
# extension produces no module to copy.
RUN docker-php-ext-install pdo_pgsql pgsql bcmath pcntl gd zip intl

# Redis for the queue, the cache, the crawl rate limiter and the per-mailbox
# locks — all four, so this one is not optional.
RUN pecl install redis && docker-php-ext-enable redis

# Node only to build the front end. `yarn build` shells out to
# `php artisan wayfinder:generate`, so the assets cannot be built in a stage
# without PHP — it is built here and thrown away below.
RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && npm install -g corepack \
    && corepack enable \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Dependencies before the code, so editing a file does not re-resolve them.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json yarn.lock .yarnrc.yml .npmrc ./
COPY .yarn ./.yarn
RUN yarn install --immutable

COPY . .

RUN composer dump-autoload --optimize --classmap-authoritative \
    && yarn build \
    && rm -rf node_modules .yarn/cache

COPY deploy/nginx.conf /etc/nginx/sites-available/default
COPY deploy/supervisord.conf /etc/supervisor/conf.d/eveil.conf
COPY deploy/entrypoint.sh /usr/local/bin/entrypoint

RUN chmod +x /usr/local/bin/entrypoint \
    && chown -R www-data:www-data storage bootstrap/cache

EXPOSE 80

ENTRYPOINT ["entrypoint"]

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/supervisord.conf"]
