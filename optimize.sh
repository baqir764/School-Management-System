#!/bin/sh
# One-time performance setup (Linux/macOS), run from the project root.
# Applies database migrations (incl. the performance indexes) and caches
# config/routes/views/events for maximum request speed.
# See PERFORMANCE.md. To undo: php artisan optimize:clear

cd "$(dirname "$0")"

php artisan migrate --force
php artisan optimize

echo
echo "Done. If you change .env (e.g. APP_DEBUG), re-run this script so the"
echo "cached config picks it up."
