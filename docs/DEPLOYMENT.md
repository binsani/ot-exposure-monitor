# Production deployment

OTEIM is designed for a standard Ubuntu VPS or Laravel Forge host with PHP 8.3+, PostgreSQL 16+, Redis, Nginx, and a TLS certificate. The web root must be the repository's `public` directory.

## Required services

- PHP-FPM with cURL, OpenSSL, PDO PostgreSQL, Redis, Mbstring, XML, and Fileinfo
- PostgreSQL with a dedicated database and least-privilege application user
- Redis for queues and cache
- Nginx or another reverse proxy terminating HTTPS
- A transactional mail provider
- One long-running Laravel queue worker
- One scheduler invocation every minute

## First deployment

1. Clone the stable `main` branch into a dedicated service directory.
2. Run `composer install --no-dev --classmap-authoritative` and `npm ci && npm run build` in CI or on the build host.
3. Create `.env` from `.env.example`. Never copy a developer `.env` or agent token.
4. Set `APP_ENV=production`, `APP_DEBUG=false`, an HTTPS `APP_URL`, a unique `APP_KEY`, `ALLOW_PUBLIC_REGISTRATION=false`, `SESSION_ENCRYPT=true`, and `SESSION_SECURE_COOKIE=true`.
5. Configure PostgreSQL, Redis, production email, Shodan, and the queue connection.
6. Run `php artisan migrate --force`, then `php artisan optimize`.
7. Give the web/worker service account write access only to `storage` and `bootstrap/cache`.
8. Start the queue worker and enable the scheduler.
9. Register the first organization while registration is temporarily enabled, then immediately disable registration and run `php artisan config:cache`.

## Queue worker

Use Supervisor or systemd to run:

```text
php artisan queue:work redis --sleep=3 --tries=3 --backoff=30 --max-time=3600
```

Restart workers after each release with `php artisan queue:restart`. Administrators can inspect and retry failed jobs from **Operations** in the application.

## Scheduler

Run this every minute from cron under the application service account:

```text
* * * * * cd /srv/oteim && php artisan schedule:run >> /dev/null 2>&1
```

## Zero-downtime release sequence

Enable maintenance mode only if the migration is not backward compatible. Install dependencies, build assets, run migrations, optimize configuration/routes/views, restart queue workers, and verify `/up`, login, dashboard, a queued mail, and the schedule list. Keep the preceding release directory and database backup until the smoke test passes.

## Deployment blocker

This repository cannot select or access a production host by itself. A target host/domain, DNS control, database/Redis credentials, and mail credentials are required before the first live deployment.
