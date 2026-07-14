# AGENTS.md

## Purpose

This repository contains **Atlas**, a large debt collection system developed for a debt collection company as a modular monolith.

This file is the permanent engineering constitution for agents working in the repository. It defines how to work, design, implement, test, document, review, and commit changes.

`AGENTS.md` is not a system specification. Keep module-specific behavior, detailed workflows, operational procedures, and implementation history in canonical documentation under `docs/`.

## Project identity

- System name: **Atlas**.
- Business purpose: support the company's debt collection operations.
- Permanent PHP root namespace: `App`.
- Regular user UI: Polish and English, Polish by default.
- Technical documentation, code, commits, technical errors, CLI output, and Admin UI: English.

## Required working mode

Before non-trivial work:

1. Read this file.
2. Read the lightweight [`WORKROAD.md`](WORKROAD.md) index.
3. Identify the current phase and the modules, shared mechanisms, and operational areas touched by the task.
4. Read only the relevant phase file or files under [`docs/roadmap/`](docs/roadmap/).
5. Read the affected canonical module documentation under [`docs/modules/`](docs/modules/).
6. Read related architecture, operations, and ADR documents only when the task touches them.
7. Inspect existing code and shared contracts before creating anything new.

Do not load the whole documentation tree “just in case.”

Do not create a second implementation of a capability already provided by Atlas.

### Discussion mode

When the user explicitly enters discussion mode:

- do not edit code, files, configuration, or infrastructure;
- discuss, evaluate, and plan only;
- resume changes only after explicit user instruction.

Do not ask questions whose answers already follow unambiguously from accepted contracts.

## Roadmap rules

- Follow roadmap order unless the user explicitly selects another scope.
- Continue from the first unfinished applicable item.
- Binding contracts and executable checkboxes live in `docs/roadmap/phase-*.md`.
- Never delete or rewrite completed checkboxes.
- Expand unfinished work when needed.
- Substantial later evolution of completed work receives a new sequential phase.
- Update `WORKROAD.md` only when phase status or phase inventory changes.
- Do not place detailed implementation contracts back into the index.
- Chat history must never be required to understand accepted behavior.

## Repository objective

Atlas must remain:

- modular;
- explicit;
- secure;
- testable;
- maintainable;
- auditable;
- operationally recoverable;
- suitable for continued development of large debt collection workflows.

Shared Core and technical modules must not absorb business rules owned by a debt collection `Application` module.

## Technology policy

Use the latest stable mutually compatible versions selected at implementation time.

Do not introduce replacements for approved technologies without an explicit architectural decision.

### Approved stack

Backend:

- Laravel;
- modern PHP;
- PostgreSQL;
- Redis;
- Horizon;
- Fortify backend with custom Inertia/Vue screens;
- Spatie Laravel Permission with teams;
- Laravel Scout and Meilisearch;
- Sentry;
- `owen-it/laravel-auditing`.

Frontend:

- Inertia;
- Vue 3;
- TypeScript;
- Vite;
- Tailwind CSS;
- TailAdmin;
- TanStack Table;
- Tabler Icons;
- Vitest;
- Playwright;
- pnpm.

Infrastructure and quality:

- Docker Compose;
- VS Code Dev Containers;
- PHPUnit;
- Larastan at maximum practical strictness;
- Pint;
- ESLint;
- Prettier;
- Stylelint;
- Lefthook.

## Engineering principles

### Explicit over magic

Prefer explicit registration, typed contracts, visible control flow, and predictable configuration.

Do not add hidden scanning, implicit cross-module discovery, broad service-location patterns, or convention-based behavior that makes ownership unclear.

### Framework boundaries

- Domain code is framework-independent pure PHP.
- Application code owns use cases, Commands, Queries, orchestration, and transaction boundaries.
- Infrastructure contains persistence and external adapters.
- Presentation contains delivery concerns such as HTTP, CLI, queues, and UI endpoints.
- Controllers and Form Requests remain thin.
- Console commands are classes owned by the appropriate module or shared infrastructure. Do not define console commands as closures in `routes/console.php`.
- Web routes are split into small files under `routes/web/` until module Presentation route registration is implemented. Do not recreate a monolithic `routes/web.php`.
- Breadcrumb definitions are split into small files under `routes/breadcrumbs/` until module breadcrumb registration is implemented. Do not recreate a monolithic `routes/breadcrumbs.php`.
- Eloquent models are persistence models, not domain entities.
- Business rules do not belong in controllers, requests, Eloquent models, policies, Filament/Admin components, Vue components, or infrastructure adapters.

