FROM composer:2 as composer

COPY composer* /app/
COPY config/ /app/config
COPY src/ /app/src

RUN composer install --no-ansi --no-progress --no-interaction --no-dev -o -a --no-scripts --ignore-platform-reqs

FROM ghcr.io/medleybox/php-fpm:master as vault
COPY --from=ghcr.io/medleybox/audiowaveform-alpine:1.6.0 /bin/audiowaveform /usr/local/bin/audiowaveform

ENV POSTGRES_DB=medleybox_vault \
    POSTGRES_USER=medleybox \
    MESSENGER_TRANSPORT_DSN='redis://redis:6379/messages/symfony/consumer?auto_setup=true&delete_after_ack=true&serializer=1&stream_max_entries=0&dbindex=4'

WORKDIR /var/www

RUN apk add --no-cache ca-certificates curl ffmpeg python3 gnupg nginx \
  && apk add --no-cache --virtual .pip-deps py-pip gcc libc-dev zlib-static libpng-static boost-static python3-dev \
  && pip install --no-cache-dir --no-color -U yt-dlp \
  && pip cache purge \
  && apk del .pip-deps

COPY nginx.conf /etc/nginx/nginx.conf
COPY php.ini /usr/local/etc/php/conf.d/php-common.ini
COPY config /var/www/config
COPY public/index.php /var/www/public/index.php
COPY bin/console /var/www/bin/console
COPY bin/docker-entrypoint* /var/www/bin/

RUN mkdir -p /var/tmp/nginx/client_body && chmod 777 /var/tmp/nginx/client_body \
    && mkdir -p /var/www/log/nginx && chmod 777 /var/www/log/nginx \
    && touch /var/www/log/nginx/error.log && chmod 777 /var/www/log/nginx/error.log \
    && ln -sf /dev/stdout /var/log/nginx/access.log && ln -sf /dev/stderr /var/log/nginx/error.log

ENTRYPOINT ["/var/www/bin/docker-entrypoint"]

COPY --from=composer /app/vendor /var/www/vendor
COPY src/ /var/www/src

RUN chmod +x /var/www/bin/* \
    && mkdir -p /var/www/var/tmp \
    && chmod 777 /var/www/var/tmp \
    && chown -Rf 82:82 /var/www
