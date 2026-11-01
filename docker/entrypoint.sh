#!/bin/bash
# Post-startup setup: run inside the container after `docker compose up -d`
# Usage: docker compose exec laravel.test bash docker/entrypoint.sh
set -e

cd /var/www/html

echo "Building frontend assets..."
npm install && npm run build

echo "Running migrations..."
php artisan migrate --force --no-interaction

QUOTE_COUNT=$(php artisan tinker --execute 'echo App\Models\RawQuote::count();' 2>/dev/null | tr -d '[:space:]')

if [ "$QUOTE_COUNT" = "0" ] || [ -z "$QUOTE_COUNT" ]; then
    echo "Database is empty — seeding demo data..."
    php artisan db:seed --force --no-interaction
else
    echo "Database has ${QUOTE_COUNT} quotes — skipping seed."
fi

echo "Refreshing materialized views..."
php artisan views:refresh

echo "Generating investment memo..."
php artisan memo:generate

echo "Setup complete."
