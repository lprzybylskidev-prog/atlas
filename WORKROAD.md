# WORKROAD.md

## Purpose

This file is the lightweight, durable index of the **Atlas debt collection system** evolution roadmap.

Binding implementation contracts, task checkboxes, and phase history live in the linked files under `docs/roadmap/`.

Rules:

- work from top to bottom unless the user explicitly selects another phase;
- use the first unfinished item in the relevant phase file;
- never delete or rewrite completed phase tasks;
- unfinished phase work may be split or expanded;
- substantial later evolution of completed work receives a new sequential phase;
- update this index when a phase is added, reordered, split, merged, or its status changes;
- keep detailed contracts and checkboxes out of this index;
- chat history must not be required to understand accepted behavior;
- use `docs/roadmap/_template.md` for every new phase;
- before adding or reordering phases, analyze dependency order across existing code, completed phases, future phases, canonical module docs, architecture docs, and operational docs;
- a shared capability must be fully implemented, tested, and documented before the first phase that uses it, unless the genuinely new requirement was not known earlier and is added as a later evolution phase.

## Purpose and Lifetime

`WORKROAD.md` remains active for the full life of Atlas.

It provides a fast map of:

- completed, active, and planned phases;
- the current or first unfinished phase;
- the system's major evolution steps;
- the canonical phase files containing binding details.

Roadmap history is preserved in phase files. Current module behavior is documented separately under `docs/modules/`.

Do not replace this index after the initial technical foundation is completed. Continue it with new sequential phases as the application evolves.

## Current phase

- Phase: [Phase 23 — Feature flags](docs/roadmap/phase-23-feature-flags.md)
- Status: `complete`

## Roadmap dependency repair note

After Phase 7 completed, the roadmap was reordered because several shared foundations were already used before their full known contracts were scheduled. The repaired order preserves completed phase history, adds an immediate closure phase for partial foundations, splits table/saved-view work from later report/export/PDF generation, and moves audit, settings, sessions, module activation, notifications, health, and Admin foundations before modules that depend on them.

Before Phase 15 starts, Phase 14a was added as a targeted dependency repair because module-owned PostgreSQL schemas must exist before additional module persistence is introduced.

## Phase index

### Phase 0 — Repository bootstrap

**Status:** `complete`

Initialize the independent repository, naming, seed strategy, source-delivery model, and foundational documentation.

[Open implementation contract and tasks](docs/roadmap/phase-00-bootstrap.md)

### Phase 1 — Dev Container and Docker skeleton

**Status:** `complete`

Create the reproducible development container and initial Docker skeleton while protecting the Codex development workflow.

[Open implementation contract and tasks](docs/roadmap/phase-01-devcontainer.md)

### Phase 2 — Laravel foundation

**Status:** `complete`

Install and configure the Laravel backend foundation, shared primitives, timezone, money model, Redis, and core runtime services.

[Open implementation contract and tasks](docs/roadmap/phase-02-laravel.md)

### Phase 3 — Frontend foundation

**Status:** `complete`

Build the Vue/Inertia/Tailwind frontend foundation, fixed composable views, themes, accessibility, and TailAdmin licensing guard.

[Open implementation contract and tasks](docs/roadmap/phase-03-frontend.md)

### Phase 4 — Quality workflow

**Status:** `complete`

Establish deterministic local quality commands, tests, static analysis, formatting, and Lefthook gates without bundled CI.

[Open implementation contract and tasks](docs/roadmap/phase-04-quality.md)

### Phase 5 — Modular architecture skeleton

**Status:** `complete with follow-up closure moved to Phase 8`

Create modular-monolith boundaries, public contracts, ModuleGate, Outbox, architecture tests, and shared cross-module primitives. The former visibility e2e follow-up is no longer blocked by phases 6-7 and is now tracked in Phase 8.

[Open implementation contract and tasks](docs/roadmap/phase-05-modular-architecture.md)

### Phase 6 — Core identity and authentication

**Status:** `complete`

Implement identity, login security, password lifecycle, email verification, MFA, lockouts, and authentication rate limits.

[Open implementation contract and tasks](docs/roadmap/phase-06-identity-authentication.md)

### Phase 7 — Authorization and teams

**Status:** `complete`

Implement teams, roles, permissions, starter roles, permission catalogs, onboarding packages, and the first-administrator bootstrap.

[Open implementation contract and tasks](docs/roadmap/phase-07-authorization-teams.md)

### Phase 8 — Foundation completion and roadmap dependency repair

**Status:** `complete`

Close partial foundations pulled forward during phases 5-7, including current Admin shared UI/table consistency, visibility e2e coverage, audit/module-gate documentation, and dependency-first roadmap rules.

[Open implementation contract and tasks](docs/roadmap/phase-08-foundation-completion.md)

### Phase 9 — Shared UI components

**Status:** `complete`

Build reusable accessible UI primitives, forms, confirmations, alerts, formatters, layouts, and fixed application interaction patterns before additional screens depend on them.

[Open implementation contract and tasks](docs/roadmap/phase-09-shared-ui.md)

### Phase 10 — Shared tables and saved views

**Status:** `complete`

Complete the shared TanStack table and saved-view foundation before future Admin and business tables are implemented.

[Open implementation contract and tasks](docs/roadmap/phase-10-shared-tables-saved-views.md)

### Phase 11 — Audit and security audit

**Status:** `complete`

Implement application and security audit trails with immutable context, correlation, querying, retention, and privacy controls before high-risk and operational phases depend on them.

[Open implementation contract and tasks](docs/roadmap/phase-11-audit-security.md)

### Phase 12 — Settings and localization

**Status:** `complete`

Implement typed settings, localization, precedence, validation, caching, and safe administrative configuration.

