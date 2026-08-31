# ============================================================
# 开放管理后台 — 生产 Dockerfile
# Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
# ============================================================

FROM php:8.3-cli-alpine

RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# 镜像加速 + 基础依赖
RUN sed -i 's/dl-cdn.alpinelinux.org/mirrors.aliyun.com/g' /etc/apk/repositories \
    && apk update --no-cache \
    && apk add --no-cache \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
        libxml2-dev \
        curl \
        git \
        unzip \
        autoconf \
        gcc \
        g++ \
        make \
        libc-dev \
        libevent-dev \
        libevent \
        openssl-dev \
    && docker-php-source extract

# PHP 扩展
RUN docker-php-ext-install -j$(nproc) \
        pdo pdo_mysql \
        pcntl \
        mbstring \
        gd \
        xml \
        dom \
        xmlwriter \
    && pecl channel-update pecl.php.net \
    && pecl install event redis \
    && docker-php-ext-enable opcache pcntl event redis

# OPcache 生产配置
RUN echo "opcache.enable=1" >> "$PHP_INI_DIR/php.ini" \
    && echo "opcache.enable_cli=1" >> "$PHP_INI_DIR/php.ini" \
    && echo "opcache.memory_consumption=128" >> "$PHP_INI_DIR/php.ini" \
    && echo "opcache.max_accelerated_files=10000" >> "$PHP_INI_DIR/php.ini"

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# 编译工具链仅在 pecl 构建期需要，装完即删保持镜像精简
RUN apk del autoconf gcc g++ make libc-dev \
    && docker-php-source delete \
    && rm -rf /var/cache/apk/*

RUN mkdir -p /app
WORKDIR /app

# 依赖安装（利用 Docker 层缓存）
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --optimize-autoloader

COPY . .

EXPOSE 8788
CMD ["php", "start.php", "start"]
