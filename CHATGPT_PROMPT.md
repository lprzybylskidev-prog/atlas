# CHATGPT_PROMPT.md

## Role

You are working on **Atlas**, a large debt collection system developed for a debt collection company.

The current system includes a carefully designed shared technical foundation and will continue growing through debt collection business modules.

Your task is to design and implement Atlas capabilities while strictly respecting its architecture, shared infrastructure, module contracts, security model, UI system, quality gates, operational conventions, and documentation system.

Business functionality belongs primarily in:

```text
app/Modules/Application/*
```

Do not redesign, duplicate, bypass, or casually modify shared Atlas capabilities.

---

## Required Reading Order

Before any non-trivial work:

1. Read `AGENTS.md`.
2. Read `WORKROAD.md`.
3. Read this file.
4. Read relevant module documentation.
5. Read relevant ADRs.
6. Inspect existing Core and Optional Foundation modules.
7. Inspect shared UI components, contracts, services, and infrastructure.
8. Identify the first unfinished `WORKROAD.md` item unless the user explicitly points to another.

The files have distinct roles:

### `AGENTS.md`

Contains permanent repository rules, architectural principles, quality requirements, security requirements, workflow rules, and Definition of Done.

It is authoritative.

### `WORKROAD.md`

Contains:

- current execution order;
- binding implementation contracts;
- accepted business-module design decisions;
- explicit implementation steps;
- acceptance criteria.

`WORKROAD.md` is not merely a checklist.

Every `Implementation contract` is mandatory and defines how related checkbox items must be implemented.

Do not reduce a detailed implementation contract to the wording of a shorter checkbox.

### `CHATGPT_PROMPT.md`

Explains how to work on a real application built on the completed Atlas foundation.

---

## Source of Truth

Accepted decisions must be available in repository files.

Do not rely on old chat history as the only source of a requirement.

When the user accepts a new decision:

- permanent repository rules go to `AGENTS.md`;
- concrete implementation behavior and acceptance criteria go to `WORKROAD.md`;
- unresolved alternatives remain in the active discussion until accepted or rejected;
- architectural decisions may additionally require an ADR;
- module-specific behavior belongs in the relevant module documentation.

Do not leave accepted behavior only in the conversation.

Before closing a discussion topic, verify that all accepted consequences are represented in the appropriate files.

---

## Working Mode

Follow the workflow defined in `AGENTS.md`.

### Normal work mode

In normal work mode:

- implement the first unfinished roadmap item;
- follow the roadmap order;
- inspect existing code before creating new mechanisms;
- update code, tests, documentation, translations, and roadmap state;
- run required checks;
- review the diff;
- create a logical English Conventional Commit.

### Discussion mode

The user may explicitly enter discussion mode.

During discussion mode:

- do not modify code;
- do not modify files;
- do not change configuration;
- do not change infrastructure;
- only discuss, evaluate, and plan.

Discussion mode ends only when the user explicitly says to resume work.

When work resumes, continue from the first unfinished roadmap item unless the user selects another.

---

## Question Policy

Do not ask questions whose answers follow unambiguously from:

- accepted rules;
- module contracts;
- earlier decisions;
- architecture;
- established security policy;
- existing Atlas behavior.

Ask only when:

- there are at least two meaningful design options;
- the decision materially affects architecture;
- the decision materially affects security;
- the decision materially affects data integrity;
- the decision materially affects privacy;
- the decision materially affects UX;
- the existing files genuinely contradict one another;
- the requested behavior is technically impossible or unsafe.

Do not split one coherent topic into many trivial confirmation questions.

When an obvious consequence follows from accepted rules, apply it and record it without asking again.

---

## Primary Objective

Build Atlas as a coherent debt collection system.

Business modules may include, after explicit roadmap decisions:

- Portfolios
- Debtors
- Creditors
- Claims
- Cases
- Payments
- Settlements
- Contact history
- Documents
- Court proceedings
- Enforcement proceedings
- Tasks and workflows
- Reporting
- External debt collection integrations

Shared Core and optional technical modules must remain free from debt collection rules that belong to a specific `Application` module.

Place domain-specific rules in the correct debt collection module. Modify a shared capability only when:

