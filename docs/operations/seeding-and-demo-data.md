# Seeding and demo data

Canonical operational rules for mandatory technical seeders and development-only demo data.

## Technical seeders

Technical seeders create only system records required for Atlas to operate safely.

Allowed technical seed data includes:

- starter roles;
- registered permission catalogs;
- required bootstrap reference records;
- module-owned reference values explicitly required by accepted contracts.

Technical seeders must be safe for production and idempotent.

## Demo seeders

Demo seeders are development-only tooling.

Development demo seeders are intentionally minimal by default. Mandatory authorization/team bootstrap records are created by normal seeders, and the local preview administrator is created by a dedicated development bootstrap seeder. Demo seeders remain reserved for representative business demo data when a later phase explicitly accepts that scope.

Demo seeders must not create artificial Admin panel volume such as extra users, manager relationships, notifications, uploaded files, import executions, process runs, process logs, schedules, or module activation states unless that exact scenario is explicitly requested and documented.

Demo users use reserved non-production addresses such as `example.test`.

Known demo credentials are permitted only in local or development environments, must be documented clearly, and must never be reusable in production.

Module-specific demo seeders are created in the owning module phase, after that module's real tables, contracts, and invariants exist.

The current foundation-level development demo seeder is `Database\Seeders\DevelopmentDemoSeeder`.

`Database\Seeders\DatabaseSeeder` is production-safe, installs starter roles and registered permissions, creates the mandatory `Administration` team, and synchronizes Administration module access. It must not create demo accounts or module demo records.

`Database\Seeders\DevelopmentBootstrapSeeder` creates the local preview administrator for local/development review and assigns it normal administrator access in the `Administration` team.

## Demo reset

Atlas provides one explicit command to recreate or reset the complete local/development demo environment:

```bash
composer demo:reset
```

The command runs `php artisan demo:reset`, clears cached application state and sessions, recreates the database schema, runs production-safe technical seeders, runs the development bootstrap seeder, runs development-only demo seeders, and clears cached/session state again so stale browser sessions do not retain old active-team data.

The demo reset command must refuse to run outside approved local or development environments.

Production deployment commands must never invoke demo seeders.

Automated tests use factories and explicit fixtures. Permission-gated and module-gated e2e scenarios must use explicit deterministic test fixtures rather than generic demo data.

## Current development bootstrap account

The development demo reset runs production-safe technical seeders first, then creates one local administrator account in the required `Administration` team so the application can be reviewed through the real Fortify login flow without artificial Admin panel records:

- email: `admin@example.test`;
- password: `password`.

This account is for local development review only. It must not be reused as a production bootstrap credential.