Read [`docs/architecture/modular-monolith.md`](docs/architecture/modular-monolith.md) before changing module structure or shared architecture.

### Module boundaries

- A module owns its Domain, Application, Infrastructure, Presentation, database tables, permissions, settings, and events.
- Cross-module synchronous access uses only typed contracts exposed from `Application/Public`.
- Cross-module asynchronous communication uses versioned Integration Events.
- Never import another module's Domain internals, Eloquent models, repositories, or Infrastructure.
- Never query or mutate another module's tables directly.
- Avoid generic repositories and generic business abstractions.
- Prefer small capability-specific interfaces.

### Types and contracts

- Use `declare(strict_types=1);`.
- Avoid `mixed` unless an external boundary genuinely requires it.
- Use immutable DTOs and value objects at public boundaries.
- Use enums for closed sets.
- Use typed identifiers where identity mistakes are possible.
- Represent money as integer minor units plus ISO 4217 currency.
- Do not perform implicit mixed-currency operations.
- Public query results must not expose Eloquent collections or Laravel paginator types.

### Transactions, events, and side effects

- Define transaction boundaries in Application use cases.
- Persist business state and Outbox records atomically.
- Consumers must be idempotent.
- Jobs and integrations must tolerate at-least-once delivery.
- External side effects must not occur before the owning database transaction commits.
- Never hide partial failure.

### Persistence and migrations

- Design explicitly for PostgreSQL.
- Use real foreign keys and appropriate indexes.
- Default foreign-key behavior is `RESTRICT`; do not introduce cascading deletion casually.
- Use `BIGINT` internal identifiers and ULID public identifiers where resources are exposed.
- Use `Europe/Warsaw` for business time unless a documented contract says otherwise.
- Before the first production deployment, migrations may be edited in place.
- After production deployment, migrations are forward-only.
- Never edit an already deployed migration.

### Validation, authorization, and errors

- Validate structure at the request boundary.
- Validate business invariants in Domain/Application code.
- Authorize every protected operation on the backend.
- UI visibility is not authorization.
- Reject invalid or unauthorized state changes; do not silently repair them.
- Use meaningful typed exceptions and stable error mapping.
- Protect mutable workflows from stale writes and concurrency races.

Read affected canonical documents before changing authentication, authorization, teams, managers, Admin mode, impersonation, audit, or privacy behavior.

### Security

- Deny by default.
- Never log secrets, raw credentials, authentication tokens, full sensitive payloads, or unnecessary personal data.
- Redact sensitive context before logs and Sentry.
- Audit security-sensitive and irreversible operations.
- Use least privilege.
- Keep public and internal services explicitly separated.
- Do not weaken authentication, authorization, rate limits, malware scanning, module gates, or audit controls for convenience.
- Security bypasses require explicit user approval, a documented reason, and a safer replacement plan.

Read [`docs/architecture/security-baseline.md`](docs/architecture/security-baseline.md) and the affected module documentation for security-sensitive work.

## Frontend rules

- Use shared UI primitives before creating new local components.
- Maintain light and dark themes together.
- Meet WCAG 2.2 AA where applicable.
- Preserve keyboard navigation, focus management, semantics, and screen-reader behavior.
- Do not use native `alert`, `confirm`, or title-only tooltips.
- Use centralized forms, validation display, dialogs, toasts, formatters, and table wrappers.
- Keep business decisions on the backend.
- Do not duplicate backend permission logic in the client.
- Query-string state must be deterministic and shareable where applicable.
- Loading, empty, error, offline, and permission-denied states are first-class UI states.

Read [`docs/architecture/frontend-ui.md`](docs/architecture/frontend-ui.md) for shared UI changes and [`docs/architecture/tables-reports-exports-and-print.md`](docs/architecture/tables-reports-exports-and-print.md) for tables or reporting work.

## Testing and quality

Every change must be covered at the lowest effective test level and at higher levels where boundaries or user workflows require it.

Use:

- unit tests for pure logic;
- application tests for use cases and transactions;
- integration tests for persistence, queues, cache, search, files, and external adapters;
- feature tests for HTTP and authorization boundaries;
- Vitest for frontend logic and components;
- Playwright for critical end-to-end workflows;
- architecture tests for forbidden dependencies.

Tests must verify meaningful behavior, not implementation trivia.

Do not reduce strictness, skip failing checks, delete tests, or weaken assertions merely to make a task pass.

Run the relevant commands documented in [`docs/operations/quality-gates-and-git.md`](docs/operations/quality-gates-and-git.md).

## Documentation map

Read only the documents relevant to the task.

### Root files

