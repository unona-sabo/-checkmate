# CheckMate

Laravel 12 + Inertia.js (Vue 3) application for managing checklists, test suites, test runs, bug reports, documentation, and notes.

## Stack

- PHP 8.4, Laravel 12
- Inertia.js v2 + Vue 3, Tailwind CSS v4
- SQLite (default), database-backed queue and cache
- Vite for frontend builds, Wayfinder for typed route generation

## Deployment

### Requirements

- PHP 8.2+ with the extensions Laravel 12 requires (`pdo_sqlite` or your chosen DB driver, `mbstring`, `fileinfo`, `openssl`, etc.)
- Composer 2
- Node.js 18+ and npm
- A process manager (systemd, Supervisor, etc.) for the queue worker

### 1. Get the code

```bash
git clone <repository-url> checkmate
cd checkmate
```

### 2. Install dependencies

```bash
composer install --no-dev --optimize-autoloader
npm ci
```

### 3. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` for the target environment:

- `APP_ENV=production`, `APP_DEBUG=false`
- `APP_URL` set to the real domain
- `DB_CONNECTION` and credentials (defaults to SQLite — if kept, ensure `database/database.sqlite` exists: `touch database/database.sqlite`)
- `SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION` (default to `database`)
- Mail, filesystem, and any third-party service credentials

### 4. Build the frontend

```bash
npm run build
```

This runs `vite build`, which also runs the Wayfinder plugin to regenerate `resources/js/actions` and `resources/js/routes`.

### 5. Run database migrations

```bash
php artisan migrate --force
```

> **Never run `migrate:fresh` or `migrate:refresh` against a production database** — both drop all existing tables and data. Use `migrate` for forward-only, additive migrations.

### 6. Cache configuration for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Re-run these after every deploy that changes config, routes, or views. Clear with the `:clear` equivalents if you need to debug production behavior.

### 7. Start the queue worker

The app uses the database queue driver, so a persistent worker is required for queued jobs:

```bash
php artisan queue:work --tries=3
```

Run this under a process supervisor (e.g., Supervisor or systemd) so it restarts automatically on failure or deploy.

### 8. Serve the application

Point your web server (Nginx/Apache/Herd) at the `public/` directory, or serve directly for simple setups:

```bash
php artisan serve --host=0.0.0.0 --port=8000
```

### Deploy checklist summary

1. Pull latest code
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan migrate --force`
5. `php artisan config:cache route:cache view:cache event:cache`
6. Restart queue workers
7. Restart PHP-FPM / web server if needed

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
composer run dev
```

`composer run dev` starts the PHP dev server, queue listener, and Vite dev server together.
