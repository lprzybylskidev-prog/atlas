# WORKROAD.md

## Purpose

This file is the lightweight, durable index of the **Atlas debt collection system** evolution roadmap.

Binding implementation contracts, task checkboxes, and phase history live in the linked files under `docs/roadmap/`.

Rules:

- work from top to bottom unless the user explicitly selects another phase;
- use the first unfinished item in the relevant phase file;
- never delete or rewrite completed phase tasks;
- unfinished phase work may be split or expanded;
- do not create new phases with letter suffixes; large scopes may use workstreams, packages, and issue IDs inside one phase;
- unstarted phases may be merged or replaced only after their scope is moved without loss;
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

- Phase: [Phase 31 — Optional internal company chat, calendar, calls, meetings, and realtime communication](docs/roadmap/phase-31-chat.md)
- Status: `in progress` (`P31-W01` through `P31-W04` complete; `P31-W05` is next)

## Roadmap dependency repair note

After Phase 7 completed, the roadmap was reordered because several shared foundations were already used before their full known contracts were scheduled. The repaired order preserves completed phase history, adds an immediate closure phase for partial foundations, splits table/saved-view work from later report/export/PDF generation, and moves audit, settings, sessions, module activation, notifications, health, and Admin foundations before modules that depend on them.

Before Phase 15 starts, Phase 14a was added as a targeted dependency repair because module-owned PostgreSQL schemas must exist before additional module persistence is introduced.

After Phase 27 completed, Phase 27a was inserted as a dependency-repair phase because the large TimeTracking implementation revealed or amplified cross-cutting issues in module boundaries, persistence ownership, configuration consistency, and quality-gate completeness. These repairs must land before the next foundation-hardening phases and before production deployment work.

After Phase 27a completed, further foundation review consolidated the unstarted Phase 27b, Phase 27c, and former Phase 28 scopes into a single Phase 28 repair contract. Phase 27 and Phase 27a remain completed historical phases. Phase 28 is the first unfinished phase and must close all known foundation repair work before Phase 30 production deployment and Phase 31 final release verification.

After Phase 28 completed, an independent post-completion acceptance review identified concrete gaps in the implementation of some accepted contracts. Phase 28 remains complete and its historical implementation record is not rewritten. A new sequential Phase 29 was therefore added as a later foundation acceptance repair. The previously unstarted deployment Phase 29 moved to Phase 30, and the previously unstarted final-verification Phase 30 moved to Phase 31. No deployment or final-verification scope was removed.

Before the then-current Phase 30 deployment began, a new internal company Chat requirement was accepted. Chat depends on the already completed Files, Search, Teams, Module Activation, shared UI, and realtime foundations, so Chat became the new Phase 30. The previously unstarted deployment Phase 30 moved to Phase 31, and the previously unstarted final-verification Phase 31 moved to Phase 32. No deployment or final-verification scope was removed, and the earlier Phase 29/30/31 reorder history above remains unchanged.

Chat had already been planned as Phase 30 but had not started when manual review of the completed Teams and Authorization foundation exposed a coherent set of Team Structure, authorization presentation, mutation-feedback, and provenance problems. Because Teams and Authorization are shared foundations used by Chat and later modules, the repair must land before Chat. A new Phase 30 was inserted; the previous Chat Phase 30 moved to Phase 31, deployment Phase 31 moved to Phase 32, and final verification Phase 32 moved to Phase 33. No Chat, deployment, or final-verification scope was removed.

After Phase 30 completed and before Phase 31 implementation started, the internal communication requirements expanded to include a shared Core Calendar, direct/group/Team Calls, Meetings, screen sharing, Meeting recording, and provider-neutral future transcription readiness. Because Phase 31 remained unstarted, its contract was expanded in place. Phase 32 deployment planning now includes the required self-hosted LiveKit/TURN/Egress production boundary, and Phase 33 verification planning includes the expanded communication workflows. No existing Chat, deployment, or final-verification scope was removed.

After Phase 31 implementation had started and its completed work had been committed, the future need for a first-party Diagnostics and User Bug Report capability was accepted and recorded immediately so its scope would not be lost. Diagnostics became the new Phase 32 but was not started; deployment moved from Phase 32 to Phase 33, and final verification moved from Phase 33 to Phase 34. This planning change did not interrupt or reopen Phase 31 implementation, and no deployment or final-verification scope was removed.

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

Build the Vue/Inertia/Tailwind frontend foundation, fixed composable views, themes, accessibility, and shared Atlas UI rules.

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

**Status:** `complete`

Build report/export pipelines, browser print, Chromium-based PDF generation, chart wrappers, artifact storage, notifications, and report layouts after table, file, notification, audit, and health foundations exist.

[Open implementation contract and tasks](docs/roadmap/phase-24-reports-exports-print.md)

### Phase 24a — Core export foundation and Admin data integration

**Status:** `complete`

Extract reusable export, PDF, print, artifact, and render lifecycle ownership into Core and add export/print support to every exportable Admin DataTable and custom Admin data surface while keeping Reports optional.

[Open implementation contract and tasks](docs/roadmap/phase-24a-core-export-foundation.md)

### Phase 25 — Admin panel rebuild and operational UX repair

**Status:** `complete`

Rebuild Admin panel views from the existing backend foundations and shared frontend primitives, fixing partial localization, duplicated operational surfaces, dashboard signal ownership, toast storms, bounded-view disclaimers, and operational incident lifecycle.

[Open implementation contract and tasks](docs/roadmap/phase-25-admin-panel-rebuild-operational-ux.md)