[Open implementation contract and tasks](docs/roadmap/phase-12-settings-localization.md)

### Phase 13 — Sessions and active team

**Status:** `complete`

Implement Redis-backed sessions, active-team context, session administration, session security controls, and centralized frontend network handling.

[Open implementation contract and tasks](docs/roadmap/phase-13-sessions-active-team.md)

### Phase 14 — Module availability and activation

**Status:** `complete`

Implement deployment availability, global/team activation, schedules, dependencies, cache invalidation, deactivation guards, and central gate enforcement.

[Open implementation contract and tasks](docs/roadmap/phase-14-module-activation.md)

### Phase 14a — PostgreSQL module schemas

**Status:** `complete`

Move Atlas-owned database tables from the default `public` schema into explicit module and shared-infrastructure PostgreSQL schemas before later phases add more persistence.

[Open implementation contract and tasks](docs/roadmap/phase-14a-postgresql-module-schemas.md)

### Phase 15 — Notifications and realtime foundation

**Status:** `complete`

Implement typed notifications, delivery channels, preferences, queueing, and the minimal realtime foundation.

[Open implementation contract and tasks](docs/roadmap/phase-15-notifications-realtime.md)

### Phase 16 — Admin operations and health

**Status:** `complete`

Implement Admin operational screens, structured logging, health/readiness, alerts, queues, scheduler, rate-limit administration, and diagnostics.

[Open implementation contract and tasks](docs/roadmap/phase-16-admin-health.md)

### Phase 17 — Manager hierarchy

**Status:** `complete`

Implement effective-dated manager hierarchy, DAG validation, direct-report and subtree scope, and manager administration.

[Open implementation contract and tasks](docs/roadmap/phase-17-manager-hierarchy.md)

### Phase 18 — Administrative mode and impersonation

**Status:** `complete`

Implement Admin mode, high-risk reauthentication, account sensitivity, secure impersonation, and isolated TimeTracking simulation.

[Open implementation contract and tasks](docs/roadmap/phase-18-admin-impersonation.md)

### Phase 19 — Files

**Status:** `complete`

Implement private file storage, validation, quarantine, ClamAV scanning, retention participation, authorization, and administrative operations.

[Open implementation contract and tasks](docs/roadmap/phase-19-files.md)

### Phase 20 — Integrations

**Status:** `complete`

Implement typed external integration adapters, idempotency, retries, circuit breaking, API boundaries, credentials, and operational visibility.

[Open implementation contract and tasks](docs/roadmap/phase-20-integrations.md)

### Phase 20a — Audit context and security category hardening

**Status:** `complete`

Harden Audit context discovery and security category classification before non-HTTP import workflows depend on audit.

[Open implementation contract and tasks](docs/roadmap/phase-20a-audit-hardening.md)

### Phase 20b — Managed processes, process logs, and scheduler

**Status:** `complete`

Implement the shared run, queue, structured process-log, progress, retry/cancel, schedule, notification, audit, and Admin visibility foundation before Imports and later long-running workflows depend on it.

[Open implementation contract and tasks](docs/roadmap/phase-20b-managed-processes.md)

### Phase 21 — Imports

**Status:** `complete`

Implement reusable import pipelines on top of the managed-process foundation after files, notifications, audit, integrations, module activation, and operational health are complete.

[Open implementation contract and tasks](docs/roadmap/phase-21-imports.md)

### Phase 22 — Search

**Status:** `complete`

Implement full-text search as module-owned Meilisearch projections with Outbox indexing, authorization, visibility, and zero-downtime rebuilds.

[Open implementation contract and tasks](docs/roadmap/phase-22-search.md)

### Phase 22a — Frontend rebuild and design system hardening

**Status:** `complete`

Rebuild the current Atlas frontend into a consistent, reusable, documented, and tested UI system before adding more Auth, application, or Admin workflows and modules.

[Open implementation contract and tasks](docs/roadmap/phase-22a-frontend-rebuild.md)

### Phase 23 — Feature flags

**Status:** `complete`

Implement typed feature flags with safe targeting, evaluation, lifecycle, audit, and administrative controls.

[Open implementation contract and tasks](docs/roadmap/phase-23-feature-flags.md)

### Phase 24 — Reports, exports, PDF, charts, and print

**Status:** `not started`

Build report/export pipelines, browser print, Chromium-based PDF generation, chart wrappers, artifact storage, notifications, and report layouts after table, file, notification, audit, and health foundations exist.

[Open implementation contract and tasks](docs/roadmap/phase-24-reports-exports-print.md)

### Phase 25 — Security, privacy, deletion, and anonymization

**Status:** `not started`

Implement privacy, retention, hard deletion, anonymization orchestration, legal holds, previews, approvals, and evidence after controlled copy owners exist.

[Open implementation contract and tasks](docs/roadmap/phase-25-security-privacy.md)

### Phase 26 — Optional TimeTracking module

**Status:** `not started`

Implement optional operational TimeTracking, breaks, other work, inactivity, corrections, settlement, reporting, and analysis-ready data after all known shared dependencies exist.

[Open implementation contract and tasks](docs/roadmap/phase-26-time-tracking.md)

### Phase 27 — Production deployment, backup, restore, and rollback

**Status:** `not started`

Implement the single-host production Docker topology, HTTPS, deployment releases, PostgreSQL backups, restore, readiness, and rollback.

[Open implementation contract and tasks](docs/roadmap/phase-27-deployment-backup-rollback.md)

### Phase 28 — Final foundation verification

**Status:** `not started`

Perform final architecture, security, documentation, testing, restore, deployment, and technical-foundation verification before debt collection business modules begin.

[Open implementation contract and tasks](docs/roadmap/phase-28-final-verification.md)
