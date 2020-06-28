FROM xigen/php:cli-composer74 as composer

COPY composer* /app/
COPY config/ /app/config
COPY src/ /app/src

RUN composer install -vvv -o -a --no-scripts --ignore-platform-reqs

FROM xigen/php:fpm-74

ENV APP_ENV dev

COPY --from=composer /app/vendor /var/www/vendor

RUN apk add --update --no-cache ca-certificates curl ffmpeg python3 gnupg py-pip nginx \
    && pip install -U youtube-dl

COPY bin/ /var/www/bin
COPY config/ /var/www/config
COPY public/index.php /var/www/public/index.php
COPY src/ /var/www/src

RUN chmod +x /var/www/bin/console \
    && mkdir /var/www/var/tmp \
    && chmod 777 /var/www/var/tmp \
    && chown -Rf 82:82 /var/www

RUN ls -alsh /var/www/config

COPY nginx.conf /etc/nginx/nginx.conf

RUN mkdir -p /var/tmp/nginx/client_body && chmod 777 /var/tmp/nginx/client_body \
    && mkdir -p /var/www/log/nginx && chmod 777 /var/www/log/nginx \
    && touch /var/www/log/nginx/error.log && chmod 777 /var/www/log/nginx/error.log \
    && ln -sf /dev/stdout /var/log/nginx/access.log && ln -sf /dev/stderr /var/log/nginx/error.log

ENTRYPOINT ["/var/www/bin/docker-entrypoint"]
