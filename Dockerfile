FROM alpine:3.24 AS base

RUN set -eux; \
    apk add --no-cache --repository=http://dl-cdn.alpinelinux.org/alpine/v3.24/community \
        php84 \
        php84-curl \
        php84-gd \
        php84-dom \
        php84-gettext \
        php84-gmp \
        php84-iconv \
        php84-intl \
        php84-json \
        php84-mbstring \
        php84-pdo_mysql \
        php84-pecl-yaml \
        php84-phar \
        php84-xml \
        php84-zlib \
    ; \
    ln -sf /usr/bin/php81 /usr/bin/php


FROM composer:latest AS vendor

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
