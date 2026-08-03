#!/bin/sh
set -e

rm -f /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf
a2enmod mpm_prefork rewrite >/dev/null

PORT="${PORT:-8080}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/*.conf

if [ -f /var/www/html/database_init.php ]; then
  php /var/www/html/database_init.php || true
fi

if [ ! -d /var/www/html/logs ]; then
  mkdir -p /var/www/html/logs
fi

exec apache2-foreground
