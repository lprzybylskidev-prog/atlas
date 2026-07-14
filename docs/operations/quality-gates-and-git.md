# Quality gates, Git, and commits

Canonical commands and repository workflow for local quality gates, hooks, commit structure, and agent-produced changes.

## Quality Commands

Provide project-level commands.

### Composer

- `composer format`
- `composer lint`
- `composer analyse`
- `composer test`
- `composer test:unit`
- `composer test:integration`
- `composer test:feature`
- `composer check`

`composer format` formats backend and frontend.

`composer check` runs full verification and must not silently modify code.

At the frontend foundation checkpoint, `composer lint` also runs `pnpm lint` and `pnpm stylelint`, while `composer check` delegates frontend verification to `pnpm check`.

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

`pnpm test:e2e` runs Playwright against isolated local servers on `127.0.0.1:8010` for Laravel and `127.0.0.1:5174` for Vite. The Playwright setup migrates the configured local database, clears cache-backed test state, and seeds `DevelopmentDemoSeeder` so authenticated shell checks can log in through the real login form.

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

The pre-push hook runs public commands only:

- `composer analyse`;
- `composer test`;
- `pnpm test`;
- `pnpm build`.

Playwright runs before deployment.

Run `pnpm exec lefthook install` after dependency installation if Git hooks were not synchronized automatically.

Atlas intentionally starts without bundled CI workflows.

Required quality enforcement is local through public project commands and Lefthook.

Do not add GitHub Actions, GitLab CI, or another CI provider unless the user explicitly requests it for a concrete derived project.

Project commands must remain CI-ready so a future project owner may add CI without redesigning the quality workflow.

Hooks call the same public project commands. Do not hide duplicate logic inside hooks.

### Commits

The agent creates commits.

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
