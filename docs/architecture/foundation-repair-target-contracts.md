# Foundation repair target contracts

This document records the accepted and implemented Phase 28 foundation contracts. The completed binding issue register, package evidence, and executable checkboxes live in [Phase 28 — Foundation repair and consolidation](../roadmap/phase-28-foundation-repair-and-consolidation.md).

## Status

- Current implementation: Phases 27, 27a, 28, 29, and 30 are complete. All 110 Phase 28 issue IDs and all 15 Phase 29 acceptance-repair issue IDs are reconciled to implementation, tests, permanent guardrails, canonical documentation, and package evidence; Phase 30 completed its Authorization, Team Structure, and mutation-feedback repair contract.
- Current roadmap order after the later accepted Team Structure repair insertion and Phase 30 completion: Phase 30 completed Authorization, Team Structure, and mutation feedback repair; Phase 31 is implementing optional internal Chat, shared Calendar, Calls, Meetings, recording, and provider-neutral transcription readiness; Phase 32 owns future first-party Diagnostics and User Bug Reports; Phase 33 owns production deployment, backup, restore, and rollback; and Phase 34 owns the distinct final release audit. Phase 31 is `in progress`; Phases 32 through 34 remain `not started`.

## Module graph and public contracts

Current implementation: the `P28-W02` module-boundary repair and `P28-W03` executable ModuleRegistry/ModuleGate workstream are complete. `P28-MOD-001` through `P28-MOD-005` are protected by permanent architecture and availability tests.

Target contract:

- real code imports, providers, middleware, migrations, configuration references, and table access match `ModuleDefinition`;
- dependencies are acyclic;
- optional dependencies are truly optional and have reduced modes;
- Core modules do not depend on Optional modules unless an ADR changes the classification or a shared capability is extracted;
- cross-module synchronous access uses owner-owned public contracts;
- public table constants and schema-qualified table names are not cross-module APIs;
- global providers and middleware use public contracts, shared contracts, or explicit Presentation contributions only.

The permanent architecture suite parses PHP with `nikic/php-parser`, discovers all configured modules and internal table catalogs, checks real and runtime-only dependency evidence, scans configuration/bootstrap/migrations, and includes mutation fixtures for grouped imports, FQCNs, inheritance, interfaces, types, attributes, static/class-string references, foreign table catalogs, embedded schema-qualified SQL, and forbidden global Infrastructure imports.

## Module metadata and activation

Implemented state: `P28-MOD-001` through `P28-MOD-005` are complete and protected by registry, ModuleGate, technical-availability, activation/deactivation, dependency, reduced-mode, and audit tests.

Target contract:

- `requiredDependencies`, `optionalDependencies`, `integrations`, `healthChecks`, `frontendEntrypoints`, and activation support are executable and tested, or removed;
- ModuleGate checks deployed state, dependency technical availability, global/team activation, active-team validity, and permissions;
- technical availability comes from real health, integration, binary, service, queue, storage, configuration, and credential checks;
- deactivation is blocked by reverse dependencies and unsafe in-flight work, and all attempts are audited.

## Audit contracts and event coverage

Implemented state: `P28-AUDIT-001` through `P28-AUDIT-011` are complete and protected by catalog, outcome, producer, atomicity, redaction, DB-backed browser/export, pagination, and mutation guardrails.

Target contract:

- each module with meaningful mutating operations registers a typed audit action catalog;
- audit action/result/source/target/aggregate values are not loose strings;
- security-sensitive and irreversible operations have success, rejection, and failure coverage;
- mandatory audit persistence and critical business changes share one transaction;
- Audit browser and exports use database-backed read models, stable pagination, indexes, and owner-owned label providers;
- audit metadata is secret-safe and validates allowed fields.

## Unified UI/UX contracts

Implemented state: `P28-UI-001` through `P28-UI-008`, `P28-ACTION-001` through `P28-ACTION-003`, `P28-FORM-001` through `P28-FORM-006`, `P28-TABLE-001` through `P28-TABLE-009`, `P28-LOC-001`, and `P28-LOC-002` are complete and protected by typed source, translation/glossary, shared-component, route/view, feature, Vitest, and Playwright guardrails.

Completed Phase 29 workstream `P29-W01` closes the later localization acceptance findings `P29-LOC-001` through `P29-LOC-004`: frontend and backend usage are inventoried against both locale catalogs, finite dynamic families are explicitly registered, PL/EN removals are mutation-tested, and the bilingual canonical route sweep rejects missing markers and visible raw Atlas key namespaces. `P29-W02` decomposes the DataTable host into focused state, persistence, saved-view, action, formatting, and result units; its permanent structural guard and mutation fixtures reject responsibility re-centralization.

Target contract:

- one PL/EN glossary controls canonical labels in navigation, breadcrumbs, titles, forms, tables, messages, mails, docs, and tests;
- one typed navigation registry feeds desktop sidebar, mobile navigation, TopBar, shell subnavigation, breadcrumbs, route availability, active state, permissions, module gates, and shell mode;
- one action contract feeds list, detail, edit, row, and bulk actions;
- one confirmation system handles destructive and high-risk actions with named operations and reason requirements;
- one CRUD/form contract controls create/edit/show/index structure, dirty state, save scope, `Anuluj`, and `Wroc` semantics;
- one DataTable contract owns columns, filters, saved views, sorting, pagination, selection, actions, exports, states, responsive rendering, formatting, and permissions; saved views use shell-neutral routes and typed table registration and are available to eligible regular-user and manager tables without Admin mode;
- one status catalog owns status labels, colors, icons, meanings, and allowed surfaces;
- missing translations fail quality gates instead of being humanized into plausible UI text;
- regular-user and manager UI do not expose raw technical tokens.

