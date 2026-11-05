#!/bin/sh
# Production entrypoint: warm Laravel caches, then hand off to the container command
# (php-fpm, queue:work or schedule:work). Migrations are run by the deploy script, not here.
set -e
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
exec "$@"
