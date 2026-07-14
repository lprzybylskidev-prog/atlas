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
- `pnpm lint`
- `pnpm test`
- `pnpm test:e2e`
- `pnpm build`
- `pnpm check`

`pnpm check` runs TypeScript checking, ESLint, Stylelint, Vitest, and the production Vite build.

Configure VS Code format-on-save for backend and frontend.

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

### Pre-push

- PHPStan/Larastan at maximum practical level;
- PHPUnit;
- Vitest;
- production frontend build.

Playwright runs before deployment.

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