1. the requirement is genuinely cross-module within Atlas;
2. existing extension contracts are insufficient;
3. the architectural impact is understood;
4. tests and migration consequences are defined;
5. the user explicitly approves the shared change.

Prefer implementing local business behavior in the owning `Application` module over weakening shared boundaries for convenience.

---

## Atlas Module Categories

Atlas uses three module categories.

### Core

Core modules are always present and cannot be disabled operationally.

Typical Core modules include:

- Identity
- Users
- Teams
- Authorization
- Audit
- Notifications
- Settings
- Files
- Admin
- Health

### Optional Foundation

Optional Foundation modules remain in the same repository but may be technically unavailable or operationally inactive.

Typical Optional Foundation modules include:

- TimeTracking
- Imports
- Search
- Integrations
- FeatureFlags

Reports/exports/print and realtime/WebSockets are shared capabilities by default, not modules unless a Atlas explicitly gives them an independent business boundary.

### Application

Application modules contain concrete business domains for the Atlas.

Business modules must use the existing foundation rather than recreating it.

---

## Module Activation

Optional modules have two separate states.

### Deployment availability

Defines whether the module is technically available in the deployed application.

Changing this state requires restart or deployment.

### Operational activation

Defines whether a technically available module is active:

- globally;
- for selected teams;
- from a scheduled future date.

Rules:

- Admin cannot activate a technically unavailable module;
- Core cannot be operationally disabled;
- backend enforcement is mandatory;
- hiding UI is not sufficient;
- disabled modules retain code, migrations, data, roles, permissions, and history;
- reactivation restores access to existing data;
- modules are never physically removed from the repository;
- required dependencies block deactivation;
- dependent modules are never disabled automatically;
- optional dependencies may enter a documented reduced mode;
- unsafe active processes block deactivation;
- every activation change requires permission, reason, impact preview, and audit.

### Activation and permissions

Module activation never grants permissions automatically.

Permissions remain registered and assignable while a module is inactive.

Backend checks:

1. module availability;
2. module operational activation;
3. active team;
4. effective permission;
5. additional business authorization.

Admin authorization screens must distinguish:

- assigned permission;
- effective permission;
- ineffective permission because the module is inactive;
- ineffective permission for another explicit reason.

---

## Permission Package Templates

A module may provide named permission-package templates such as:

- `viewer`
- `operator`
- `manager`
- `administrator`

These are templates only.

They are not automatically synchronized roles.

Rules:

- the module explicitly declares which permissions belong to each template;
- Admin may use a template to create a role;
- Admin may use a template to add missing permissions to an existing role;
- applying a template requires an exact diff preview;
- applying a template requires explicit confirmation;
- applying a template is audited;
- later template changes never automatically modify existing roles;
- Admin can compare a role with the current version of a template;
- Core may provide useful starter templates;
- Core must not impose one organizational role model.

Never automatically create or mutate roles merely because a module was activated.

---

## Architecture

Every business module uses:

```text
app/Modules/Application/<Module>/
  Domain/
  Application/
  Infrastructure/
  Presentation/
```

### Domain

Domain contains pure PHP only.

It may contain:

- aggregates;
- entities;
- value objects;
- domain services;
- domain events;
- repository interfaces;
- domain exceptions;
- typed domain IDs.

Domain must not depend on:

- Laravel;
- Eloquent;
- Redis;
- queues;
- HTTP;
- filesystem;
- external APIs;
- Inertia;
- Vue.

### Application

Application contains:

- Commands;
- Queries;
- Handlers;
- immutable DTOs;
- use-case orchestration;
- transaction coordination;
- public module contracts;
- authorization coordination where needed.

Application must not contain UI logic.

Application must not contain persistence implementations.

Application must not duplicate invariants belonging to Domain.

### Infrastructure

Infrastructure contains:

- Eloquent persistence models;
- repository implementations;
- mappers;
- Redis implementations;
- queue jobs;
- search adapters;
- filesystem adapters;
- external API adapters;
- integration clients.

### Presentation

Presentation contains:

- controllers;
- Form Requests;
- API Resources;
- routes;
- console commands;
- Inertia entrypoints.

Controllers remain thin.

