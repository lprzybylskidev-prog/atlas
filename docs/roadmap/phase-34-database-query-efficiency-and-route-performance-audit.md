# Phase 34 — Database Query Efficiency and Route Performance Audit

**Status:** `not started`

## Objective

Systematically exercise Atlas HTTP entry points under representative authorization and dataset conditions, identify and eliminate N+1 queries, duplicate database work, repeated request-scope lookups, and other avoidable PostgreSQL access, verify that important query paths scale appropriately, correct proven indexing and query-plan problems, and establish targeted regression protection before Final Verification.

This phase is a database/query-efficiency hardening phase, not a general application performance rewrite. The goal is not to chase an arbitrary universal query-count target.

The goals are:

- no known N+1 query behavior;
- no known unjustified duplicate queries;
- no avoidable repeated request-scope database work;
- query counts that remain bounded where operations should batch rather than scale per row;
- appropriate PostgreSQL indexing for demonstrated query patterns;
- targeted automated regression protection for important paths;
- documented evidence that the route surface was systematically audited.

## Dependencies

- [Phase 33 — Private production deployment, installer, backup, restore, and rollback](phase-33-deployment-backup-rollback.md) must be complete.
- All application foundations intended for the first base release must already be complete.
- This phase must complete before [Phase 35 — Final test audit, full-app E2E review, and foundation verification](phase-35-final-verification.md) begins.

Do not reopen previous completed phases merely because an optimization opportunity is found. If a true correctness defect or broken architectural invariant is discovered, fix the smallest required owning implementation and record the evidence, but do not opportunistically redesign unrelated foundations.

## Related documentation

- Architecture: [Modular-monolith architecture](../architecture/modular-monolith.md)
- Architecture: [Security baseline](../architecture/security-baseline.md)
- Architecture: [Tables, reports, exports, charts, and print](../architecture/tables-reports-exports-and-print.md)
- Operations: [Quality gates and Git](../operations/quality-gates-and-git.md)
- Operations: [Testing environment](../operations/testing-environment.md)
- Modules: [Module documentation index](../modules/README.md)

## Implementation contract

### Core principles

#### 1. Measure real request paths

Audit actual Atlas HTTP entry points and the application work they trigger. Do not optimize isolated repository methods without evidence that they contribute to real request inefficiency.

#### 2. No universal arbitrary query budget

Do not impose a universal rule such as `every route must execute <= 20 queries`. Different routes have different legitimate responsibilities.

Instead enforce:

- no known N+1 behavior;
- no unjustified duplicate queries;
- bounded query growth where batching is expected;
- targeted route-specific regression expectations where useful.

#### 3. Representative data is mandatory

A query pattern that looks acceptable with one row may become pathological with fifty or one hundred rows. Relevant list and detail scenarios must be exercised with representative dataset sizes.

Where useful, compare at least:

- a minimal or single-record dataset;
- a normal populated dataset;
- a full-page or materially larger dataset.

The purpose is to identify query-count growth characteristics, not to establish synthetic benchmark scores.

#### 4. Authorization context matters

Exercise significant routes under representative principals where authorization changes query behavior. At minimum where applicable, cover:

- a regular user;
- a manager or Team-scoped user;
- an Admin.

Do not assume a route has the same query behavior for every principal.

#### 5. Shared request plumbing is first-class scope

Repeated queries caused by shared request infrastructure can affect every screen. Audit shared request work such as:

- current-user loading;
- Authorization and effective permissions;
- role resolution;
- Team membership and hierarchy;
- module gates;
- Settings;
- feature flags;
- notification counters and shared shell state;
- Inertia shared frontend props;
- locale and user preferences;
- other globally executed request concerns.

The same logical state must not be repeatedly reloaded from PostgreSQL during one request without a justified reason.

#### 6. Caching is not the default fix

Do not introduce caching merely to make query counts look better. Prefer first:

- removing duplicate work;
- request-scope reuse;
- batching;
- eager loading where appropriate;
- `exists` instead of unnecessary record loads;
- selecting only required data where appropriate;
- correcting joins and subqueries;
- correcting ownership and authorization lookup patterns;
- appropriate PostgreSQL indexes.

Introduce persistent cache only when there is a proven use case, clear ownership, correct invalidation semantics, and no security or authorization staleness risk.

#### 7. PostgreSQL plans are evidence-driven

Do not run an indiscriminate index-adding campaign. Use `EXPLAIN` or `EXPLAIN ANALYZE` where a materially expensive or poorly scaling query has actually been identified.

Candidate areas include:

