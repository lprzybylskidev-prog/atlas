# Seeding and demo data

Canonical operational rules for mandatory technical seeders and development-only demo data.

## Phase 28 closure

`P28-SEED-001` is complete. Seeder classes are orchestration only: they use public Application contracts or dedicated owner fixture builders and never use Eloquent models, `forceFill`, query builders, raw SQL, or module persistence classes. A permanent architecture test enforces that boundary.

Fixture builders are registered only in `local`, `development`, and `testing`; resolving them in production is unsupported. Their direct owner-table writes are limited to deterministic records that cannot be represented as normal runtime operations (historical TimeTracking review data and completed ManagedProcesses/Imports browser fixtures). Those writes stay inside the owning module and are covered by repeated-seed, aggregate-consistency, and invariant tests.

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

`Database\Seeders\DevelopmentDemoSeeder` delegates the Phase 27 development-only scenario to TimeTracking's owner fixture builder. Accounts are created by Identity's verified-account builder; memberships, head-manager flags and the DAG use Teams contracts; exact scoped permissions and provenance use Authorization; activation uses the shared activation service; and user policies use Teams/TimeTracking contracts. Only deterministic historical TimeTracking aggregate rows are written by the TimeTracking owner builder. Repeated runs preserve account public IDs, authorization provenance, hierarchy shape, audit counts, and aggregate counts while rebuilding the same review timeline.

The scenario creates `TT Demo Team North` and `TT Demo Team South`, 2 `TT Head Manager ...` accounts, 3 `TT Manager ...` accounts, 50 `TT User ...` accounts, and `TT One Minute Policy Test User - North` with the local demo password `password`; activates TimeTracking for both teams; grants scoped panel/report/activity/lock/notification permissions; creates head-manager and direct-manager hierarchy scopes; enables tracking; gives the one-minute policy test user a 1-minute inactivity policy and 1-minute regular-break policy while leaving maximum session lifetime inherited; and seeds representative categories, work, breaks, maintenance, Other work, and corrections. The seeder skips production and no-ops when TimeTracking tables have not been migrated yet.

`Database\Seeders\DatabaseSeeder` is production-safe, installs starter roles and registered permissions, creates the mandatory `Administration` team, and synchronizes Administration module access. It must not create demo accounts or module demo records.

`Database\Seeders\DevelopmentBootstrapSeeder` creates the local preview administrator for local/development review and assigns it normal administrator access in the `Administration` team.

`Database\Seeders\E2eVisibilitySeeder` is also non-production. It uses the same verified-account, membership, authorization, hierarchy, activation, and tracking contracts. Its completed process/import read model is split between dedicated ManagedProcesses and Imports fixture builders, uses fixed timestamps and idempotency keys, and remains stable across repeated runs. It intentionally creates no uploaded Files objects or user notifications.

## Demo reset

Atlas provides one explicit command to recreate or reset the complete local/development demo environment:

```bash
composer demo:reset
```

The command runs `php artisan demo:reset`, clears cached application state and sessions, recreates the database schema, runs production-safe technical seeders, runs the development bootstrap seeder, runs development-only demo seeders, and clears cached/session state again so stale browser sessions do not retain old active-team data.

For PostgreSQL module schemas, the command first drops Atlas-owned schemas listed by `DatabaseSchema::all()` with `cascade`, then runs `migrate:fresh`. The repository-owned `db:wipe` extension applies the same exact schema registry for every fresh migration. This keeps local demo resets reliable when a previous interrupted migration left tables in a module-owned schema, without broadening PostgreSQL `search_path` beyond `public`.

The demo reset command must refuse to run outside approved local or development environments.

Production deployment commands must never invoke demo seeders.

Automated tests use factories and explicit fixtures. Permission-gated and module-gated e2e scenarios must use explicit deterministic test fixtures rather than generic demo data.

## Current development bootstrap account

The development demo reset runs production-safe technical seeders first, then creates one local administrator account in the required `Administration` team so the application can be reviewed through the real Fortify login flow:

- email: `admin@example.test`;
- password: `password`.

This account is for local development review only. It must not be reused as a production bootstrap credential.
