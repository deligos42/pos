#!/bin/sh
set -e

rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork rewrite >/dev/null

if [ -n "$PORT" ]; then
    sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
    sed -ri "s/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf
fi

exec apache2-foreground