Form Requests perform validation and authorization only.

Form Requests must not:

- construct domain aggregates;
- contain business processes;
- mutate persistence;
- coordinate use cases.

---

## Domain Modeling

Use aggregates to enforce invariants.

Entities expose intent methods rather than public setters.

Avoid anemic domain models.

Use immutable Value Objects when rules exist, including where appropriate:

- IDs;
- money;
- email addresses;
- date ranges;
- percentages;
- statuses;
- external identifiers.

Relations between aggregates use typed IDs.

Do not pass Eloquent models into Domain or Application contracts.

Repository interfaces live in Domain.

Repository implementations live in Infrastructure.

Generic CRUD `BaseRepository` abstractions are forbidden.

Writes use aggregate repositories.

Reads use Query Handlers and read models.

---

## CQRS and Events

Use application-level CQRS.

### Commands

Commands mutate state.

Commands contain data only.

Commands do not contain business logic.

### Queries

Queries read state.

Queries return read DTOs, views, or paginated read models.

Queries must not expose Eloquent models.

### Events

Separate:

- Domain Events;
- Integration Events.

Event names use past tense.

Domain Events originate from Domain behavior.

Integration Events cross module or external boundaries.

Do not hide obvious sequential application flow behind unnecessary events.

External side effects occur after database commit.

Use the Outbox Pattern where reliable delivery is required.

Do not introduce event sourcing or a separate read database unless the user explicitly approves a concrete domain justification.

---

## Transactions and Concurrency

One use case owns one transaction.

Application opens and coordinates transactions.

Aggregate persistence and event persistence must be atomic where required.

External effects happen after commit.

Keep transactions short.

Use concurrency tools where justified:

- PostgreSQL constraints;
- unique constraints;
- Redis locks;
- database locks;
- optimistic locking;
- idempotency keys.

Do not silently overwrite concurrent changes.

Use a `version` column only where real concurrent editing risk exists.

Return an explicit conflict response when the client works on stale data.

Financial and legally important operations require stricter conflict handling.

---

## Module Boundaries

A module must not:

- access another module's Eloquent models;
- query another module's tables directly;
- depend on another module's Infrastructure;
- instantiate another module's internal classes;
- bypass another module's public API.

Allowed communication:

- typed public application contracts;
- Integration Events;
- stable typed identifiers.

Module dependencies must remain acyclic.

Shared code contains only truly reusable domain-neutral concepts.

Do not create a generic helper dump.

---

## Use Existing Atlas Capabilities

Before creating any new cross-cutting mechanism, inspect whether Atlas already provides it.

Reuse existing systems for:

- authentication;
- password policy;
- MFA;
- sessions;
- active team;
- authorization;
- roles;
- permissions;
- manager hierarchy;
- Admin mode;
- impersonation;
- audit;
- security audit;
- notifications;
- settings;
- module activation;
- feature flags;
- files;
- uploads;
- antivirus/quarantine;
- imports;
- reports;
- saved views;
- CSV;
- XLSX;
- PDF;
- browser print;
- tables;
- forms;
- modals;
- confirmations;
- alerts;
- tooltips;
- formatters;
- charts;
- WebSockets;
- queues;
- health checks;
- search;
- integrations;
- rate limiting;
- correlation IDs;
- Sentry;
- backup;
- deployment.

Do not implement a second local version inside a business module.

---

## Identity and Identifiers

Use:

- internal BIGINT primary keys;
- public ULID `public_id`;
- typed domain IDs;
- explicit external identifier mappings.

Public URLs, APIs, logs, and external references use ULIDs rather than internal numeric IDs.

For external systems, prefer:

- source plus external identifier;
- or a dedicated mapping table.

Do not use one ambiguous `outer_id` when multiple external systems may identify the same entity.

---

## Database Rules

Use PostgreSQL only.

All schema changes use migrations.

Foreign keys use `RESTRICT`.

Cascade delete is forbidden.

Use precise data types.

Enforce uniqueness in the database.

Design indexes from real query patterns.

When query patterns or data volume change, review indexes.

Use `EXPLAIN ANALYZE` where justified.

Queries must:

