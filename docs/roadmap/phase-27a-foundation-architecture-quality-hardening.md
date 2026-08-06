# Phase 27a — Foundation architecture and quality-gate hardening

**Status:** `complete`

## Objective

Close cross-cutting architecture and quality-gate issues revealed after the large Phase 27 TimeTracking implementation before later foundation work, backend-surface auditing, and production deployment start depending on those mechanisms.

## Dependencies

- [Phase 4 — Quality workflow](phase-04-quality.md)
- [Phase 5 — Modular architecture skeleton](phase-05-modular-architecture.md)
- [Phase 14a — PostgreSQL module schemas](phase-14a-postgresql-module-schemas.md)
- [Phase 26 — Security, privacy, deletion, and anonymization](phase-26-security-privacy.md)
- [Phase 27 — Optional TimeTracking module](phase-27-time-tracking.md)
- [Modular monolith architecture](../architecture/modular-monolith.md)
- [Security baseline](../architecture/security-baseline.md)
- [Quality gates, Git, and commits](../operations/quality-gates-and-git.md)
- [Identity, authentication, users, and sessions](../modules/identity-authentication-and-sessions.md)
- [Privacy and retention](../modules/privacy.md)
- [TimeTracking module](../modules/time-tracking.md)

## Implementation contract

- `composer analyse` must cover every PHPStan-configured path. Operational chunking may remain, but targets must be discovered deterministically from `phpstan.neon`; adding an `app` module or global PHP directory must not require editing `composer.json`.
- Public quality gates must be complete and non-mutating. `composer check` runs backend formatting checks, frontend formatting checks, ESLint, Stylelint, secret checks, unwanted-file checks, `.env.example` duplicate checks, PHPStan/Larastan, PHPUnit, TypeScript typechecking, Vitest, and the production Vite build without modifying source files.
- Lefthook pre-push must include TypeScript typechecking through public commands.
- Module-boundary guardrails cover the whole `app` tree, not only `app/Modules`, and use structural PHP token analysis for imports and fully qualified references.
- Global application composition may use module public contracts and explicit Presentation contributions. It must not import module internals, module Infrastructure, or query module-owned tables directly.
- `HandleInertiaRequests` remains a thin Inertia adapter that merges global shared data and explicitly registered shared-data contributions while preserving current prop shape, route names, URLs, permissions, active-team semantics, ModuleGate behavior, impersonation behavior, and TimeTracking behavior.
- Shared-data contributors are owned by the module whose data they provide, are registered explicitly through providers, merge deterministically, and fail on duplicate ownership of the same prop path.
- Generic high-risk administrative reauthorization must not know Privacy route names or Privacy form fields. Privacy owns validation and temporary continuation payload storage for its own operations, and only validated allowlisted fields may be retained for one-time use after reauthorization.
- `App\Shared\Infrastructure\Database\DatabaseTable` contains only shared-infrastructure table names. Module-owned table names live in final module-local table-name classes under the owning module's `Application/Public/Persistence` namespace.
- Module table-name classes are explicit public persistence identifiers owned by their module. Cross-module reads still require public contracts or owner-owned participants; importing a table-name class does not grant permission to query another module's tables directly.
- `DatabaseSchema` may remain the central schema-topology registry; a schema-qualified name remains deployment topology, not permission for cross-module table access.
- `.env.example` must not contain duplicate active keys and must remain aligned with accepted file/avatar WebP support, Docker Compose defaults, and documented configuration.
- Playwright package version and Dev Container `PLAYWRIGHT_VERSION` must remain aligned by an automated guardrail or a single documented source.
- No user-facing behavior, URL, route name, permission name, database schema/table name, localization behavior, audit semantics, manager scope, impersonation behavior, Privacy preview behavior, or Phase 27 TimeTracking contract may regress.

## Tasks

- [x] Rename existing lettered Phase 27 roadmap files so Demo seeders become Phase 27b and bilingual emails become Phase 27c.
- [x] Add this Phase 27a contract and update `WORKROAD.md` so Phase 27 is complete, Phase 27a is current while active, and Phase 27b remains the next not-started phase after completion.
- [x] Replace manually maintained PHPStan path batches with deterministic target discovery from `phpstan.neon`.
- [x] Add a guardrail proving public `composer analyse` covers every PHPStan-configured PHP path.
- [x] Make `composer check` a full non-mutating repository verification gate and remove duplicate gate execution.
- [x] Ensure Lefthook pre-push includes TypeScript typechecking through public commands.
- [x] Extend architecture tests across all `app` PHP code using token-based namespace reference analysis.
- [x] Move module-owned table names from Shared `DatabaseTable` into module-local final table-name classes.
- [x] Replace legal global or owner-local table references with the correct table-name owner and remove illegal cross-module table usage from global code.
- [x] Add a guardrail preventing module-owned table constants from returning through the central Shared table registry.
- [x] Introduce explicit shared Inertia data contributions with deterministic merge and duplicate-path detection.
- [x] Move module-owned Inertia shared props out of `HandleInertiaRequests`.
- [x] Add shared-props tests for guest, authenticated user, active team, TimeTracking-enabled user, manager, administrator, impersonation, disabled module, and missing permissions.
- [x] Move Privacy preview validation and one-time continuation payload handling out of generic high-risk middleware.
- [x] Add high-risk continuation tests for validation-before-store, invalid payload rejection, allowed payload restoration, one-time cleanup, safe intended URLs, and non-Privacy high-risk independence.
- [x] Remove duplicate `.env.example` keys, preserve accepted WebP upload support, and check for other duplicates.
- [x] Add an automated `.env.example` duplicate-key guardrail to public quality gates.
- [x] Add a Playwright version drift guardrail covering `package.json`, `pnpm-lock.yaml`, and Dev Container `PLAYWRIGHT_VERSION`.
- [x] Update architecture, operations, and module documentation for the durable contracts changed in this phase.
- [x] Run required quality gates and record the result before marking the phase complete.

## Completion criteria

- [x] PHPStan covers every configured path through the public `composer analyse` command.
- [x] `composer check` is complete and non-mutating.
- [x] Global `HandleInertiaRequests` does not know module internals or module-owned tables.
- [x] Module shared-data contributions are explicitly registered, deterministic, collision-checked, and tested.
- [x] Generic high-risk middleware does not know Privacy routes or fields.
- [x] Central `DatabaseTable` contains only shared-infrastructure tables.
- [x] Module table-name classes are module-owned public persistence identifiers, and non-public cross-module imports are blocked.
- [x] `.env.example` contains no duplicate active keys.
- [x] WebP and related file upload defaults are consistent.
- [x] Guardrails prevent regressions in quality coverage, module boundaries, table ownership, configuration duplicates, and Playwright version drift.
- [x] Required tests and quality gates pass.
- [x] Canonical documentation is current.
- [x] `WORKROAD.md` points to Phase 27b as the first unfinished phase after completion.

## Quality gate record

- `composer check` passed on 2026-08-06 with 490 PHPUnit tests, 59 Vitest tests, PHPStan/Larastan, TypeScript typechecking, frontend linting/format checks, Stylelint, repository configuration guardrails, and production Vite build.
