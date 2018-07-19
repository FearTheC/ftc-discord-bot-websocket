FROM php:7.2-fpm-alpine

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    docker-php-ext-install bcmath; \
     sed -i '/phpize/i \
    [[ ! -f "config.m4" && -f "config0.m4" ]] && mv config0.m4 config.m4' \
    /usr/local/bin/docker-php-ext-configure; \
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer; \
    mkdir /app && \
    rm -rf /var/cache/apk/*

WORKDIR /app

COPY . /app/
COPY entrypoint.sh /

RUN cd /app && composer install --no-dev -o && \
    cp -p /app/config/bot.local.php.dist /app/config/bot.local.php && \
    cp -p /app/config/broker.local.php.dist /app/config/broker.local.php && \
    cp -p /app/config/cache.local.php.dist /app/config/cache.local.php

ENTRYPOINT ["/entrypoint.sh"]
