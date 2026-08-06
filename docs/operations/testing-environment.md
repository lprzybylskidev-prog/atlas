# Testing environment

Canonical testing environment strategy for backend tests, frontend tests, browser tests, local development, and future CI.

## Test Layers

Atlas uses separate test layers with different responsibilities.

- PHPUnit `Unit` tests cover pure PHP logic, value objects, structure rules, and static contracts that do not need Laravel state.
- PHPUnit `Integration` tests cover persistence, Redis, queues, cache, search, filesystem adapters, module providers, transaction boundaries, and other infrastructure behavior.
- PHPUnit `Feature` tests cover HTTP, middleware, validation and authorization boundaries, Inertia responses, and protected backend workflows.
- Vitest covers frontend TypeScript logic, composables, registries, formatters, typed UI services, and focused component behavior where browser automation would be too expensive.
- Playwright covers real browser workflows, rendered shells, accessibility-sensitive interactions, light and dark themes, browser-console cleanliness, permission-gated visibility, module-gated visibility, and critical user journeys.

Do not use a higher test layer when a lower layer can prove the behavior clearly.

## Stateful Backend Isolation

Atlas is PostgreSQL-first and Redis-backed. Stateful automated tests use PostgreSQL and Redis rather than SQLite.

The local development/demo state uses:

- database: `atlas`;
- Redis default DB: `0`;
- Redis cache DB: `1`;
- cache prefix: `atlas_cache`;
- normal development ports: Laravel `8000`, Vite `5173`.

PHPUnit stateful tests use:

- database: `atlas_testing`;
- Redis default DB: `2`;
- Redis cache DB: `3`;
- cache prefix: `atlas_testing_cache`;
- environment: `testing`.

Playwright e2e tests use:

- database: `atlas_e2e`;
- Redis default DB: `4`;
- Redis cache DB: `5`;
- cache prefix: `atlas_e2e_cache`;
- environment: `testing`;
- auth login rate-limit max attempts: high e2e-only override in `playwright.config.ts`, so repeated browser login workflows do not block the suite;
- isolated application port: `8010`;
- isolated Vite port: `5174`.

PHPUnit, feature tests, and Playwright e2e tests must not mutate the same database or Redis state in parallel. Until every parallel lane has its own database and Redis logical databases, stateful gates run sequentially.

## Environment Conventions

The committed PHPUnit contract lives in `phpunit.xml`. The repository intentionally does not track `.env.testing` because Atlas blocks committed `.env.*` files. A developer may create a local `.env.testing` only as an override matching the values in `phpunit.xml`.

Playwright e2e environment values live in `playwright.config.ts`. The Playwright web server command prepares `atlas_e2e`, clears Laravel config, runs `migrate:fresh --force`, clears cache-backed state, seeds the deterministic e2e fixture set, and starts isolated local servers. The Laravel e2e server uses PHP's built-in server with Laravel's router file so the configured e2e environment is inherited by the request process. Playwright must not depend on an already-running development Laravel server or Vite server. The e2e-only auth login rate-limit override exists because full browser suites intentionally perform many successful login workflows; production and feature-test rate-limit contracts must stay covered without weakening production configuration.

`tools/testing/ensure-test-databases.sh` creates the local PostgreSQL databases required by PHPUnit and Playwright. Public Composer and pnpm commands call this setup where they need stateful test databases.

For PHPUnit lanes, the same preparation script drops known Atlas-owned PostgreSQL schemas before migrations run. This keeps schema-qualified module tables isolated even when a previous interrupted test run left a non-`public` schema behind.

## Deterministic Fixtures

Automated tests use factories and explicit deterministic fixtures.

Test seeders must:

- be idempotent;
- create stable technical users, teams, permissions, active-team state, module states, and UI visibility fixtures needed by the tested workflow;
- avoid random credentials, random emails, or time-dependent data unless the test explicitly controls the clock;
- belong to the module or shared testing support that owns the tested behavior;
- stay separate from production-safe technical seeders and development-only demo seeders.

`Database\Seeders\DatabaseSeeder` remains production-safe, installs starter roles and registered permissions, creates mandatory system bootstrap records such as the `Administration` team, synchronizes Administration module access, and does not create demo or e2e-only accounts.

`Database\Seeders\DevelopmentBootstrapSeeder` may be used by local preview only to create the local administrator account. `Database\Seeders\DevelopmentDemoSeeder` owns development-only module demo data accepted by active phases, currently the TimeTracking demo scenario, and must stay separate from production-safe technical seeders. Permission-gated and module-gated Playwright scenarios use explicit e2e fixtures rather than the generic development account.

`Database\Seeders\E2eVisibilitySeeder` is the deterministic fixture set for current Admin visibility coverage. It runs the production-safe technical seeders, then creates stable administrator and limited-user accounts, an active team, module states, and the exact records needed by the browser scenarios.

## Browser Coverage

Playwright tests must import `test` and `expect` from `tests/e2e/support/test`.

The shared fixture fails browser tests on:

- runtime `pageerror`;
- `console.error`;
- failed monitored document, script, stylesheet, font, image, fetch, or XHR requests;
- unexpected HTTP 4xx/5xx responses for monitored resources.

Permission-gated and module-gated UI behavior needs Playwright coverage when manual visibility checks would be error-prone. UI visibility tests do not replace backend authorization tests.

## Future CI

Atlas does not bundle a CI provider. A derived project may add CI later by calling the public Composer and pnpm commands.

Future CI jobs may split PHPUnit, Vitest, build, and Playwright only when each stateful job receives isolated PostgreSQL and Redis state. Sharing `atlas_testing`, `atlas_e2e`, or Redis DBs between parallel jobs is forbidden.
