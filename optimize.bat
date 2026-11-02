@echo off
REM One-time performance setup for Windows / XAMPP, run from the project
REM root. Applies database migrations (incl. the performance indexes) and
REM caches config/routes/views/events for maximum request speed.
REM See PERFORMANCE.md. To undo: php artisan optimize:clear

cd /d "%~dp0"

php artisan migrate --force
php artisan optimize

echo.
echo Done. If you change .env (e.g. APP_DEBUG), re-run this script so the
echo cached config picks it up.
pause