- select only required columns;
- paginate potentially large results;
- avoid full-table loading;
- use chunking, cursors, or queues for large operations;
- prevent N+1;
- use explicit eager loading.

Raw SQL is allowed when clearer, safer, or faster, but must be tested.

---

## Money

Store and transport money as integer minor units.

Use INTEGER or BIGINT.

Never use:

- float;
- double;
- decimal/numeric as the normal application-money representation.

Example:

```text
1250
```

represents:

```text
12,50 zł
```

Use the shared formatter.

Do not divide or format money manually inside arbitrary components.

---

## Dates and Timezone

Use the centrally configured application timezone, defaulting to:

```text
Europe/Warsaw
```

Technical timestamps may be stored in UTC.

Business calendar calculations use the configured application timezone.

Transport date/time values as ISO with timezone, for example:

```text
2026-07-13T14:30:00+02:00
```

Presentation is localized through shared formatters.

Do not hardcode timezone strings inside business modules.

---

## Routes and Permissions

Every protected route:

- has an English name;
- belongs to the module Presentation layer;
- uses module/resource/action naming;
- uses public ULIDs;
- has a permission equal to its route name.

Document explicit exceptions for public or purely technical routes.

Add business permissions when route access alone is insufficient.

Never check role names in business code.

Use named routes.

Do not manually construct URLs.

Use REST naming where suitable.

Use explicit business verbs for custom actions.

Do not use vague action names such as:

- `process`;
- `handle`;
- `do-action`;
- `execute-action`.

Routes drive:

- permissions;
- breadcrumbs;
- menu;
- Ziggy exposure.

Expose only required frontend routes through Ziggy.

---

## Teams and Managers

Every team-scoped operation uses the active team.

A user may belong to multiple teams but has one active team per session.

Manager relationships are team-scoped.

Atlas supports:

- multiple direct managers;
- managers supervising managers;
- head managers;
- hierarchy history;
- validity periods;
- subtree access;
- cycle prevention;
- self-management prevention.

Business modules must reuse the central manager hierarchy.

Do not implement module-specific manager tables unless the domain requires a genuinely different relationship and the user approves it.

A normal manager sees direct reports.

A head manager sees their authorized subtree.

Permissions still apply.

---

## Authentication and Sessions

Use existing foundation behavior.

Do not create custom authentication inside a business module.

Respect:

- account activation;
- password history;
- breached-password checks;
- MFA;
- session maximum lifetime;
- inactivity timeout;
- active sessions;
- active team;
- session invalidation;
- suspicious-login handling;
- rate limiting.

When a business operation is high-risk, use the shared reauthentication/MFA mechanism.

---

## Admin and Impersonation

Admin uses ordinary user accounts with explicit administrative mode.

There is no hidden superadmin bypass.

Admin has full functional permissions but still passes through:

- module activation;
- use cases;
- validation;
- domain invariants;
- authorization;
- confirmations;
- audit.

### Impersonation

During impersonation:

- the application behaves as for the selected user;
- the same teams, permissions, managers, menu, modules, and limits apply;
- business actions are real and production-effective;
- audit records the real administrator and impersonated user context;
- external-effect actions show an additional warning;
- the real user's session is not interrupted;
- a persistent impersonation banner is mandatory.

Do not allow during impersonation:

- password changes;
- MFA changes/reset;
- email changes;
- session management;
- role changes;
- permission changes;
- team changes;
- account deactivation;
- account deletion;
- nested impersonation;
- Admin-panel access as the impersonated user.

TimeTracking simulation during impersonation must remain isolated from official reports and live employee state.

Business modules must include actual actor and impersonated context in audit where applicable.

---

## Audit

Evaluate every meaningful business operation for audit.

Use the shared audit contracts.

Store where relevant:

- actor;
- actual actor during impersonation;
- impersonated/context user;
- aggregate or target;
- active team;
- module;
- source;
- correlation ID;
- action;
- result;
- meaningful before/after;
- reason.

Audit is append-only.

Do not log every irrelevant persistence update.

Do not store:

- passwords;
- tokens;
- secrets;
- unnecessary sensitive data.

Use security audit for:

- authentication;
- MFA;
- sessions;
- impersonation;
- authorization;
- rate limiting;
- suspicious activity.

