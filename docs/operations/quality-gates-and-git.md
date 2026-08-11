# Quality gates, Git, and commits

Canonical commands and repository workflow for local quality gates, hooks, commit structure, and agent-produced changes.

## Quality Commands

Provide project-level commands.

`composer check:foundation` is the public aggregate foundation gate. It runs `composer check` (including fresh test-database preparation, migration/schema, seeder, bilingual mail, architecture, audit, frontend, and static guardrails), then the full isolated Playwright suite, and finally the isolated production runtime smoke. This order keeps stateful PHPUnit and Playwright sequential. The runtime lane builds production images and covers Compose validation, fresh migration, HTTP/assets, Horizon and every configured queue, scheduler heartbeat, ClamAV/EICAR, Meilisearch, Chromium/PDF, clean teardown, and restart with persisted PostgreSQL data.

### Composer

- `composer format`
- `composer lint`
- `composer analyse`
- `composer test`
- `composer test:unit`
- `composer test:integration`
- `composer test:feature`
- `composer runtime:check`
- `composer runtime:smoke`
- `composer check:foundation`
- `composer check`

`composer format` formats backend and frontend.

`composer runtime:check` validates pinned package-manager versions, production Docker COPY and ignore boundaries, environment/search-path contracts, external secret registration, Compose structure, PostgreSQL 18 storage, internal HTTP exposure, and nginx asset behavior. `composer runtime:smoke` additionally builds the production PHP, nginx, and preliminary backup images; checks their contents and runtime user; migrates an isolated Compose stack; exercises HTTP and a built asset; and proves PostgreSQL data survives container recreation. It uses an isolated project name, temporary owner-only secret files, and removes its containers and volumes on exit.

`composer check` runs full verification and must not silently modify code. It includes Pint check mode, Prettier check mode, ESLint, Stylelint, secret checks, unwanted-file checks, configuration and runtime-contract guardrails, PHPStan/Larastan, PHPUnit, TypeScript typechecking, Vitest, and the production Vite build. The heavier image-building `composer runtime:smoke` remains an explicit runtime gate until the aggregate Phase 28 foundation command owns it.

At the frontend foundation checkpoint, `composer lint` also runs `pnpm lint` and `pnpm stylelint`, while `composer check` delegates frontend verification to `pnpm check`.

`composer analyse` runs PHPStan/Larastan at the configured maximum practical level through `tools/quality/run-phpstan.sh`. The script discovers targets deterministically from `phpstan.neon`, expands modules automatically, includes global `app` directories such as `Http` and `Providers`, verifies that every configured PHP file is covered by the public command, and submits the deterministic target set in one PHPStan invocation. A single invocation avoids cumulative worker lifecycle faults between sequential module processes while preserving the same analysed paths from `phpstan.neon`; set `PHPSTAN_DISABLE_PARALLEL=1` for one-process debug execution when diagnosing an environment-specific worker failure.

The public PHPStan script disables PHPStan parallel workers by default because the current PHPStan/Larastan/PHP runtime combination can intermittently terminate a child worker with exit code 139 during otherwise clean analysis. Keep the public target discovery and coverage verification intact; do not work around this by skipping PHPStan or bypassing Git hooks.

### pnpm

- `pnpm format`
- `pnpm format:check`
- `pnpm lint`
- `pnpm test`
- `pnpm test:e2e`
- `pnpm build`
- `pnpm check:secrets`
- `pnpm check:unwanted`
- `pnpm check`

`pnpm check` runs TypeScript checking, ESLint, Stylelint, Vitest, and the production Vite build.

`pnpm check:config` runs repository configuration guardrails. The current checks reject duplicate active keys in `.env.example`, fail when the Playwright package versions drift from the Dev Container `PLAYWRIGHT_VERSION` browser-install argument, and execute the production runtime contract check.

PHPUnit uses separate `Unit`, `Integration`, and `Feature` test suites. `Integration` is reserved for persistence, Redis, queues, cache, search, filesystem adapters, module providers, transaction boundaries, and other infrastructure behavior. `Feature` is reserved for HTTP, middleware, validation and authorization boundaries, Inertia responses, and protected backend workflows.