- large DataTables;
- filtering;
- sorting;
- permission-scoped lists;
- relationship joins;
- date and range queries;
- repeated existence checks;
- other demonstrated hot paths.

Add or change indexes only where supported by real query patterns and PostgreSQL evidence. Avoid redundant or speculative indexes.

### Audit surface

Systematically cover the HTTP surface that exists when this phase begins. This includes, where present:

- normal page loads and dashboard or shell requests;
- Admin routes;
- profile and settings routes;
- detail, create, and edit pages;
- DataTable, list, filtering, and sorting endpoints;
- mutations and authorization-sensitive endpoints;
- Files-related request paths;
- Search-related application request paths where PostgreSQL participates;
- Calendar, Chat, Calls, and Meetings;
- Diagnostics;
- Integrations;
- API `/api/v1` routes;
- System Status and Health-adjacent UI endpoints where database queries are involved;
- other enabled module HTTP entry points.

Do not treat every static route as equally expensive. The audit must nevertheless maintain a reproducible inventory showing which route families were reviewed and how coverage was established.

### Problems to identify

Explicitly detect and remediate where applicable:

- exact duplicate SQL queries within one request;
- semantic duplicate lookups implemented through different services;
- N+1 relationship loading and N+1 authorization lookups;
- repeated Team hierarchy or membership resolution;
- repeated role or permission calculation;
- repeated Settings, module-gate, or feature-state loading;
- repeated user lookups and shared-shell queries;
- unnecessary lazy loading or eager loading of large graphs;
- loading complete records where only `exists` is required;
- loading collections where aggregate, count, or existence queries are sufficient;
- unnecessary repeated `count` calls;
- inefficient per-row computations backed by database access;
- avoidable database queries for disabled or inapplicable features;
- per-item queries in serializers, resources, or presenters;
- inefficient pagination, filtering, or sorting queries;
- query growth proportional to result count where batching should be possible;
- missing indexes proven by actual query plans;
- redundant queries introduced by middleware plus controller or service duplication;
- mutation paths repeatedly reading the same authorization or domain state without need.

Do not reduce query count by breaking authorization correctness, stale-write protection, privacy boundaries, Audit semantics, or module ownership.

### Permanent guardrails

- Do not optimize by weakening Authorization.
- Do not bypass module ownership to reduce query count.
- Do not expose private data to avoid an authorization lookup.
- Do not replace correct live authorization state with unsafe persistent cache.
- Do not create a global repository or service locator for query reuse.
- Do not introduce generic utility abstractions solely to remove a few lines of code.
- Do not add speculative indexes.
- Do not introduce persistent caching without explicit invalidation semantics.
- Do not create arbitrary universal query budgets.
- Do not treat Telescope, Pulse, or development instrumentation as production runtime dependencies for normal requests.
- No known N+1 may remain without documented, unavoidable justification.
- No known unjustified duplicate query may remain without documented justification.
- Query optimizations must preserve existing business behavior.
- Query optimizations must preserve existing Audit, privacy, authorization, transaction, event, and stale-write semantics.

### Testing and acceptance

- Require backend tests appropriate to changed code and targeted regression tests for discovered classes of problems.
- Existing browser and E2E suites must continue to pass where HTTP behavior changes.
- Important scenarios must include representative authorization differences and populated datasets.
- Acceptance is based on evidence, not percentages or synthetic readiness scores.
- Do not implement CSS tuning, frontend bundle optimization, generic Redis tuning, PHP micro-optimization, infrastructure benchmarking, speculative caching, or unrelated performance work in this phase.

## Tasks

Workstreams are strictly sequential. Only the earliest incomplete workstream is active. Do not select work based on ease, speed, or file locality.

### P34-W01 — Query instrumentation, route inventory, and representative fixtures

- [ ] Define the canonical query-capture and instrumentation method used during this phase.
- [ ] Create or reuse deterministic representative fixtures.
- [ ] Establish the route-family inventory.
- [ ] Define representative principal contexts and dataset-size scenarios.
- [ ] Define how duplicate SQL and query-count growth are recorded.
- [ ] Ensure instrumentation is not shipped as unsafe production debugging behavior.
- [ ] Document the reproducible audit procedure and baseline evidence without arbitrary global query budgets; ad-hoc Telescope screenshots alone are insufficient.

### P34-W02 — Shared request, shell, identity, Authorization, and Team query audit

