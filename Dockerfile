FROM ubuntu:jammy 

ARG user=www-data
ARG uid=1000

ARG DEBIAN_FRONTEND=noninteractive

RUN set -eux; \
        apt-get update; \
    	apt-get install -y --no-install-recommends \
            php8.1-cli \
            php8.1-bcmath \
            php8.1-curl \
            php8.1-intl \
            php8.1-mbstring \
            php8.1-mysql \
            php8.1-sqlite3 \
            php8.1-xml \
            php8.1-zip \
        ; \
        rm -rf /var/lib/apt/lists/*

FROM composer:latest as vendor

COPY composer.json composer.lock ./
RUN composer install \
    --ignore-platform-reqs \
    --no-interaction \
    --no-plugins \
    --no-scripts \
    --prefer-dist

FROM base

WORKDIR /var/www


COPY . .

# Create system user to run Composer and Artisan Commands
RUN useradd -G www-data,root -u $uid -d /home/$user $user
RUN mkdir -p /home/$user/.composer && \
    chown -R $user:$user /home/$user

RUN chown -R $uid:$uid /var/www
