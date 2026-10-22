# syntax=docker/dockerfile:1
ARG PHP_IMAGE=php:8.5-apache-trixie
ARG COMPOSER_IMAGE=composer:2
FROM ${COMPOSER_IMAGE} AS composer
FROM ${PHP_IMAGE} AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
       libcurl4-openssl-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev \
       libonig-dev libxml2-dev libicu-dev libgmp-dev \
       ca-certificates curl unzip default-mysql-client gosu util-linux \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" curl gd mysqli mbstring simplexml intl gmp \
    && rm -rf /var/lib/apt/lists/* \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && a2enmod rewrite headers \
    && sed -i 's/Listen 80/Listen 8080/' /etc/apache2/ports.conf

COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /opt/aowow
COPY composer.json composer.lock ./
RUN composer install --no-dev --prefer-dist --no-interaction --no-progress --no-plugins --no-scripts \
    && composer check-platform-reqs --no-dev \
    && php -r 'foreach (["SimpleXML","gd","mysqli","mbstring","fileinfo","curl","intl","gmp"] as $e) { if (!extension_loaded($e)) { throw new RuntimeException("Missing extension: ".$e); } }' \
    && rm -rf /root/.composer /root/.cache
COPY . .
COPY docker/apache/site.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php/production.ini /usr/local/etc/php/conf.d/aowow.ini
COPY docker/scripts/ /usr/local/lib/aowow/
RUN chmod 0755 /usr/local/lib/aowow/entrypoint.sh \
    && mkdir -p /var/www/html /usr/local/share/aowow-seed \
    && cp docker/apache/index.php /var/www/html/index.php \
    && cp .htaccess /var/www/html/.htaccess \
    && cp crossdomain.xml /var/www/html/ \
    && for path in cache config datasets static/download static/widgets static/js static/uploads static/images/wow static/wowsounds static/modelviewer setup/mpqdata; do \
         mkdir -p "$path" "/usr/local/share/aowow-seed/$path"; \
         cp -a "$path/." "/usr/local/share/aowow-seed/$path/"; \
       done \
    && chown -R root:root /opt/aowow /var/www/html /usr/local/lib/aowow /usr/local/share/aowow-seed \
    && chmod -R go-w /opt/aowow /var/www/html /usr/local/lib/aowow /usr/local/share/aowow-seed

ENV APACHE_RUN_DIR=/tmp/apache2 APACHE_PID_FILE=/tmp/apache2/apache2.pid \
    PHP_MEMORY_LIMIT=1536M PHP_MAX_EXECUTION_TIME=120 TZ=UTC \
    APACHE_MAX_REQUEST_WORKERS=4 \
    AOWOW_ROBOTS_FILE=static/download/robots.txt
USER www-data
EXPOSE 8080
STOPSIGNAL SIGTERM
HEALTHCHECK --interval=30s --timeout=10s --start-period=30s --retries=3 \
    CMD php /usr/local/lib/aowow/healthcheck.php
ENTRYPOINT ["/usr/local/lib/aowow/entrypoint.sh"]
CMD ["apache2-foreground"]
