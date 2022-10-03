FROM composer:2 as composer

COPY composer* /app/
COPY config/ /app/config
COPY src/ /app/src

RUN composer install -vvv --no-dev -o -a --no-scripts --ignore-platform-reqs

FROM php:8.1-fpm-alpine as vault

ENV APP_ENV=prod \
  POSTGRES_DB=medleybox_webapp \
  POSTGRES_USER=medleybox \
  POSTGRES_PASSWORD='' \
  TZ='Europe/London' \
  PAGER='busybox less' \
  MINIO_ENDPOINT='http://minio:9000' \
  REDIS_VERSION='5.3.7' \
  EXT_AMQP_VERSION=master \
  MESSENGER_TRANSPORT_DSN='redis://redis:6379/messages/symfony/consumer?auto_setup=true&delete_after_ack=true&serializer=1&stream_max_entries=0&dbindex=4' \
  PATH='/var/www/bin:/var/www/vendor/bin:/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin'

WORKDIR /var/www

RUN curl -L -o /tmp/redis.tar.gz https://github.com/phpredis/phpredis/archive/${REDIS_VERSION}.tar.gz \
    && tar xfz /tmp/redis.tar.gz \
    && rm -r /tmp/redis.tar.gz \
    && mkdir -p /usr/src/php/ext \
    && mv phpredis-* /usr/src/php/ext/redis \
    && apk add --no-cache --virtual .build-deps \
    autoconf \
    binutils \
    freetype-dev \
    g++ \
    git \
    icu-dev \
    icu-libs \
    libxml2-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    libzip-dev \
    make \
    postgresql-dev \
    rabbitmq-c-dev \
    python3-dev \
# =========================================================================== #\
    && apk add --no-cache \
        aria2 \
        freetype \
        icu-libs \
        libjpeg-turbo \
        libpng \
        libwebp \
        libxslt \
        libzip \
        tzdata \
        postgresql-libs \
        rabbitmq-c \
        gcc zlib-static libpng-static boost-static \
        ca-certificates curl ffmpeg python3 gnupg py-pip nginx \
    && pip install -U yt-dlp \
# =========================================================================== #\
    && docker-php-ext-configure gd -enable-gd --with-freetype --with-jpeg --with-webp \
    && git clone --branch $EXT_AMQP_VERSION --depth 1 https://github.com/php-amqp/php-amqp.git /usr/src/php/ext/amqp \
    && cd /usr/src/php/ext/amqp && git submodule update --init \
    && docker-php-ext-install -j "$(getconf _NPROCESSORS_ONLN)" \
        amqp \
        exif \
        gd \
        intl \
        opcache \
        pdo_pgsql \
        redis \
        zip \
    && docker-php-ext-enable amqp \
# =========================================================================== #\
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* \
    && rm -rf /usr/src

COPY nginx.conf /etc/nginx/nginx.conf
COPY --from=ghcr.io/medleybox/audiowaveform-alpine:1.6.0 /bin/audiowaveform /usr/local/bin/audiowaveform
COPY --from=composer /app/vendor /var/www/vendor
COPY php.ini /usr/local/etc/php/conf.d/php-common.ini
COPY config /var/www/config
COPY public/index.php /var/www/public/index.php
COPY bin/console /var/www/bin/console
COPY bin/docker-entrypoint* /var/www/bin/

RUN mkdir -p /var/tmp/nginx/client_body && chmod 777 /var/tmp/nginx/client_body \
    && mkdir -p /var/www/log/nginx && chmod 777 /var/www/log/nginx \
    && touch /var/www/log/nginx/error.log && chmod 777 /var/www/log/nginx/error.log \
    && ln -sf /dev/stdout /var/log/nginx/access.log && ln -sf /dev/stderr /var/log/nginx/error.log \
    && sed -i 's/pm.max_children = 6/pm.max_children = 4/' /usr/local/etc/php-fpm.d/www.conf

ENTRYPOINT ["/var/www/bin/docker-entrypoint"]

COPY src/ /var/www/src

RUN chmod +x /var/www/bin/* \
    && mkdir -p /var/www/var/tmp \
    && chmod 777 /var/www/var/tmp \
    && chown -Rf 82:82 /var/www
