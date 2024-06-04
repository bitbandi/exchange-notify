FROM alpine AS base

RUN set -eux; \
    apk add --no-cache \
        php82 \
        php82-curl \
        php82-gd \
        php82-dom \
        php82-gettext \
        php82-gmp \
        php82-iconv \
        php82-intl \
        php82-json \
        php82-mbstring \
        php82-pdo_mysql \
        php82-pecl-yaml \
        php82-phar \
        php82-xml \
        php82-zlib \
    ; \
    ln -sf /usr/bin/php81 /usr/bin/php


FROM composer:latest as vendor

COPY composer.json composer.lock ./
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

FROM base AS final

ARG user=e-notify
ARG uid=1000

WORKDIR /var/www

RUN adduser --uid $uid --disabled-password --home /var/www $user \
    && addgroup $user root

COPY --chown=$uid:$uid . /var/www

COPY --from=vendor --chown=$uid:$uid /app/vendor /var/www/vendor