- [`README.md`](README.md) — living high-level description of Atlas, major capabilities, setup entry points, and operational expectations.
- [`WORKROAD.md`](WORKROAD.md) — lightweight phase index.
- [`CHATGPT_PROMPT.md`](CHATGPT_PROMPT.md) — helper for the project owner during separate ChatGPT architecture and roadmap discussions.

Agents must not read `CHATGPT_PROMPT.md` during ordinary repository work, review, implementation, or documentation maintenance unless the user explicitly asks to inspect or edit that file.

### Roadmap

- [`docs/roadmap/`](docs/roadmap/) — binding implementation contracts, checkboxes, and historical evolution.
- Create new phases from [`docs/roadmap/_template.md`](docs/roadmap/_template.md).

### Modules

- [`docs/modules/README.md`](docs/modules/README.md) — module documentation index.
- Module documents are canonical for current public API, permissions, events, workflows, invariants, configuration, and administrative behavior.

### Architecture

- [`docs/architecture/README.md`](docs/architecture/README.md) — cross-module architecture index.
- Use architecture documents for shared mechanisms such as module boundaries, Outbox, ModuleGate, Admin mode, impersonation, audit, security, UI, reports, and data contracts.

### Operations

- [`docs/operations/README.md`](docs/operations/README.md) — development and runtime operations index.
- Use operations documents for Dev Containers, quality commands, deployment, backup, restore, recovery, observability, health, maintenance, realtime, and network behavior.

### Decisions

- [`docs/decisions/`](docs/decisions/) — one durable architectural decision per ADR.
- Link to an ADR instead of copying its full reasoning elsewhere.

## Documentation maintenance

Documentation is part of the implementation.

For every accepted change, update only the canonical layers genuinely affected:

- phase file for accepted scope and executable work;
- module documentation for current module behavior;
- architecture documentation for shared mechanisms;
- operations documentation for runtime procedures;
- ADR for durable architectural choices;
- root README for high-level system scope, major capabilities, setup, architecture, integrations, or operations;
- `AGENTS.md` only for permanent repository-wide working and coding rules.

Do not duplicate detailed behavior across layers.

Every new module, shared mechanism, or operational process must create or extend its canonical documentation.

When documentation grows too large, split it losslessly and retain a lightweight entry index.

`AGENTS.md` must not become a system specification.

## README maintenance

The root `README.md` is living documentation.

Update the English README whenever a task changes information a new developer, administrator, reviewer, or stakeholder should know, including:

- system purpose or scope;
- major modules and capabilities;
- supported debt collection workflows;
- setup and development entry points;
- environments and deployment expectations;
- major integrations;
- important operational procedures;
- significant architectural changes.

Do not copy detailed module contracts into the README. Link to canonical documentation.

## Git and commits

- Use English Conventional Commits.
- The agent must not create commits automatically.
- Before committing, the agent must present the final diff summary, quality commands run, and proposed Conventional Commit message for user review.
- The agent creates a commit only after explicit user approval for that exact reviewed change.
- The agent must not push commits unless the user explicitly approves the push after reviewing the committed state.
- Keep commits small, logical, reviewable, and buildable.
- Do not mix unrelated changes.
- Never rewrite published history without explicit instruction.
- Do not include secrets, generated private data, or local-only artifacts.
- Follow repository hooks and quality gates.
- Do not bypass hooks with `--no-verify`.

## Definition of Done

A task is complete only when all applicable conditions are satisfied:

- the requested behavior is implemented;
- relevant accepted contracts are respected;
- module and architecture boundaries remain valid;
- validation and backend authorization are complete;
- security, privacy, audit, and concurrency consequences are handled;
- database constraints and indexes are correct;
- failures are explicit and recoverable where required;
- relevant automated tests are added or updated;
- relevant quality commands pass;
- documentation is updated in its canonical location;
- English documentation remains current;
- the root README is updated when the change affects README-level knowledge;
- roadmap checkboxes and phase status are updated accurately;
- no completed historical item is deleted or rewritten;
- no secret or sensitive data is exposed;
- the change is committed as the smallest logical Conventional Commit.

If an applicable condition cannot be satisfied, report the exact blocker instead of silently weakening the result.

## Purpose and lifetime of this file

These rules apply during the initial technical foundation and throughout all later Atlas business-module development and maintenance.

Keep this file concise. Add only rules that apply broadly across repository work.

Detailed module behavior belongs in `docs/modules/`.

Cross-module mechanisms belong in `docs/architecture/`.

Operational procedures belong in `docs/operations/`.

Historical implementation contracts belong in `docs/roadmap/`.
