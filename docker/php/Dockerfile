# syntax=docker/dockerfile:1.7
#
# FugaziByUs PHP image. One Dockerfile, several targets:
#   dev   - local development (bind-mounted code, Node for Vite, Xdebug available)
#   build - compiles Composer deps + Vite assets (CI / prod only)
#   prod  - lean PHP runtime image (php-fpm, queue, scheduler) pushed to DigitalOcean Container Registry
#   web   - nginx with TLS and the compiled public/ assets, for staging and production
# Both hosted images are built from the same build stage, so they always match.

ARG PHP_VERSION=8.4
ARG NODE_VERSION=24

############################
# base: PHP-FPM + extensions
############################
FROM php:${PHP_VERSION}-fpm-bookworm AS base

COPY --from=mlocati/php-extension-installer:2 /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
        pdo_mysql bcmath intl zip opcache pcntl exif \
    && apt-get update \
    && apt-get install -y --no-install-recommends default-mysql-client unzip curl \
    && rm -rf /var/lib/apt/lists/*

COPY docker/php/conf.d/app.ini /usr/local/etc/php/conf.d/zz-app.ini

WORKDIR /var/www/html

############################
# node: Node binaries to copy into other stages
############################
FROM node:${NODE_VERSION}-bookworm-slim AS node

############################
# dev: local development
############################
FROM base AS dev

ARG UID=1000
ARG GID=1000

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm \
    && ln -s /usr/local/lib/node_modules/npm/bin/npx-cli.js /usr/local/bin/npx \
    && install-php-extensions xdebug \
    && cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini \
    && groupmod -o -g ${GID} www-data && usermod -o -u ${UID} -g ${GID} www-data \
    && mkdir -p /var/www/html/node_modules && chown -R www-data:www-data /var/www

COPY docker/php/conf.d/xdebug.ini /usr/local/etc/php/conf.d/zz-xdebug.ini
COPY docker/php/conf.d/dev.ini /usr/local/etc/php/conf.d/zz-dev.ini

USER www-data

############################
# build: vendor + compiled assets
############################
FROM base AS build

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY --from=node /usr/local/bin/node /usr/local/bin/node
COPY --from=node /usr/local/lib/node_modules /usr/local/lib/node_modules
RUN ln -s /usr/local/lib/node_modules/npm/bin/npm-cli.js /usr/local/bin/npm

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY package.json package-lock.json* .npmrc pnpm-workspace.yaml ./
RUN npm ci --include=optional

COPY . .
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev \
    && php artisan package:discover --ansi \
    && npm run build \
    && rm -rf node_modules

############################
# prod: runtime image
############################
FROM base AS prod

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
COPY docker/php/conf.d/prod.ini /usr/local/etc/php/conf.d/zz-prod.ini

COPY --from=build --chown=www-data:www-data /var/www/html /var/www/html
COPY --chmod=755 docker/php/entrypoint.prod.sh /usr/local/bin/entrypoint

USER www-data
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]

############################
# web: nginx for staging and production (TLS via Let's Encrypt, see deploy/compose.yml)
############################
FROM nginx:1.29-alpine AS web

RUN apk add --no-cache openssl \
    && rm /etc/nginx/conf.d/default.conf

COPY docker/nginx/prod.conf.template /etc/nginx/templates/default.conf.template
COPY docker/nginx/security-headers.conf /etc/nginx/snippets/security-headers.conf
COPY --chmod=755 docker/nginx/entrypoint.d/ /docker-entrypoint.d/
COPY --from=build /var/www/html/public /var/www/html/public

# Only APP_DOMAIN is substituted into the template; nginx's own $variables stay as they are.
ENV NGINX_ENVSUBST_FILTER=^APP_DOMAIN$
