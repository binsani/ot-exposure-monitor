# OT Exposure & Integrity Monitor

OT Exposure & Integrity Monitor helps water and wastewater utilities know when an operational-technology asset becomes reachable from the internet or changes without authorization.

The product brings four workflows into one tenant-safe application:

- asset inventory and vulnerability matching;
- internet-exposure monitoring with a complete scan history;
- device baseline and integrity-change alerting; and
- incident playbooks, manual fallback readiness, and incident records.

## Foundation

The application uses Laravel 13, PHP 8.3, PostgreSQL, Vue 3, Inertia.js, Tailwind CSS, Redis-backed queues, Pest, and Vitest. Organization membership and current-organization selection are separate so a user can safely belong to multiple organizations. Tenant-owned records carry an organization key and are protected by a query-level global scope.

## Local setup

1. Install PHP 8.3+, Composer, Node.js, PostgreSQL, and Redis.
2. Copy `.env.example` to `.env` and set local database values.
3. Run `composer install`, `npm install`, `php artisan key:generate`, and `php artisan migrate`.
4. Run `composer dev` for local development.

No external service credentials are committed. Shodan, Censys, advisory-feed, mail, and Redis settings are supplied through environment variables.

The complete product specification is in [`docs/PRODUCT_SPEC.md`](docs/PRODUCT_SPEC.md).

Production guidance is in [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md), the operating runbook is in [`docs/OPERATIONS.md`](docs/OPERATIONS.md), and implemented security controls and release checks are in [`docs/SECURITY.md`](docs/SECURITY.md). The optional on-premise collector is documented in [`agent/README.md`](agent/README.md).
