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

`Database\Seeders\DevelopmentDemoSeeder` currently seeds the Phase 27 development-only TimeTracking scenario. It creates `TT Demo Team North` and `TT Demo Team South`, 2 `TT Head Manager ...` accounts, 3 `TT Manager ...` accounts, 50 `TT User ...` accounts, and `TT One Minute Policy Test User - North` with the local demo password `password`; activates TimeTracking for both teams; grants scoped panel/report/activity/lock/notification permissions; creates head-manager and direct-manager hierarchy scopes; enables tracking; gives the one-minute policy test user a 1-minute inactivity policy and 1-minute regular-break policy while leaving maximum session lifetime inherited; seeds team-scoped work-outside-the-computer categories; and seeds representative official work, regular break, maintenance/technical break, work outside the computer, and correction records for report review. Each TimeTracking demo team includes at least one correction linked to a work session, one linked to a break, and one linked to work outside the computer. The seeder skips production and no-ops when TimeTracking tables have not been migrated yet.

`Database\Seeders\DatabaseSeeder` is production-safe, installs starter roles and registered permissions, creates the mandatory `Administration` team, and synchronizes Administration module access. It must not create demo accounts or module demo records.

`Database\Seeders\DevelopmentBootstrapSeeder` creates the local preview administrator for local/development review and assigns it normal administrator access in the `Administration` team.

## Demo reset

Atlas provides one explicit command to recreate or reset the complete local/development demo environment:

```bash
composer demo:reset
```

The command runs `php artisan demo:reset`, clears cached application state and sessions, recreates the database schema, runs production-safe technical seeders, runs the development bootstrap seeder, runs development-only demo seeders, and clears cached/session state again so stale browser sessions do not retain old active-team data.

For PostgreSQL module schemas, the command first drops Atlas-owned schemas listed by `DatabaseSchema::all()` with `cascade`, then runs `migrate:fresh`. This keeps local demo resets reliable when a previous interrupted migration left tables in module-owned schemas such as `optional_time_tracking`.

The demo reset command must refuse to run outside approved local or development environments.

Production deployment commands must never invoke demo seeders.

Automated tests use factories and explicit fixtures. Permission-gated and module-gated e2e scenarios must use explicit deterministic test fixtures rather than generic demo data.

## Current development bootstrap account

The development demo reset runs production-safe technical seeders first, then creates one local administrator account in the required `Administration` team so the application can be reviewed through the real Fortify login flow:

- email: `admin@example.test`;
- password: `password`.

This account is for local development review only. It must not be reused as a production bootstrap credential.