---

## UI Implementation

Use the existing TailAdmin-based frontend foundation.

Implementation order:

1. existing shared Atlas component;
2. TailAdmin component or pattern;
3. Tailwind utilities;
4. custom CSS only as a last resort.

Custom CSS should be minimal.

Do not create duplicate:

- table wrappers;
- form components;
- tooltips;
- modal systems;
- confirmation systems;
- alerts;
- uploaders;
- charts;
- formatters;
- timers;
- validation components.

Avoid giant components.

Extract reusable stable cores and focused variants.

---

## TailAdmin Pro

Before first use of any paid TailAdmin Pro:

- component;
- chart;
- asset;
- template;
- code fragment;

check whether license confirmation is already recorded.

When it is not recorded:

1. stop before using the paid asset;
2. inform the user that a TailAdmin Pro purchase is required;
3. continue only after explicit confirmation;
4. record confirmation for the repository.

Do not ask again after confirmation.

Use TailAdmin Pro charts before adding another chart library.

An additional chart library requires:

- a concrete unsupported requirement;
- analysis of maintenance and license implications;
- explicit discussion.

---

## Light and Dark Themes

Every new screen and component must support light and dark themes simultaneously.

Do not implement one theme first and postpone the other.

Verify both themes for:

- normal state;
- hover;
- focus;
- active;
- disabled;
- loading;
- empty;
- success;
- info;
- warning;
- error;
- forms;
- tables;
- dialogs;
- charts;
- notifications;
- reports;
- print-related preview where applicable.

A component is not complete until both themes are correct.

---

## Accessibility

Target WCAG 2.2 AA.

Require:

- keyboard operation;
- visible focus;
- semantic controls;
- accessible names;
- screen-reader-compatible errors;
- correct modal focus handling;
- sufficient contrast;
- no color-only meaning;
- accessible table interactions;
- accessible chart summaries where possible.

Use custom accessible tooltips.

Native `title` attributes are forbidden.

Icons cannot be the only explanation for unclear actions.

---

## Forms

Use shared form components.

Use `novalidate`.

Frontend validation improves UX only.

Backend remains authoritative.

Shared behavior includes:

- backend field errors;
- loading state;
- disabled state;
- success/error state;
- double-submit prevention;
- common reset behavior;
- unsaved-change warning;
- permission-aware actions;
- money conversion;
- date/time formatting.

Do not duplicate domain invariants in Vue or Form Requests.

---

## Modals, Confirmations, and Alerts

Use the shared modal and confirmation system.

Do not use:

- `window.alert`;
- `window.confirm`.

Destructive actions must show:

- exact target;
- exact impact;
- irreversibility;
- affected count;
- stronger typed confirmation for dangerous operations.

Use the shared alert/toast system.

Do not create local notification systems.

Backend and frontend messages use the standardized Inertia contract and translation keys.

---

## Tables

Every business table uses the shared TanStack Table wrapper.

Required behavior:

- server-side pagination;
- server-side sorting;
- server-side filtering;
- backend allowlists;
- URL synchronization;
- column visibility;
- column ordering;
- selection;
- loading;
- empty;
- error;
- no-results;
- saved views;
- exports;
- print.

Do not load entire large datasets into browser memory.

Query parameters use stable English names.

Do not put sensitive values in URLs.

---

## Saved Views

Use shared saved views.

Supported types:

- private;
- team-shared;
- system.

Views may store:

- filters;
- sorting;
- visible columns;
- column order;
- grouping;
- time-range configuration.

Views store configuration only, never business rows.

System views:

- cannot be deleted;
- cannot be overwritten;
- may be copied.

Sharing requires permission.

Shared-view changes are audited.

---

## Reports, Exports, PDF, and Print

Use shared reporting infrastructure.

Supported formats from the beginning:

- CSV;
- XLSX;
- PDF;
- browser print.

Exports and print must respect:

- filters;
- sorting;
- active time range;
- visible columns;
- active team;
- effective permissions.

Backend revalidates all requested data.

Small exports may run synchronously.

Large exports run in queues.

Users receive notifications when queued exports are ready.

Generated artifacts:

- are private;
- have expiry;
- are cleaned automatically.

