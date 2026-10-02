# Phase 51 — Developer extension contract, module documentation, and MDK

**Status:** `not started`

## Objective

Finalize Atlas as a developer-consumable modular foundation after its architecture and reusable platform contracts are stable by hardening extension boundaries, documenting correct module construction, adding focused examples/test fixtures, and providing a minimal Module Developer Kit (MDK) scaffolder.

The generator is the result of the architecture; it must not design the architecture.

## Dependencies

- All accepted extension-relevant foundations and capabilities through Phase 50 must be complete, including [Phase 34](phase-34-foundation-extension-points-and-duplication-audit.md), [Phase 35](phase-35-module-owned-routing-navigation-breadcrumbs.md), [Phase 36](phase-36-runtime-settings-localized-reference-data-team-timezones-business-calendars.md), [Phase 37](phase-37-reusable-data-integrity-business-primitives.md), [Phase 38](phase-38-enterprise-oidc-external-identities.md), [Phase 39](phase-39-api-service-accounts-integration-events-webhooks.md), and the Optional capabilities in Phases 40–50.
- [Modular-monolith architecture](../architecture/modular-monolith.md)

Do not build the MDK before routing, Settings, temporal/data primitives, OIDC/API boundaries, and public extension contracts are stable.

## Related documentation

- Architecture: [Modular-monolith architecture](../architecture/modular-monolith.md)
- Architecture: [Frontend UI](../architecture/frontend-ui.md)
- Modules: [Module documentation index](../modules/README.md)
- Operations: [Quality gates and Git](../operations/quality-gates-and-git.md)
- Operations: [Testing environment](../operations/testing-environment.md)

## Implementation contract

### Developer extension contract

Audit the final supported paths for Authorization, module activation, Audit, Settings, Localization, navigation, routes, breadcrumbs, Files, Search, Notifications, exports/reports, Managed Processes, scheduling, Health, Integration Events, API/webhooks, optimistic locking, Change Reason, effective periods, provenance, safe references, numbering, Reference Dictionaries, and Business Calendars.

A module must not need foreign tables, Infrastructure, Eloquent models, hidden persistence, central god-route/breadcrumb edits, or duplicate shared setting/authorization/audit primitives. Permanent architecture tests enforce these boundaries.

### Developer documentation

Create canonical `Creating an Atlas module` guidance covering category, manifest and registration, schema ownership, layers, `Application/Public`, dependency direction, routes by surface, navigation, breadcrumbs, permissions, localization, Settings, Audit, ModuleGate, synchronous contracts, Integration Events, Files/Search/Notifications, API exposure, tests, browser acceptance, and forbidden patterns.

Include small focused examples. Do not create a production KitchenSink module. A small internal/test fixture may verify contracts and generator behavior.

### Module Developer Kit

Provide a minimal command conceptually equivalent to `php artisan atlas:make-module Foo --category=application` or `--category=optional`. Support Application and Optional Foundation modules. Do not normally generate Core modules; adding Core is an architectural decision.

Generate only the minimal correct structure and registration hooks for Domain, Application, `Application/Public`, Infrastructure, Presentation, providers, routes, tests, documentation, manifest, PostgreSQL schema/migrations, permissions, localization, navigation, breadcrumbs, and architecture checks as genuinely required.

Do not generate speculative entities, repositories, commands, queries, API controllers, event handlers, placeholder CRUD/business logic, or dozens of empty classes. Optional capabilities remain opt-in.

Validate names, stable keys, category, directories, manifest collisions, and schema-key conflicts before partial generation where practical. Never silently overwrite an existing module. Generated output must pass relevant architecture/static checks.

### New required extension documentation

The developer extension/MDK contract must document and test how developers create and integrate:

- Optional modules with required and optional dependencies;
- safe reduced modes;
- registered resource types;
- Phase 37 safe resource references;
- resource authorization checks;
- typed action targets;
- Comments/Notes support registration;
- Tags support;
- Custom Fields support registration;
- Work Schedule public queries;
- Work Management Task producers and action targets;
- TimeTracking Task/business-context integration;
- DocumentGeneration template definitions and context schemas;
- OCR provider adapters;
- DocumentExtraction profiles, schemas, providers, input modes, decision policies, result consumers;
- DuplicateDetection profiles/providers/policies/result consumers;
- Approval request/result integration;
- Correspondence submission/tracking provider adapters;
- BusinessTimeline contribution integration;
- optional Address Validation providers.

For every provider/extension type, document:

