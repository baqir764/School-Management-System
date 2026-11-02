# Installation — Phase 1

These steps assume XAMPP on Windows with the project already sitting at
`C:\xampp\htdocs\School-Management-System`.

## 1. Prerequisites

- XAMPP with PHP 8.2 or newer and MySQL running (start both **Apache** and
  **MySQL** from the XAMPP control panel)
- [Composer](https://getcomposer.org/download/) installed and on your PATH
- [Node.js](https://nodejs.org/) 18+ and npm (for building the CSS/JS)

Check versions from a terminal opened in the project folder:

```bash
php -v
composer -V
node -v
npm -v
```

## 2. Install PHP dependencies

```bash
composer install
```

This downloads Laravel and every package listed in `composer.json`
(currently `spatie/laravel-permission` for roles/permissions — later
phases will add PDF and Excel export packages when report cards and
import/export are built) into a new `vendor/` folder.

## 3. Configure the environment

```bash
copy .env.example .env
php artisan key:generate
```

Open `.env` and confirm the database block matches your XAMPP MySQL setup
(these are already the XAMPP defaults):

```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=school_management_system
DB_USERNAME=root
DB_PASSWORD=
```

## 4. Create the database

Open [phpMyAdmin](http://localhost/phpmyadmin) and create a new, empty
database named `school_management_system` (utf8mb4_unicode_ci collation).
Do **not** import any SQL file — the Laravel migrations below create every
table.

## 5. Run migrations and seed demo data

```bash
php artisan migrate --seed
php artisan storage:link
```

`storage:link` makes uploaded files (the school logo, later student
photos/documents) reachable through the web server.

## 6. Build the frontend assets

```bash
npm install
npm run build
```

For active day-to-day development, `npm run dev` instead starts Vite in
watch mode with hot reload.

## 7. Open the application

Because the project lives directly under `htdocs`, Laravel's front
controller is at `public/index.php`, so browse to:

```
http://localhost/School-Management-System/public
```

(For clean URLs without `/public` in the address, point an Apache virtual
host's `DocumentRoot` at the `public` folder instead — optional for local
development. If you do this, also update `APP_URL` in `.env` to drop the
trailing `/public`, then re-run `php artisan config:clear` and
`npm run build`, since the school logo and other uploaded files are linked
using that URL.)

## 8. Log in

All demo accounts share the password `Passw0rd!123` and are meant for
development/demo only — **change or delete them before any real use**:

| Role | Email |
|---|---|
| Super Administrator | admin@school.local |
| School Administrator | school.admin@school.local |
| Principal | principal@school.local |
| Teacher | teacher@school.local |
| Accountant | accountant@school.local |
| Librarian | librarian@school.local |
| Storekeeper | storekeeper@school.local |
| Receptionist | receptionist@school.local |
| Parent | parent@school.local |
| Student | student@school.local |

Only **Super Administrator** and **School Administrator** currently see the
Users and School Settings menu items — the other roles only have dashboard
access until their modules are built in later phases.

## 9. Running the test suite (optional but recommended)

```bash
php artisan test
```

Tests run against an in-memory SQLite database (configured in
`phpunit.xml`), so they never touch your real MySQL data.

## Troubleshooting

- **White/blank page or 500 error**: run `php artisan config:clear` and
  check `storage/logs/laravel.log` for the real error.
- **"could not find driver" on migrate**: enable the `pdo_mysql` and
  `mysqli` extensions in `php.ini` (XAMPP's `php.ini` usually has these
  commented out with a leading `;` — remove it and restart Apache).
- **Permission/500 errors around `storage/` or `bootstrap/cache/`**: make
  sure both folders are writable by the web server user.
- **Styles/JS not loading**: you skipped step 6, or ran `npm run dev`
  and then closed it — run `npm run build` for a production build that
  doesn't need a running process.
- **`LazyLoadingViolationException` in the browser**: `AppServiceProvider`
  turns on Laravel's strict model mode outside production
  (`Model::shouldBeStrict()`) so accidental N+1 queries fail loudly during
  development instead of hiding silently. If a future custom change trips
  it somewhere unexpected, either eager-load the relation that triggered it
  or, as a temporary unblock, comment out the `Model::shouldBeStrict(...)`
  line in `app/Providers/AppServiceProvider.php`.