Stateful PHPUnit tests use the dedicated `atlas_testing` PostgreSQL database, Redis DB `2`, Redis cache DB `3`, and the `atlas_testing_cache` prefix. `composer test`, `composer test:integration`, and `composer test:feature` prepare the PHPUnit database through `tools/testing/ensure-test-databases.sh`.

`pnpm test:e2e` runs Playwright against isolated local servers on `127.0.0.1:8010` for Laravel and `127.0.0.1:5174` for Vite. The Playwright setup uses the dedicated `atlas_e2e` PostgreSQL database, Redis DB `4`, Redis cache DB `5`, and the `atlas_e2e_cache` prefix. It clears Laravel config, runs `migrate:fresh --force`, clears cache-backed test state, and seeds `E2eVisibilitySeeder`, which first runs the production-safe `DatabaseSeeder`, so authenticated shell checks can log in through the real login form without depending on local development demo data. The e2e Laravel server uses PHP's built-in server directly instead of `php artisan serve` so the full e2e environment reaches the request process. The e2e environment raises the auth login rate-limit threshold only for the isolated browser suite; production rate limits and feature-level rate-limit assertions must not be weakened.

The durable testing environment strategy lives in [Testing environment](testing-environment.md).

Do not run `pnpm test:e2e` in parallel with `composer test` or `composer check` against the same local database. PHPUnit and Playwright both prepare application state, so run those gates sequentially unless a derived CI setup gives each job an isolated database.

E2E tests must import `test` and `expect` from `tests/e2e/support/test`. The shared fixture fails tests on browser `pageerror`, `console.error`, failed monitored asset/API requests, and HTTP 4xx/5xx responses for documents, scripts, stylesheets, fonts, images, fetch, and XHR resources.

Configure VS Code format-on-save for backend and frontend.

The browser support baseline is current stable Chrome, Edge, and Firefox, recorded through the project `browserslist` entry.

---

## Git and Hooks

Use Lefthook from the beginning.

### Pre-commit

Fast changed/staged checks:

- Pint;
- Prettier;
- ESLint;
- Stylelint;
- secret detection;
- unwanted-file detection.

The pre-commit hook runs public commands only:

- `composer lint`;
- `pnpm format:check`;
- `pnpm check:secrets`;
- `pnpm check:unwanted`.

### Pre-push

- PHPStan/Larastan at maximum practical level;
- PHPUnit;
- Vitest;
- production frontend build.
- TypeScript typechecking.

The pre-push hook runs public commands only:

- `composer analyse`;
- `composer test`;
- `pnpm check`.

Playwright runs before deployment.

Run `pnpm exec lefthook install` after dependency installation if Git hooks were not synchronized automatically.

Atlas intentionally starts without bundled CI workflows.

Required quality enforcement is local through public project commands and Lefthook.

Do not add GitHub Actions, GitLab CI, or another CI provider unless the user explicitly requests it for a concrete derived project.

Project commands must remain CI-ready so a future project owner may add CI without redesigning the quality workflow.

Hooks call the same public project commands. Do not hide duplicate logic inside hooks.

### Commits

The agent does not create commits automatically. A commit is created only after explicit user approval for the exact reviewed change.

Use English Conventional Commits:

- `feat:`
- `fix:`
- `chore:`
- `docs:`
- `refactor:`
- `test:`
- `build:`
- `ci:`

Create the smallest logical commits.

Before every commit:

- inspect the diff;
- remove accidental changes;
- verify naming;
- verify module boundaries;
- verify security;
- verify performance;
- verify tests;
- verify UI translations;
- verify docs;
- verify duplication;
- verify no debug or dead code remains.

Refactoring must be separate from feature work.

Write or update tests before significant refactoring.

Do not create speculative abstractions before a pattern is stable.

Large later refactoring indicates an earlier design failure. Design correctly from the beginning so later refactoring is mainly small and cosmetic.

---
