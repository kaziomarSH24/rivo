#!/bin/sh
set -e

# --- Database Wait Logic ---
# Set default values if environment variables are not set
DB_HOST_CHECK=${DB_HOST:-boilerplate-db}
DB_PORT_CHECK=${DB_PORT:-3306}

echo "Attempting to connect to database at: ${DB_HOST_CHECK}:${DB_PORT_CHECK}"

# Loop until the database container is ready to accept connections
while ! nc -z ${DB_HOST_CHECK} ${DB_PORT_CHECK}; do
  echo "Waiting for database connection..."
  sleep 2 # wait for 2 seconds before checking again
done
echo "Database connected successfully!"


# --- Set Permissions ---
echo "Setting storage and cache permissions..."
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache


# --- Laravel Optimization ---
echo "Clearing old Laravel caches..."
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Now, cache everything for production performance
if [ "$INSTALL_DEV" != "true" ]; then
    echo "PRODUCTION MODE: Running optimizations..."

    echo "Caching configurations..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # Ensure composer autoloader is optimized
    composer dump-autoload --optimize

    # Automatic Migrations for Production
    echo "Running database migrations..."
    php artisan migrate --force

else
   echo "DEVELOPMENT MODE: Installing dependencies..."
    composer install --ignore-platform-reqs --no-interaction
    chmod -R 777 storage bootstrap/cache
fi

# --- Role-Based Execution ---
role=${1}
if [ "$role" = "queue" ]; then
    echo "Running the queue worker..."
    shift
    exec php artisan "$@"

elif [ "$role" = "scheduler" ]; then
    echo "Running the scheduler..."
    shift
    exec "$@"

else
    echo "Starting PHP-FPM..."
    exec php-fpm
fi