## Authorization, assignments, and manager hierarchy

Implemented state: `P28-AUTH-001` through `P28-AUTH-005` are complete and protected by provenance, stale-write, backend-authorization, manager-DAG/head-manager, legacy-reference, and browser tests.

Completed Phase 29 workstream `P29-W03` makes Team Structure the sole membership and hierarchy mutation surface. It covers membership history, add/end workflows, head-manager state, effective-dated atomic reparenting, stale writes, DAG and membership invariants, active-process blockers, transactional audit outcomes, and desktop/mobile/keyboard browser acceptance.

Target contract:

- manager hierarchy belongs to Teams;
- the separate Admin Managers CRUD surface is removed;
- the team structure editor is the canonical hierarchy surface;
- user-team authorization uses one workflow from user and team sides;
- assignment provenance stores manual, preset, and copy source details truthfully.

## TimeTracking

Implemented state: `P28-TT-001` through `P28-TT-004` are complete together with the `P28-MODAUD-*` capability audit and protected by route, scope, transaction/audit, shared-UI, incomplete-capability, removed-surface, and browser tests.

Completed Phase 29 workstream `P29-W04` adds deterministic Chromium and Firefox workflows for the mobile Polish user lifecycle, manager scope and decisions, resulting notifications, English Admin operations, maintenance evidence, and legacy-route absence. These workflows use the shared untranslated-copy, console, failed-request, and unexpected-HTTP guards.

Target contract:

- TimeTracking uses one route-backed model for user, manager, and Admin surfaces;
- shared composables, status catalog, action contract, DataTable, formatters, and workflow components replace large mixed-responsibility components;
- manager and Admin views differ only by scope and permission;
- every correction, decision, manual entry, session end, force close, break conversion, other-work decision, category change, and maintenance operation has complete atomic audit evidence.

## Migrations and schema ownership

Current state: `P28-MIG-001` through `P28-MIG-003` are complete.

Target contract:

- before first production deployment, not-yet-deployed migrations are squashed into canonical create migrations;
- Atlas-owned tables are schema-qualified and do not rely on PostgreSQL `search_path`;
- PostgreSQL `after` is forbidden;
- constraints, indexes, FKs, checks, and triggers exist in canonical create migrations;
- local reset requirements after squash are documented.

The canonical migration set contains create migrations only. Permanent unit guardrails reject follow-up table alters, PostgreSQL `after`, local historical table detection, destructive pre-create repairs, removed legacy schemas, unqualified Atlas table literals, and a broad configured `search_path`. PostgreSQL integration coverage verifies every registered owner table plus the Spatie Permission team FKs, Identity session FK, Audit append-only function/triggers, partial unique indexes, and Files/Privacy retention relationships against the fresh catalog.

## Seeders and demo data

Current state: `P28-SEED-001` is complete. Production-safe bootstrap seeders use public Application contracts, while deterministic demo/e2e records are delegated to owner fixture builders registered only in local, development, and testing environments. Permanent architecture tests reject persistence shortcuts in seeder classes and unguarded fixture-builder registration.

Target contract:

- technical seeders are production-safe and idempotent;
- demo/e2e seeders are deterministic, idempotent, invariant-preserving, and unavailable in production;
- public Application contracts are used where feasible;
- dedicated fixture builders belong to owning modules, centralize invariants, are tested, and are not registered in production.

## Mail architecture

Current state: `P28-MAIL-001` and `P28-MAIL-002` are complete and protected by bilingual render, translation-key, preference, and architecture guardrails.

Target contract:

- every Atlas-owned mail uses Laravel translation keys and the shared branded template;
- Polish and English sections are rendered in one message;
- effective locale is first;
- plain-text fallback exists;
- hardcoded user-facing mail copy is forbidden;
- no secrets, raw tokens, internal IDs, or unnecessary diagnostics appear in user-facing mail.

## Runtime and guardrail architecture

Current state: `P28-RUNTIME-001` through `P28-RUNTIME-014` are complete. The foundation gate runs the standard gate, full isolated Playwright suite, and production runtime smoke sequentially. Permanent mutation-tested source guards replace the temporary Phase 28 inventory generator and snapshots; removed legacy surfaces and components have active-tree no-reference coverage. Phase 34 owns the distinct post-deployment release gate after the completed Phase 30 Authorization/Team Structure repair, expanded Phase 31 internal communication scope, Phase 32 Diagnostics, and Phase 33 deployment.

Target contract:

- Dev Container, production images, Compose services, and manual server installation have documented parity;
- production images are reproducible immutable artifacts built from repository source and lockfiles;
- PHP runtime roles share one artifact where possible;
- worker, scheduler, Horizon, queues, ClamAV, Chromium/PDF, Search, Files, and ManagedProcesses have real readiness and smoke coverage;
- `composer check:foundation` is the required full Phase 28 gate;
- legacy solutions are removed after migration with no-reference evidence.
