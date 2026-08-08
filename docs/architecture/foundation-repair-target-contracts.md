# Foundation repair target contracts

This document records accepted Phase 28 target contracts for the Atlas foundation. It distinguishes the current implemented state from the required target state. The binding issue register and executable checkboxes live in [Phase 28 — Foundation repair and consolidation](../roadmap/phase-28-foundation-repair-and-consolidation.md).

## Status

- Current implementation: Phases 27 and 27a are complete, but later review found known foundation noncompliance tracked by Phase 28 issue IDs.
- Target implementation: Phase 28 repairs the known drift before production deployment.
- Phase 28 implementation status: not started.

## Module graph and public contracts

Current known noncompliance: `P28-ARCH-001` through `P28-ARCH-014`, `P28-MOD-001` through `P28-MOD-005`.

Target contract:

- real code imports, providers, middleware, migrations, configuration references, and table access match `ModuleDefinition`;
- dependencies are acyclic;
- optional dependencies are truly optional and have reduced modes;
- Core modules do not depend on Optional modules unless an ADR changes the classification or a shared capability is extracted;
- cross-module synchronous access uses owner-owned public contracts;
- public table constants and schema-qualified table names are not cross-module APIs;
- global providers and middleware use public contracts, shared contracts, or explicit Presentation contributions only.

## Module metadata and activation

Current known noncompliance: `P28-MOD-001` through `P28-MOD-005`.

Target contract:

- `requiredDependencies`, `optionalDependencies`, `integrations`, `healthChecks`, `frontendEntrypoints`, and activation support are executable and tested, or removed;
- ModuleGate checks deployed state, dependency technical availability, global/team activation, active-team validity, and permissions;
- technical availability comes from real health, integration, binary, service, queue, storage, configuration, and credential checks;
- deactivation is blocked by reverse dependencies and unsafe in-flight work, and all attempts are audited.

## Audit contracts and event coverage

Current known noncompliance: `P28-AUDIT-001` through `P28-AUDIT-011`.

Target contract:

- each module with meaningful mutating operations registers a typed audit action catalog;
- audit action/result/source/target/aggregate values are not loose strings;
- security-sensitive and irreversible operations have success, rejection, and failure coverage;
- mandatory audit persistence and critical business changes share one transaction;
- Audit browser and exports use database-backed read models, stable pagination, indexes, and owner-owned label providers;
- audit metadata is secret-safe and validates allowed fields.

## Unified UI/UX contracts

Current known noncompliance: `P28-UI-001` through `P28-UI-008`, `P28-ACTION-001` through `P28-ACTION-003`, `P28-FORM-001` through `P28-FORM-006`, `P28-TABLE-001` through `P28-TABLE-009`, `P28-LOC-001`, and `P28-LOC-002`.

Target contract:

- one PL/EN glossary controls canonical labels in navigation, breadcrumbs, titles, forms, tables, messages, mails, docs, and tests;
- one typed navigation registry feeds desktop sidebar, mobile navigation, TopBar, shell subnavigation, breadcrumbs, route availability, active state, permissions, module gates, and shell mode;
- one action contract feeds list, detail, edit, row, and bulk actions;
- one confirmation system handles destructive and high-risk actions with named operations and reason requirements;
- one CRUD/form contract controls create/edit/show/index structure, dirty state, save scope, `Anuluj`, and `Wroc` semantics;
- one DataTable contract owns columns, filters, saved views, sorting, pagination, selection, actions, exports, states, responsive rendering, formatting, and permissions;
- one status catalog owns status labels, colors, icons, meanings, and allowed surfaces;
- missing translations fail quality gates instead of being humanized into plausible UI text;
- regular-user and manager UI do not expose raw technical tokens.

## Authorization, assignments, and manager hierarchy

Current known noncompliance: `P28-AUTH-001` through `P28-AUTH-005`.

Target contract:

- manager hierarchy belongs to Teams;
- the separate Admin Managers CRUD surface is removed;
- the team structure editor is the canonical hierarchy surface;
- user-team authorization uses one workflow from user and team sides;
- assignment provenance stores manual, preset, and copy source details truthfully.

## TimeTracking

Current known noncompliance: `P28-TT-001` through `P28-TT-004`.

Target contract:

- TimeTracking uses one route-backed model for user, manager, and Admin surfaces;
- shared composables, status catalog, action contract, DataTable, formatters, and workflow components replace large mixed-responsibility components;
- manager and Admin views differ only by scope and permission;
- every correction, decision, manual entry, session end, force close, break conversion, other-work decision, category change, and maintenance operation has complete atomic audit evidence.

## Migrations and schema ownership

Current known noncompliance: `P28-MIG-001` through `P28-MIG-003`.

Target contract:

- before first production deployment, not-yet-deployed migrations are squashed into canonical create migrations;
- Atlas-owned tables are schema-qualified and do not rely on PostgreSQL `search_path`;
- PostgreSQL `after` is forbidden;
- constraints, indexes, FKs, checks, and triggers exist in canonical create migrations;
- local reset requirements after squash are documented.

## Seeders and demo data

Current known noncompliance: `P28-SEED-001`.

Target contract:

- technical seeders are production-safe and idempotent;
- demo/e2e seeders are deterministic, idempotent, invariant-preserving, and unavailable in production;
- public Application contracts are used where feasible;
- dedicated fixture builders belong to owning modules, centralize invariants, are tested, and are not registered in production.

## Mail architecture

Current known noncompliance: `P28-MAIL-001` and `P28-MAIL-002`.

Target contract:

- every Atlas-owned mail uses Laravel translation keys and the shared branded template;
- Polish and English sections are rendered in one message;
- effective locale is first;
- plain-text fallback exists;
- hardcoded user-facing mail copy is forbidden;
- no secrets, raw tokens, internal IDs, or unnecessary diagnostics appear in user-facing mail.

## Runtime and guardrail architecture

Current known noncompliance: `P28-RUNTIME-001` through `P28-RUNTIME-014`, `P28-GUARD-001` through `P28-GUARD-003`, and `P28-LEGACY-001`.

Target contract:

- Dev Container, production images, Compose services, and manual server installation have documented parity;
- production images are reproducible immutable artifacts built from repository source and lockfiles;
- PHP runtime roles share one artifact where possible;
- worker, scheduler, Horizon, queues, ClamAV, Chromium/PDF, Search, Files, and ManagedProcesses have real readiness and smoke coverage;
- `composer check:foundation` is the required full Phase 28 gate;
- legacy solutions are removed after migration with no-reference evidence.