Report headers include:

- report name;
- active team;
- filters;
- range;
- generation timestamp;
- generating user;
- totals.

PDF and print include page numbers.

Company identity, logo, and footer come from shared configuration.

Do not implement one-off module export frameworks.

---

## Charts

Use shared TailAdmin Pro chart wrappers.

Charts:

- use the same query/filter contract as the table;
- supplement tables;
- do not replace auditable data;
- may appear in PDF and print;
- aggregate large ranges appropriately;
- must provide real analytical value.

Do not add decorative charts without a clear purpose.

---

## Files

Use the shared Files module.

Do not store uploads directly in arbitrary business directories.

Use:

- private storage;
- generated physical names;
- original-name metadata;
- MIME validation;
- extension validation;
- size validation;
- content validation;
- checksum;
- antivirus scan;
- quarantine;
- authorized download use cases;
- audit;
- retention;
- anonymization integration.

Business modules store file references through approved contracts.

---

## Imports

Use the shared import pipeline:

```text
source adapter
-> parsing
-> normalization
-> typed input DTO
-> validation
-> deduplication/idempotency
-> domain use cases
-> audit and error reporting
```

An import must not bypass:

- authorization;
- application use cases;
- domain invariants;
- database constraints;
- audit.

Use queues for large imports.

Preserve row and field errors.

Do not create one-off import logic inside controllers.

---

## Search

Use the Search module and Meilisearch only when justified by actual large full-text search.

Use PostgreSQL for:

- ordinary filters;
- standard tables;
- reports;
- small selectors;
- precise relational conditions.

Search results must respect:

- active team;
- permissions;
- module activation.

Indexes are derived and rebuildable.

Deletion and anonymization must update search indexes.

---

## Integrations

External systems use typed contracts and Infrastructure adapters.

For every integration define:

- source of truth;
- external identifiers;
- mapping strategy;
- idempotency;
- retry rules;
- timeout;
- circuit breaker;
- error mapping;
- audit;
- correlation IDs;
- secret handling;
- synchronization history.

Do not expose Eloquent models through APIs.

Use DTOs and Resources.

Mutations support idempotency keys where repeat delivery is possible.

---

## Notifications

Use the shared Notifications module.

Notifications are typed.

Use:

- in-app;
- email;
- user preferences;
- type preferences;
- channel preferences;
- queued delivery;
- read state;
- deep links;
- retention.

Do not put unnecessary sensitive information into email.

Do not build module-local notification tables or delivery systems.

---

## Realtime

Use WebSockets only for real server push:

- notifications;
- progress;
- session invalidation;
- live status;
- system alerts;
- justified collaborative updates.

Use HTTP/Inertia for:

- forms;
- CRUD;
- filters;
- pagination;
- searches initiated by user;
- ordinary actions.

Do not use WebSockets merely because they are available.

---

## Validation

Use layered validation.

### Presentation

Validates:

- shape;
- format;
- file constraints;
- simple cross-field relationships.

### Application

Validates:

- existence;
- process conditions;
- authorization coordination;
- duplicate process state.

### Domain

Validates:

- invariants;
- legal transitions;
- Value Object rules.

### Database

Prevents:

- race-condition duplicates;
- invalid foreign references;
- uniqueness violations.

Do not duplicate complex business invariants in Form Requests or Vue.

---

## Exceptions and Error Handling

Use concrete exceptions.

Do not throw generic `Exception` for expected business or technical conditions.

Technical messages are English.

User-visible errors use translation keys.

Map exceptions centrally to:

- HTTP status;
- safe UI message;
- logging;
- retry behavior.

Do not silently catch failures.

Catch only to:

- handle;
- map;
- log;
- rethrow.

Catch `Throwable` only at boundaries.

Retry transient failures only.

---

## Deletion and Anonymization

Use the shared deletion framework.

Distinguish:

- soft delete;
- hard delete;
- irreversible anonymization.

Hard delete requires:

- dedicated use case;
- permission;
- reauthentication;
- confirmation;
- reason;
- impact preview;
- audit;
- dry run for mass operations.

Do not cascade delete.

Anonymization must cover controlled copies, including:

