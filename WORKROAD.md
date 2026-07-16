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
- update this index when a phase is added or its status changes;
- keep detailed contracts and checkboxes out of this index;
- chat history must not be required to understand accepted behavior;
- use `docs/roadmap/_template.md` for every new phase.

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

- Phase: [Phase 8 — Sessions and active team](docs/roadmap/phase-08-sessions-active-team.md)
- Status: `not started`

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

**Status:** `blocked pending Phase 6/7 UI visibility primitives`

Create modular-monolith boundaries, public contracts, ModuleGate, Outbox, architecture tests, and shared cross-module primitives.

[Open implementation contract and tasks](docs/roadmap/phase-05-modular-architecture.md)

### Phase 6 — Core identity and authentication

**Status:** `complete`

Implement identity, login security, password lifecycle, email verification, MFA, lockouts, and authentication rate limits.

[Open implementation contract and tasks](docs/roadmap/phase-06-identity-authentication.md)

### Phase 7 — Authorization and teams

**Status:** `complete`

Implement teams, roles, permissions, starter roles, permission catalogs, and the first-administrator bootstrap.

[Open implementation contract and tasks](docs/roadmap/phase-07-authorization-teams.md)

### Phase 8 — Sessions and active team

**Status:** `not started`

Implement Redis-backed sessions, active-team context, session administration, and session security controls.

[Open implementation contract and tasks](docs/roadmap/phase-08-sessions-active-team.md)

### Phase 9 — Manager hierarchy

**Status:** `not started`

Implement effective-dated manager hierarchy, DAG validation, direct-report and subtree scope, and manager administration.

[Open implementation contract and tasks](docs/roadmap/phase-09-manager-hierarchy.md)

### Phase 10 — Audit and security audit

**Status:** `not started`

Implement application and security audit trails with immutable context, correlation, querying, retention, and privacy controls.

[Open implementation contract and tasks](docs/roadmap/phase-10-audit-security.md)

### Phase 11 — Settings and localization

**Status:** `not started`

Implement typed settings, localization, precedence, validation, caching, and safe administrative configuration.

[Open implementation contract and tasks](docs/roadmap/phase-11-settings-localization.md)

### Phase 12 — Notifications and realtime foundation

**Status:** `not started`

Implement typed notifications, delivery channels, preferences, queueing, and the minimal realtime foundation.

[Open implementation contract and tasks](docs/roadmap/phase-12-notifications-realtime.md)

### Phase 13 — Module availability and activation

**Status:** `not started`

Implement deployment availability, global/team activation, schedules, dependencies, cache invalidation, and central gate enforcement.

[Open implementation contract and tasks](docs/roadmap/phase-13-module-activation.md)

### Phase 14 — Administrative mode and impersonation

**Status:** `not started`

Implement Admin mode, high-risk reauthentication, account sensitivity, secure impersonation, and isolated TimeTracking simulation.

[Open implementation contract and tasks](docs/roadmap/phase-14-admin-impersonation.md)

### Phase 15 — Shared UI components

**Status:** `not started`

Build reusable accessible UI primitives, forms, confirmations, alerts, formatters, layouts, and fixed application navigation.

[Open implementation contract and tasks](docs/roadmap/phase-15-shared-ui.md)

### Phase 16 — Shared table, saved views, reports, exports, charts, and print

**Status:** `not started`

Build shared tables, saved views, report/export pipelines, charts, browser print, and Chromium-based PDF generation.

[Open implementation contract and tasks](docs/roadmap/phase-16-tables-reports-exports.md)

### Phase 17 — Files

**Status:** `not started`

Implement private file storage, validation, quarantine, ClamAV scanning, retention, authorization, and administrative operations.

[Open implementation contract and tasks](docs/roadmap/phase-17-files.md)

### Phase 18 — Imports

**Status:** `not started`

Implement reusable import pipelines, mapping, validation, previews, idempotency, progress, errors, and audit.

[Open implementation contract and tasks](docs/roadmap/phase-18-imports.md)

### Phase 19 — Search

**Status:** `not started`

Implement full-text search as module-owned Meilisearch projections with Outbox indexing and zero-downtime rebuilds.

[Open implementation contract and tasks](docs/roadmap/phase-19-search.md)

### Phase 20 — Integrations

**Status:** `not started`

Implement typed external integration adapters, idempotency, retries, circuit breaking, API boundaries, and operational visibility.

[Open implementation contract and tasks](docs/roadmap/phase-20-integrations.md)

### Phase 21 — Feature flags

**Status:** `not started`

Implement typed feature flags with safe targeting, evaluation, lifecycle, audit, and administrative controls.

[Open implementation contract and tasks](docs/roadmap/phase-21-feature-flags.md)

### Phase 22 — Admin operations and health

**Status:** `not started`

Implement Admin operational screens, structured logging, health/readiness, alerts, queues, scheduler, rate-limit administration, and diagnostics.

[Open implementation contract and tasks](docs/roadmap/phase-22-admin-health.md)

### Phase 23 — Optional TimeTracking module

**Status:** `not started`

Implement optional operational TimeTracking, breaks, other work, inactivity, corrections, settlement, reporting, and analysis-ready data.

[Open implementation contract and tasks](docs/roadmap/phase-23-time-tracking.md)

### Phase 24 — Security, privacy, deletion, and anonymization

**Status:** `not started`

Implement privacy, retention, hard deletion, anonymization orchestration, legal holds, previews, approvals, and evidence.

[Open implementation contract and tasks](docs/roadmap/phase-24-security-privacy.md)

### Phase 25 — Production deployment, backup, restore, and rollback

**Status:** `not started`

Implement the single-host production Docker topology, HTTPS, deployment releases, PostgreSQL backups, restore, readiness, and rollback.

[Open implementation contract and tasks](docs/roadmap/phase-25-deployment-backup-rollback.md)

### Phase 26 — Final foundation verification

**Status:** `not started`

Perform final architecture, security, documentation, testing, restore, deployment, and technical-foundation verification before debt collection business modules begin.

[Open implementation contract and tasks](docs/roadmap/phase-26-final-verification.md)