- owner;
- public interface;
- configuration/secret model;
- lifecycle;
- module dependency metadata;
- failure semantics;
- idempotency where relevant;
- privacy/data classification;
- test adapter/fake expectations;
- Health/operational expectations;
- forbidden foreign persistence access.

The MDK must not generate speculative business CRUD.

The module generator remains Application/Optional oriented and must not turn these capabilities into magical convention scanning.

## Tasks

Workstreams are strictly sequential. Only the earliest incomplete workstream is active.

### P51-W01 — Final extension-boundary audit

- [ ] Inventory and exercise every supported extension path listed in the implementation contract.
- [ ] Identify remaining central-file edits, hidden persistence knowledge, duplicate primitives, and unsupported documentation assumptions.
- [ ] Close accepted boundary gaps before documenting or generating them.

### P51-W02 — Architecture guards and forbidden dependency enforcement

- [ ] Add permanent guards for foreign Infrastructure/Eloquent/table access and unsupported registration paths.
- [ ] Enforce provider-owned public contracts, Integration Event boundaries, schema ownership, and module-owned surfaces.
- [ ] Verify Application and Optional modules can integrate without violating documented boundaries.

### P51-W03 — `Creating an Atlas module` developer documentation

- [ ] Write the complete canonical module-development guide with small focused examples.
- [ ] Document required and opt-in capabilities, quality gates, tests, browser acceptance, and forbidden patterns.
- [ ] Cross-link architecture, module, frontend, security, and operations sources without duplicating their full contracts.

### P51-W04 — Focused reference examples and test fixture

- [ ] Add only the focused examples or internal test fixture necessary to prove documented extension paths.
- [ ] Keep fixtures out of production behavior and avoid a KitchenSink module.
- [ ] Verify examples remain synchronized with architecture tests and documentation.

### P51-W05 — Minimal application and optional module generator

- [ ] Implement minimal scaffolding for `application` and `optional` categories using final naming conventions.
- [ ] Generate only genuinely required structure, manifests, providers, ownership hooks, tests, and documentation registration.
- [ ] Keep Core generation unavailable in the normal workflow and optional capabilities opt-in.

### P51-W06 — Generator validation, architecture tests, and generated-module quality checks

- [ ] Validate module names, keys, categories, directories, manifests, and schema collisions before writing.
- [ ] Prevent overwrite and partial generation where practical.
- [ ] Generate representative Application and Optional fixtures and run architecture/static/format checks.
- [ ] Assert absence of speculative CRUD, entities, repositories, APIs, and placeholder business logic.

### P51-W07 — New Optional/provider extension contracts

- [ ] Document every extension/provider surface introduced by Phases 37 and 40–50.
- [ ] Add focused examples for required versus optional module dependency declarations and reduced modes.
- [ ] Add provider examples for OCR, extraction, duplicate detection, address validation, and correspondence tracking.
- [ ] Add resource registration examples for Comments, Tags, Custom Fields, Tasks/action targets, and Timeline.
- [ ] Add architecture tests/fixtures proving generated/example modules do not access foreign Infrastructure/Eloquent/tables.
- [ ] Add deterministic fake/provider testing guidance.
- [ ] Verify a developer can implement an independent Application module consuming these capabilities without reading their persistence internals.

### P51-W08 — Documentation cross-check, developer acceptance, cleanup, and closure

- [ ] Follow the guide from a fresh developer perspective and validate both supported generator categories.
- [ ] Cross-check docs against real extension contracts, generated output, and architecture guards.
- [ ] Remove temporary fixtures/artifacts not intentionally retained.
- [ ] Update canonical architecture, module index, operations, and roadmap documentation and record closure evidence.

## Out of scope

- Routine Core-module generation.
- A KitchenSink production module.
- Speculative CRUD or business boilerplate.
- Automatic generation of every optional capability.
- Redesigning unstable architecture inside the generator.

## Completion criteria

- [ ] A developer can create a valid Atlas module without historical chat context.
- [ ] Supported integration paths do not require module-boundary violations.
- [ ] Architecture guards enforce the documented contract.
- [ ] The MDK generates minimal valid Application and Optional modules, not normal Core modules.
- [ ] Generator validation prevents collisions, overwrite, and unsafe partial output.
- [ ] Generated fixtures pass relevant architecture and static checks without speculative business code.
- [ ] Provider/extension documentation covers every supported provider-neutral integration introduced before Phase 51.
- [ ] Optional dependency and reduced-mode conventions are explicit and tested.
- [ ] Resource registration/action-target/authorization conventions are explicit.
- [ ] The MDK does not generate workflow, dynamic entities, or speculative CRUD.
- [ ] Canonical documentation is current and `WORKROAD.md` status is `complete`.
