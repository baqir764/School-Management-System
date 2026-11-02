# Performance & Offline Guide

This app is built to run **fully offline** and fast on any laptop (XAMPP,
no internet needed at runtime). This file explains what was optimized in
the 2026-11 performance pass and what to do on a fresh machine.

## 1. No internet is needed at runtime

- All CSS/JS/fonts are **local files**: Bootstrap 5, Bootstrap Icons,
  Chart.js and the QR libraries are compiled by Vite into `public/build/`
  and `public/vendor/` (committed to the repo). There are **no CDN links,
  no Google Fonts, no external API calls** anywhere in the views.
- Composer/npm packages are only needed once, at setup time
  (`composer install`, `npm ci && npm run build`) - never at runtime.
- The database is local MySQL (XAMPP). Sessions and cache use the
  **file** driver, so no extra services (Redis/Memcached) are required.

## 2. What was optimized in the code (2026-11 pass)

**Database**

- New migration `2026_11_02_000000_add_performance_indexes.php` adds
  indexes on every hot filter/sort column that wasn't covered yet
  (`attendance_records.date`, `fee_payments.payment_date`,
  `fee_invoices.due_date`, `salary_payments.salary_month/payment_date`,
  `exams.start_date/is_published`, `students.status/gender`,
  `teachers/employees.status`, `users.is_active`,
  `notifications.read_at`, `announcements.is_published/created_at`,
  `parent_student.student_id`, `is_active` flags, ...). Foreign-key
  columns were already indexed implicitly by InnoDB.
- Dashboard: the ~15 separate `COUNT` queries per load are now **one
  aggregate query** for the student cards and **one** for today's
  attendance (SUM(CASE ...)); users-by-role is one `GROUP BY` on the
  spatie pivot instead of loading every user row.
- `Schema::hasTable()/hasColumn()` was being called dozens of times per
  page (each is an `information_schema` round-trip on MySQL). They now go
  through `App\Support\SchemaInfo`, which memoizes them per request.
- The topbar (rendered on every page) no longer runs a schema check per
  request; the settings row is memoized per request on top of its cache
  entry (`Setting::current()`).

**Routes / framework**

- The three route *closures* (`/storage/{path}`, `/auth/token/{nonce}`,
  `/migrate-once-9f3k2`) were moved into invokable controllers, because
  closures make `php artisan route:cache` fail. This unblocks the whole
  `php artisan optimize` step below.

**Frontend assets**

- Chart.js (~200 KB) was bundled into the global `app.js` and downloaded
  on **every** page. It is now a separate dashboard-only entry
  (`resources/js/dashboard.js`) that lazy-loads Chart.js via dynamic
  import - only the dashboard pays for it.
- axios was imported on every page but never used - removed.
- `public/.htaccess` now gzip/deflates text responses and sets 1-year
  cache headers on the content-hashed build assets (all wrapped in
  `IfModule`, so restricted hosts just skip them).
- `public/build/` is committed, so `npm run build` is **not** needed on
  the laptop - the compiled assets are already there.

## 3. One-time setup on each laptop (run once, then it's fast)

```bat
:: Windows / XAMPP - from the project root:
optimize.bat
```

```bash
# Linux/macOS:
./optimize.sh
```

Both run `php artisan migrate` (applies the new indexes) and
`php artisan optimize` (caches config, routes, views and events into
plain PHP files - no more parsing on every request).

For deployment (not while developing), also set in `.env`:

```env
APP_ENV=production
APP_DEBUG=false
```

Then re-run `optimize.bat` / `optimize.sh` (cached config is built from
`.env`, so it must be rebuilt after changing it). To undo:
`php artisan optimize:clear`.

## 4. PHP settings for speed (XAMPP `php.ini`)

Enable OPcache - this is the single biggest PHP-level win (compiled
scripts are kept in shared memory instead of being re-parsed per
request). In XAMPP's `php.ini`:

```ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=128
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=10000
opcache.validate_timestamps=0   ; production only - set 1 while developing
opcache.revalidate_freq=0
realpath_cache_size=4096k
realpath_cache_ttl=600
```

Restart Apache after editing. (On shared hosting you can't edit php.ini;
OPcache is usually already on there.)

Optional, in XAMPP's `my.ini` for MySQL/MariaDB:

```ini
innodb_buffer_pool_size=256M
```

## 5. Rebuilding frontend assets (only when you change JS/CSS)

```bash
npm ci
npm run build
```

The output in `public/build/` is committed, so end users never need
Node.js.
