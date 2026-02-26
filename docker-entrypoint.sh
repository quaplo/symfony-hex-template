#!/bin/bash
set -e

echo "Running database migrations..."
php bin/console doctrine:migrations:migrate --no-interaction

echo "Starting PHP server..."
exec php -S 0.0.0.0:8000 -t public
