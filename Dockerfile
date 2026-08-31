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

# sockets 编译需内核 uapi 头（linux/sock_diag.h）、zip 扩展需 libzip；独立一层避免大 apk 层缓存失效
RUN apk add --no-cache linux-headers libzip-dev

# PHP 扩展
# sockets 为 pecl event 编译依赖（event 默认 --enable-event-sockets=yes，configure 需 php_sockets.h）
RUN docker-php-ext-install -j$(nproc) \
        pdo pdo_mysql \
        pcntl \
        mbstring \
        gd \
        xml \
        dom \
        xmlwriter \
        sockets \
        zip \
    && pecl channel-update pecl.php.net \
    # event 的 sockets 支持需链接 PHP sockets 的 C 符号（扩展间不做符号导出，运行时必现
    # "socket_import_file_descriptor: symbol not found"）；Workerman 只用 event 事件循环，禁用之
    && pecl install --configureoptions 'enable-event-sockets="no"' event \
    && pecl install redis \
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
