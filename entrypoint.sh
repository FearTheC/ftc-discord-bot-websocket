#!/bin/sh
cd /app && composer install -o

cp -p /app/config/bot.local.php.dist /app/config/bot.local.php
cp -p /app/config/broker.local.php.dist /app/config/broker.local.php
cp -p /app/config/cache.local.php.dist /app/config/cache.local.php


sed -i "s/'token' => ''/'token' => '$FTCBOT_DISCORD_AUTH_TOKEN'/g" /app/config/bot.local.php

sed -i "s/'host' => ''/'host' => '$FTCBOT_BROKER_HOST'/g" /app/config/broker.local.php
sed -i "s/'username' => ''/'username' => '$FTCBOT_BROKER_USERNAME'/g" /app/config/broker.local.php
sed -i "s/'password' => ''/'password' => '$FTCBOT_BROKER_PASSWORD'/g" /app/config/broker.local.php
sed -i "s/'port' => ''/'port' => '$FTCBOT_BROKER_PORT'/g" /app/config/broker.local.php

sed -i "s/'host' => ''/'host' => '$FTCBOT_WS_CACHE_HOST'/g" /app/config/cache.local.php
sed -i "s/'port' => ''/'port' => '$FTCBOT_WS_CACHE_PORT'/g" /app/config/cache.local.php
sed -i "s/'version' => ''/'version' => '$FTCBOT_WS_CACHE_VERSION'/g" /app/config/cache.local.php


exec php /app/public/run.php