- business tables;
- related tables;
- files;
- search indexes;
- cache;
- queues;
- read models;
- exports;
- controlled logs/audit where legally permitted.

Respect legal retention.

---

## Security

Apply least privilege.

Treat all input as untrusted.

Use:

- CSRF;
- secure headers;
- mass-assignment protection;
- explicit fields;
- rate limits;
- reauthentication;
- MFA;
- secret redaction;
- dependency vulnerability checks;
- encryption where justified.

Do not use:

- `eval`;
- unsafe deserialization;
- production stack traces;
- secrets in logs;
- secrets in audit;
- authorization based only on UI visibility.

---

## Documentation

Every business module must include English documentation describing:

- purpose;
- boundaries;
- aggregates;
- public API;
- dependencies;
- data ownership;
- permissions;
- routes;
- events;
- integrations;
- reports;
- module activation behavior;
- team scope;
- manager scope;
- deletion;
- anonymization;
- operational concerns.

Create ADRs for significant decisions.

Comments explain why, not what.

Do not keep commented-out code.

PHPDoc is used only where it adds information such as:

- generics;
- array shapes;
- complex contracts;
- throws;
- non-obvious type constraints.

---

## Testing

Every change requires appropriate tests.

Use:

- unit tests for Domain;
- integration tests for repositories and infrastructure;
- feature tests for use cases and HTTP behavior;
- Vitest for frontend logic/components;
- Playwright for critical user flows;
- architecture tests for module boundaries;
- authorization regression tests;
- light/dark verification.

Test:

- success paths;
- authorization denial;
- inactive module behavior;
- invalid team context;
- validation;
- concurrency where relevant;
- audit;
- impersonation actor context;
- queue idempotency;
- failure handling.

Do not test only happy paths.

---

## Quality Commands

Use project commands defined by Atlas.

Before marking work complete, run relevant parts of:

```text
composer check
pnpm check
pnpm build
pnpm test:e2e
```

Run targeted tests during development.

Run full required checks before completion.

Do not claim completion when required verification was skipped.

State exactly what remains unverified.

---

## Git

Use English Conventional Commits.

Examples:

```text
feat(cases): add case assignment workflow
fix(documents): prevent unauthorized download
refactor(orders): extract order status transition policy
test(claims): cover concurrent settlement creation
docs(contracts): document contract termination flow
```

Create small logical commits.

Before every commit:

- inspect diff;
- remove accidental changes;
- check module boundaries;
- check security;
- check query performance;
- check tests;
- check translations;
- check documentation;
- check light/dark;
- remove debug output;
- remove temporary files;
- remove dead code.

The agent creates commits itself.

---

## Definition of Done

A business task is complete only when:

- the implementation matches the relevant `WORKROAD.md` implementation contract;
- module boundaries are respected;
- existing shared infrastructure is reused;
- Domain invariants are correctly placed;
- authorization works for active team;
- inactive-module behavior is correct;
- permissions are correct;
- manager scope is correct where applicable;
- impersonation audit records actual actor correctly;
- tests are added or updated;
- required checks pass;
- frontend build passes;
- critical E2E tests pass;
- PL/EN translations are complete;
- light and dark themes are verified;
- documentation is updated;
- migrations, constraints, and indexes are correct;
- audit is implemented where required;
- security implications are handled;
- no debug, temporary, or dead code remains;
- diff is reviewed;
- a logical Conventional Commit is created;
- `WORKROAD.md` checkbox state is updated only after completion.

Do not mark an item complete based only on implementation existing.

---

## How to Approach a New Business Module

Before implementation:

1. describe the business problem;
2. identify actors;
3. identify active-team scope;
4. identify manager scope;
5. identify existing Atlas modules to reuse;
6. define aggregate boundaries;
7. define invariants;
8. define Commands;
9. define Queries;
10. define permissions;
11. define routes;
12. define audit events;
13. define notifications;
14. define files/imports/search/integrations;
15. define reports and exports;
16. define deletion/anonymization;
17. define module activation behavior;
18. identify only genuinely unresolved decisions;
19. record accepted decisions in `WORKROAD.md`;
20. implement after discussion mode ends.

Do not start from database tables or CRUD screens.

Start from business behavior and invariants.

---

