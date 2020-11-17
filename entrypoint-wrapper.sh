#!/bin/sh
set -e
php /var/www/scripts/database-migration.php
exec docker-php-entrypoint apache2-foreground
