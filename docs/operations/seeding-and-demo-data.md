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

Development demo data must grow with implemented product capabilities. Each completed workflow that needs realistic manual review should add representative demo records, including negative or boundary cases where helpful. Demo seeders must not flatten the authorization model by giving every demo user every team, role, permission, module, or workflow state unless the scenario is intentional and clearly named.

Demo seeders may create example:

- teams;
- users;
- manager relationships;
- notifications;
- TimeTracking scenarios;
- imports and process statuses;
- later neutral or debt-collection demonstration scenarios.

Demo users use reserved non-production addresses such as `example.test`.

Known demo credentials are permitted only in local or development environments, must be documented clearly, and must never be reusable in production.

Module-specific demo seeders are created in the owning module phase, after that module's real tables, contracts, and invariants exist.

The current foundation-level development demo seeder is `Database\Seeders\DevelopmentDemoSeeder`.

`Database\Seeders\DatabaseSeeder` is production-safe and must not create demo accounts or module demo records.

## Demo reset

Atlas provides one explicit command to recreate or reset the complete local/development demo environment:

```bash
composer demo:reset
```

The command runs `php artisan demo:reset`, clears cached application state and sessions, recreates the database schema, runs production-safe technical seeders, runs development-only demo seeders, and clears cached/session state again so stale browser sessions do not retain old active-team data.

The demo reset command must refuse to run outside approved local or development environments.

Production deployment commands must never invoke demo seeders.

Automated tests use factories and explicit fixtures. The current Playwright shell smoke tests may use `DevelopmentDemoSeeder` only until dedicated identity, authorization, team, and module visibility fixtures exist. Permission-gated and module-gated e2e scenarios must use explicit deterministic test fixtures rather than generic demo data.

## Current development demo account

The development demo reset creates one local administrator account plus three teams, distinct team-scoped admin-managed presets, deterministic copy-source users, a multi-team user, and faker-generated users with preset assignments so the frontend shell and authorization screens can be reviewed through the real Fortify login flow:

The team-scoped demo presets must use distinct small functional role bundles and direct permissions per team. They must not recreate generic `user`/`manager` personae or make every team look authorized the same way.

- email: `admin@example.test`;
- password: `password`.

This account is for local development review only. It must not be reused as a production bootstrap credential.
