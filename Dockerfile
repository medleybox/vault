FROM composer:2 as composer

COPY composer* /app/
COPY config/ /app/config
COPY src/ /app/src

RUN composer install --no-ansi --no-progress --no-interaction --no-dev -o -a --no-scripts --ignore-platform-reqs

FROM ghcr.io/medleybox/php-fpm:master as vault
COPY www-memory.conf /usr/local/etc/php-fpm.d/www-memory.conf
COPY --from=ghcr.io/medleybox/audiowaveform-alpine:1.6.0 /bin/audiowaveform /usr/local/bin/audiowaveform

ENV POSTGRES_DB=medleybox_vault
ENV MESSENGER_TRANSPORT_DSN='redis://redis:6379/messages/symfony/consumer?auto_setup=true&delete_after_ack=true&serializer=1&stream_max_entries=0&dbindex=4'

WORKDIR /var/www

RUN apk add --no-cache ca-certificates curl ffmpeg python3 gnupg \
  && apk add --no-cache --virtual .pip-deps py-pip gcc libc-dev zlib-static libpng-static boost-static python3-dev \
  && pip install --no-cache-dir --no-color -U yt-dlp \
  && pip cache purge \
  && apk del .pip-deps

COPY nginx.conf /etc/nginx/nginx.conf
COPY php.ini /usr/local/etc/php/conf.d/php-common.ini
COPY config /var/www/config
COPY public/index.php /var/www/public/index.php
COPY bin/console /var/www/bin/console
COPY bin/run-tests /var/www/bin/run-tests
COPY bin/docker-entrypoint* /var/www/bin/

COPY --from=composer /app/vendor /var/www/vendor
COPY --from=composer /app/composer* /var/www/
COPY src/ /var/www/src

RUN chmod +x /var/www/bin/* \
    && mkdir -p /var/www/var/tmp \
    && chmod 777 /var/www/var/tmp \
    && chown -Rf 82:82 /var/www