- [ ] Audit the authenticated principal, shared request context, Authorization, effective permissions, roles, Team membership and hierarchy, and Admin Mode where applicable.
- [ ] Audit Settings, module gates, feature flags, locale and preferences, shared shell and Inertia state, and notification or shared counters where applicable.
- [ ] Remove repeated request-scope queries where safe while preserving exact authorization semantics.

### P34-W03 — Core, Admin, Profile, and system route audit

- [ ] Audit enabled Core, Admin, profile, settings, system-management, privacy, security, Files, Search-associated, Integrations, and other platform route families not owned by later module-specific workstreams.
- [ ] Fix proven N+1 behavior, duplicate queries, and avoidable database work in those route families.

### P34-W04 — Module route audit

- [ ] Systematically audit all enabled module route families that exist at implementation time, including Calendar, Chat, Calls, Meetings, Diagnostics, Teams-specific interfaces not already fully covered, and other registered modules where present.
- [ ] Maintain evidence of route-family coverage and do not silently skip a module because it appears expensive to review.

### P34-W05 — DataTable, list, filtering, sorting, and scaling audit

- [ ] Exercise collection-heavy endpoints with representative populated datasets.
- [ ] Verify query growth does not become N+1 and per-row authorization does not generate uncontrolled SQL.
- [ ] Verify database-efficient filtering, sorting, pagination, counts, aggregates, and relationship loading.
- [ ] Verify full-page datasets do not multiply unrelated queries.
- [ ] Where useful, add regression assertions comparing small and larger datasets without fragile exact counts unless an exact count is a deliberate stable contract.

### P34-W06 — Mutation and authorization-path query audit

- [ ] Audit important POST, PUT, PATCH, DELETE, and action routes for repeated entity loads, database-backed permission checks, stale-write or version loads, relationship lookups, Team scope evaluation, equivalent existence checks, and unnecessary post-mutation reloads.
- [ ] Correct proven inefficiencies without weakening authorization, optimistic locking, validation, Audit, transaction boundaries, or event and Outbox semantics.

### P34-W07 — PostgreSQL query-plan and index corrections

- [ ] Review only material query problems discovered by prior workstreams with PostgreSQL query-plan evidence.
- [ ] Identify missing or ineffective indexes and redundant indexes where relevant.
- [ ] Verify composite index ordering against actual predicates and ordering.
- [ ] Ensure index changes follow existing migration and naming conventions and test affected filters, sorts, and joins.
- [ ] Document the evidence for material index changes and add no speculative index without an identified consumer and query.

### P34-W08 — Regression protection and complete route re-audit

- [ ] Rerun the route-family audit, representative principal scenarios, and populated dataset scenarios after fixes.
- [ ] Verify duplicate-query fixes, N+1 elimination, and bounded scaling expectations.
- [ ] Verify authorization and privacy behavior did not change.
- [ ] Add targeted automated regression tests for important stable query behavior, such as absence of a known duplicate pattern, bounded query growth, or no per-row query explosion.
- [ ] Use a route-specific upper bound only where stable and justified; do not create a brittle global query-count suite.
- [ ] Ensure test instrumentation creates no production overhead.

### P34-W09 — Documentation and acceptance closure

- [ ] Complete route-family coverage evidence.
- [ ] Summarize material duplicate and N+1 patterns removed.
- [ ] Document PostgreSQL index and query-plan changes with their rationale.
- [ ] Document any intentionally accepted query behavior with justification.
- [ ] Record the regression-test inventory.
- [ ] Add developer guidance for avoiding reintroduction of common query anti-patterns.
- [ ] Update architecture and testing guidance where shared request or database patterns were standardized.
- [ ] Complete the full intended route-family audit with no known actionable N+1 or unjustified duplicate-query issue left unresolved merely to declare the phase complete.
- [ ] Verify important query-growth regressions are protected where appropriate, proven index problems are addressed, existing functional, authorization, and privacy behavior is preserved, documentation is complete, and applicable repository quality gates are green.

## Completion criteria

- [ ] The full intended route-family audit is complete with reproducible coverage evidence.
- [ ] No known actionable N+1 behavior remains.
- [ ] No known unjustified duplicate-query issue remains.
- [ ] Avoidable repeated request-scope database work has been removed.
- [ ] Important query-growth regressions have targeted protection where appropriate.
- [ ] Proven PostgreSQL query-plan and indexing problems have been addressed without speculative indexes.
- [ ] Existing business, authorization, privacy, Audit, transaction, event, and stale-write behavior is preserved.
- [ ] Relevant tests and quality gates pass, and canonical documentation is current.
- [ ] `WORKROAD.md` status is updated to `complete`.