## Final Principle

The objective is not merely to produce code that works.

The objective is to produce a business module that:

- behaves as a native part of Atlas;
- reuses its systems;
- respects its architecture;
- remains secure;
- remains auditable;
- remains testable;
- remains maintainable;
- works in both themes and languages;
- can be operated safely in production;
- does not require future developers to rediscover decisions already made.


## Purpose of This Prompt

`CHATGPT_PROMPT.md` is a persistent helper prompt for future conceptual work throughout Atlas development.

The user will provide this file to ChatGPT together with:

- `AGENTS.md`;
- the lightweight `WORKROAD.md` index;
- the relevant linked roadmap, module, ADR, architecture, and operations documents for the work being discussed.

Use all three files jointly to:

- understand the permanent engineering rules;
- understand the current system and its evolution from the relevant linked documents;
- design further modules and capabilities for the Atlas;
- update the relevant phase file with every accepted decision;
- update the `WORKROAD.md` index only when a phase is added or its status changes;
- update canonical module/architecture/operations documentation when current behavior changes;
- preserve compatibility with the existing architecture and implementation contracts.

This file is not a continuation prompt for building only the initial technical foundation. It remains useful throughout the later life of Atlas.

## Environment Scope Clarification

- The Dev Container no-rebuild rule applies only to the development Dev Container after its first successful startup.
- It does not apply to production containers or production images.
- Production containers are rebuilt normally when code, dependencies, configuration, base images, or security updates require it.
- PostgreSQL is part of the production Docker Compose stack under project control and uses durable persistent storage.

## Review-Resolved Architecture Clarifications

Apply these decisions when designing future modules:

- PostgreSQL runs in the production Docker Compose stack with durable storage.
- The Dev Container no-rebuild rule applies only to the development Dev Container, never to production images or containers.
- Reliable Integration Events use the shared transactional Outbox; consumers are idempotent.
- `ModuleGate` is the central source of truth for deployment availability, dependencies, activation, active team, and permissions.
- Required missing module dependencies invalidate startup/readiness.
- Modules with unsafe in-flight work expose typed deactivation guards.
- Public Query contracts use framework-independent DTO collections/page results, never Laravel paginator or Eloquent collection types.
- Spatie Permission is Authorization Infrastructure; Domain and foreign modules never import it.
- Money contains integer minor units plus ISO 4217 currency; default currency is configurable and initially PLN.
- Reports/exports/print and realtime/WebSockets are shared capabilities by default, not automatically optional modules.
- Meilisearch is degraded by default unless explicitly critical; ClamAV is blocking when Files are active.
- `/api/v1` is not automatically public. External API authentication is introduced only for an accepted integration use case.
- Sensitive-account classification is explicit and independent of roles. Administrator targets are evaluated globally across teams and cannot be impersonated.
- TimeTracking simulation during impersonation is ephemeral and never reaches official records, events, settlements, manager feeds, or reports.
- Work, breaks, and other-work may cross midnight logically; reports split exact time by the configured business day.
- Offline TimeTracking durations use monotonic client time and backend-authoritative reconciliation.
- Closed-period correction has a dedicated, high-risk audited Admin fallback when no eligible head manager exists.
- The minimal deletion/anonymization participation contract exists before Files/Search; the later privacy phase provides full orchestration.

## Documentation Context Selection

Do not request or load the entire documentation tree by default.

For conceptual work:

1. read `AGENTS.md`;
2. read the `WORKROAD.md` index;
3. identify the phase or new initiative affected;
4. read the relevant `docs/roadmap/phase-*.md`;
5. read documentation for touched modules;
6. read only linked ADRs and shared architecture/operations documents.

When a decision is accepted:

- record implementation scope and tasks in the active or new phase file;
- add the next phase to `WORKROAD.md` when required;
- update current-state module documentation;
- write or supersede an ADR for a durable architectural choice;
- do not place detailed contracts back into the roadmap index.

Chat history must not be required after those files are updated.


## Atlas Identity

- The system is named **Atlas**.
- Atlas is a debt collection system developed for the company.
- Atlas keeps the permanent PHP root namespace `App`.
- All new modules and capabilities are developed as part of the same system and repository.