### Phase 26 — Security, privacy, deletion, and anonymization

**Status:** `complete`

Implement privacy, retention, hard deletion, anonymization orchestration, legal holds, previews, approvals, and evidence after controlled copy owners exist.

[Open implementation contract and tasks](docs/roadmap/phase-26-security-privacy.md)

### Phase 27 — Optional TimeTracking module

**Status:** `complete`

Implement optional operational TimeTracking, breaks, other work, inactivity, corrections, settlement, reporting, and analysis-ready data after all known shared dependencies exist.

[Open implementation contract and tasks](docs/roadmap/phase-27-time-tracking.md)

### Phase 27a — Foundation architecture and quality-gate hardening

**Status:** `complete`

Harden architecture boundaries, shared Inertia composition, module-owned persistence table names, high-risk reauthorization separation, and full local quality-gate coverage after the TimeTracking phase.

[Open implementation contract and tasks](docs/roadmap/phase-27a-foundation-architecture-quality-hardening.md)

### Phase 28 — Foundation repair and consolidation

**Status:** `complete`

Repair and consolidate known foundation drift across module boundaries, ModuleGate, audit, UI/UX, authorization/team workflows, TimeTracking, PostgreSQL migrations, seeders, bilingual mail, runtime images, queues, scheduler, health, and guardrails before production deployment.

[Open implementation contract and tasks](docs/roadmap/phase-28-foundation-repair-and-consolidation.md)

### Phase 29 — Foundation acceptance repair and rendered workflow closure

**Status:** `complete`

Close concrete post-Phase-28 acceptance gaps in localization, DataTable responsibility decomposition, the integrated Team Structure workflow, and TimeTracking browser-level E2E coverage without reopening Phase 28.

[Open implementation contract and tasks](docs/roadmap/phase-29-foundation-acceptance-repair.md)

### Phase 30 — Authorization, Team Structure, and mutation feedback repair

**Status:** `complete`

Repair Authorization assignment presentation and provenance semantics, make Team Edit authorization read-only, rebuild Team Structure around explicit structural roles and multi-manager relationships, and close silent mutation and flash-message feedback defects before Chat begins.

Depends on the completed Authorization, Teams, manager hierarchy, Audit, Settings and Localization, sessions/active-Team, shared UI, Admin mode, Phase 28, and Phase 29 foundations.

[Open implementation contract and tasks](docs/roadmap/phase-30-authorization-team-structure-feedback-repair.md)

### Phase 31 — Optional internal company chat, calendar, calls, meetings, and realtime communication

**Status:** `in progress` (`P31-W01` through `P31-W04` complete; `P31-W05` is next)

Implement Atlas-owned internal communication with direct/group/Team/Meeting Chat, a shared Core Calendar, audio/video Calls, online/in-person/hybrid Meetings, optional RTC for online/hybrid modes, screen sharing, Files-owned Meeting recordings, provider-neutral transcription readiness, Reverb and self-hosted LiveKit/Egress infrastructure, authorization-safe Search, privacy, retention, participant exports, and browser alerts.

Depends on the completed Files, Search, Teams, Authorization, Module Activation, shared UI, Notifications/realtime, Audit, Settings, Health, queue/scheduler, and export foundations, plus Phases 28, 29, and 30. Calendar is a shared Core capability; Chat remains optional, and LiveKit owns RTC transport rather than Atlas domain state.

[Open implementation contract and tasks](docs/roadmap/phase-31-chat.md)

### Phase 32 — Error reporting, user bug reports, and application diagnostics

**Status:** `not started`

Add first-party automatic Technical Issues, manual User Bug Reports, safe correlation and context, deduplication, privacy-preserving Admin investigation, Health/System Status integration, Notifications, retention, and production-aware private source diagnostics without replacing logs, Pulse, or Telescope.

Depends on the completed shared Atlas foundations and begins only after Phase 31 is complete.

[Open implementation contract and tasks](docs/roadmap/phase-32-error-reporting-and-diagnostics.md)

### Phase 33 — Private production deployment, installer, backup, restore, and rollback

**Status:** `not started`

Implement the private single-host/VM Docker Compose topology, interactive installer, encrypted persistent production storage boundary, independently encrypted portable/off-host backup artifacts, database and Files backup, restore, exact-release deployment, readiness, and rollback, including Atlas-managed LiveKit/TURN/Egress, recording/transcript recovery, and Diagnostics production requirements.

Depends on Phases 28, 29, 30, 31, and 32. Phase 28 provides reproducible images, runtime configuration, dependency readiness, queue/scheduler parity, ClamAV/PDF/Search/File smoke foundations, and an internal HTTP smoke stack. The Phase 33 baseline is private/intranet and does not require public Internet exposure; TLS supports internal/company certificates and keeps Let's Encrypt/ACME optional.

[Open implementation contract and tasks](docs/roadmap/phase-33-deployment-backup-rollback.md)

### Phase 34 — Final test audit, full-app E2E review, and foundation verification

**Status:** `not started`

Perform a full test-suite audit, browser-level E2E review of the whole application, architecture/security/documentation verification, Chat/Calendar/Calls/Meetings/recording/transcription, Diagnostics/User Bug Reports, and restore/deployment checks, and final technical-foundation hardening before debt collection business modules begin.

Depends on Phases 28, 29, 30, 31, 32, and 33. Phase 34 remains the final full-app release verification and does not replace earlier foundation repair, internal communication, Diagnostics, or deployment/recovery work.

[Open implementation contract and tasks](docs/roadmap/phase-34-final-verification.md)
