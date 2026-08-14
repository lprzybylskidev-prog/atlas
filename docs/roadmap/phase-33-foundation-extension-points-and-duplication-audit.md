# Phase 33 — Foundation extension-point, duplication, and consumer audit

**Status:** `not started`

## Objective

Perform one finite, evidence-driven audit of the completed Atlas foundation before additional reusable platform capabilities are implemented.

The audit establishes what Atlas already provides, where existing capabilities should be reused or hardened, where duplicate mechanisms exist, where public extension contracts are missing or overbroad, and which accepted post-Phase-31 foundation capabilities genuinely require implementation.

This is not an open-ended architecture review. It must produce a decisive first-base-release foundation backlog and freeze it so production deployment is not postponed indefinitely by later nice-to-have ideas.

## Dependencies

- [Phase 31 — Optional internal company chat, calendar, calls, meetings, and realtime communication](phase-31-chat.md) must be complete.
- [Phase 32 — Error reporting, user bug reports, and application diagnostics](phase-32-error-reporting-and-diagnostics.md) must be complete.
- [Modular-monolith architecture](../architecture/modular-monolith.md)
- [Module documentation index](../modules/README.md)
- All completed shared Core and Optional Foundation capabilities and their current public-contract conventions.

Phase 33 must complete before Phases 34 through 39 begin.

## Related documentation

- Architecture: [Modular-monolith architecture](../architecture/modular-monolith.md)
- Architecture: [Data contracts, validation, and concurrency](../architecture/data-contracts-validation-and-concurrency.md)
- Architecture: [Tables, reports, exports, charts, and print](../architecture/tables-reports-exports-and-print.md)
- Modules: [Module documentation index](../modules/README.md)
- Operations: [Quality gates and Git](../operations/quality-gates-and-git.md)
- Operations: [Testing environment](../operations/testing-environment.md)

## Implementation contract

### Audit coverage

Audit how existing and future Atlas modules use at least:

- Authorization, Teams, and active-Team context;
- Audit, Settings, Localization, Feature Flags, Health/System Status, and ModuleGate;
- Files, Search, Notifications, Calendar, and Integrations;
- Integration Events and Outbox;
- exports, reports, print, Managed Processes, queues, and scheduler;
- shared frontend UI, DataTables, and saved views;
- public module contracts, providers, routing, navigation, and breadcrumb contributions;
- concurrency, effective-dated, provenance/source, and cross-module reference patterns.

For every audited candidate or problem, record exactly one disposition:

1. `exists / no change`;
2. `existing capability needs hardening`;
3. `implement in an already planned foundation phase`;
4. `defer until a real consumer exists`;
5. `reject`;
6. `architecture/product decision required`.

Evidence must identify, where applicable, actual code, canonical documentation, real consumers, duplicated implementations, dependency direction, public/internal boundary usage, current tests, browser behavior, and the concrete missing capability. Do not call something missing merely because a different abstraction name could be invented, or reusable merely because one module contains similar code.

### Public-boundary audit

Verify that synchronous cross-module communication uses provider-owned `Application/Public` contracts or another explicitly documented public API, and asynchronous cross-module/system communication uses Integration Events where appropriate.

Identify direct cross-module Eloquent or foreign-table access, foreign Infrastructure dependencies, consumer-owned pseudo-contracts, internal class leakage, service-locator-like access, and duplicate DTOs/contracts describing the same provider capability. Do not implement broad replacement abstractions during the audit.

### Duplication audit

Look specifically for repeated stale-write/version handling, reason capture, effective-date/range math, provenance/source metadata, object label/link resolution, settings/config lookup, localization/catalog representation, route/navigation/breadcrumb registration, record/object mapping helpers, Integration Event serialization, retry/idempotency helpers, and other genuinely shared responsibilities.

Do not extract an abstraction merely to remove a few repeated lines. Recommend consolidation only when semantic ownership is genuinely shared.

### First-release backlog freeze

At completion:

- every accepted post-Phase-31 foundation item has an owning Phase 34-39 or an explicit defer/reject outcome;
- required hardening discovered here is added to an already planned Phase 34-39 where it fits the accepted scope;
- no new phase is created for each finding;
- only a genuinely incompatible architecture decision requires a new user decision;
- later nice-to-have foundation ideas do not automatically become deployment blockers.

Reusable generic Approvals are intentionally excluded from the first-base-release foundation. Future business workflow and approval semantics belong to real Application business modules. Do not add generic approval records, an approval engine, BPMN, a workflow/process designer, a business-rules engine, or low-code automation. Shared/Core foundations provide narrow reusable technical and business-support tools; Application modules own business workflow and domain behavior.

## Tasks

Workstreams are strictly sequential. Only the earliest incomplete workstream is active.

### P33-W01 — Audit method, inventory, and evidence format

- [ ] Define finite audit boundaries, inventory sources, evidence fields, and the six allowed dispositions.
- [ ] Inventory the completed foundation, module manifests/providers, public contracts, consumers, and existing architecture guards.
- [ ] Create durable audit documentation and a dependency map without turning the audit into implementation work.

### P33-W02 — Core shared capabilities and public extension contracts

- [ ] Audit every shared capability listed in the implementation contract and its supported extension path.
- [ ] Record real consumers, missing consumers, tests, documentation, and ownership for each capability.
- [ ] Identify missing, overbroad, duplicated, or consumer-owned public contracts with evidence.

### P33-W03 — Module consumers, duplicate mechanisms, and internal-boundary leaks

- [ ] Inspect module consumers for forbidden imports, foreign persistence access, internal leakage, and service-location patterns.
- [ ] Inventory semantically duplicated mechanisms without extracting speculative abstractions.
- [ ] Map each confirmed leak or duplicate to its owning module or accepted later phase.

### P33-W04 — Cross-cutting primitive and pattern audit

- [ ] Audit concurrency, Change Reason, effective ranges, provenance, safe-reference, localized catalog, routing contribution, Integration Event, retry, and idempotency patterns.
- [ ] Distinguish genuinely shared semantics from superficially similar module-owned behavior.
- [ ] Record current tests and concrete consumer evidence for every consolidation candidate.

### P33-W05 — Candidate classification, ownership, and later-phase mapping

- [ ] Give every candidate exactly one final disposition.
- [ ] Assign every accepted hardening item to an existing Phase 34-39 and update that unstarted phase where it fits its accepted scope.
- [ ] Record explicit defer, reject, and user-decision outcomes with rationale.
- [ ] Confirm generic Approvals and workflow/rules engines remain outside the base foundation.

### P33-W06 — First-release backlog freeze, documentation, and closure

- [ ] Freeze the accepted first-base-release foundation backlog.
- [ ] Verify no actionable audit finding lacks an owner or explicit user decision.
- [ ] Update canonical architecture, module, roadmap, and testing documentation affected by the final dispositions.
- [ ] Run relevant architecture/documentation/link checks and record closure evidence.

## Out of scope

Do not:

- implement whole new capabilities during the audit;
- redesign the modular monolith;
- create a generic service locator or repository;
- start business-domain modules;
- add generic workflow or approval behavior;
- turn the audit into an indefinite cleanup program.

## Completion criteria

- [ ] The audit has finite documented coverage.
- [ ] Every candidate has one final disposition.
- [ ] Real duplicates and boundary leaks are mapped to owners.
- [ ] Later foundation work is frozen for the first base release.
- [ ] No unresolved actionable item exists without an owner or explicit user decision.
- [ ] Canonical architecture and roadmap documentation reflect the result.
- [ ] `WORKROAD.md` status is updated to `complete`.
