# Phase 36 — Reusable data integrity and business-support primitives

**Status:** `not started`

## Objective

Consolidate a small set of proven reusable data-integrity and business-support patterns required by current and future Atlas modules without creating a generic business framework:

- opt-in optimistic locking;
- canonical Change Reason handling;
- date and instant effective ranges;
- opt-in provenance/source metadata;
- safe cross-module object references;
- business numbering/sequences.

Every capability remains narrow, opt-in, and owner-aware.

## Dependencies

- [Phase 33 — Foundation extension-point, duplication, and consumer audit](phase-33-foundation-extension-points-and-duplication-audit.md) must be complete.
- [Phase 35 — Runtime Settings, localized reference data, Team timezones, and Business Calendars](phase-35-runtime-settings-localized-reference-data-team-timezones-business-calendars.md) must be complete.
- Existing Audit, Authorization, and modular public-contract architecture.

## Related documentation

- Architecture: [Modular-monolith architecture](../architecture/modular-monolith.md)
- Architecture: [Data contracts, validation, and concurrency](../architecture/data-contracts-validation-and-concurrency.md)
- Modules: [Audit](../modules/audit.md)
- Modules: [Authorization](../modules/authorization.md)
- Modules: [Settings](../modules/settings.md)

## Implementation contract

### Optimistic locking

Consolidate existing stale-write protection into one opt-in contract. Do not add `version` to every table. Use it for editable aggregates with demonstrated lost-update risk: clients submit the read version, mutations succeed only on a match, and stale writes fail explicitly without silent overwrite, merge, or replay.

HTTP conflicts normally use `409 Conflict`. Provide shared localized frontend conflict UX equivalent to “The record changed since you opened it,” with safe reload/retry guidance. Preserve Audit correctness and keep future ETag compatibility possible without imposing ETags everywhere.

### Canonical Change Reason

Provide a narrow shared contract carrying a reason from request/input through application use case, Audit, modal/form UX, validation, localization, and test helpers. The owner decides whether it is required, its detail rules, permission, collection point, and whether a module Reference Dictionary supplies an optional reason code. Do not create one global reason dictionary or centralize the business decision being justified.

### Effective-dated primitives

Provide separate `EffectiveDateRange` for date-only semantics and `EffectiveInstantRange` for real instants. Instant persistence uses `timestamptz`; Team timezone participates only when converting local wall-clock input to an instant.

Both use half-open semantics: `valid_from <= value < valid_until`; null `valid_until` is open ended. Provide validation, overlap detection, current-at-date/current-at-instant helpers, future scheduling, boundary tests, and timezone/DST tests where needed. Do not create a universal temporal table, repository, or automatic history for every entity; persistence stays with consumers.

### Provenance/source metadata

Provide opt-in origin metadata for manual, import, API, integration, system, bootstrap, and meaningful migration sources. Optional metadata may identify provider/integration, import run, external reference, Service Account, actor, correlation, and source occurrence/import time.

Provenance records original source; Audit records later changes and lifecycle. Do not rewrite provenance on later manual edits or store raw payloads, secrets, full HTTP bodies, or duplicate Audit context. Do not add provenance columns everywhere.

### Safe cross-module object references

A reference contains only owner module, stable object type, and stable public ID. Never expose foreign Eloquent class or table names. The owning module registers and owns its resolver, which may return only presentation-safe localized label, optional safe deep link, owner/type metadata, and availability.

Resolution is Authorization-, ModuleGate-, and privacy-aware. For ordinary callers, inaccessible, missing, deleted, and disabled cases collapse to neutral `unavailable` when distinguishing them leaks information. A deliberately permission-protected Admin diagnostic path may expose richer operational diagnosis.

Safe references are not arbitrary field access, a generic ORM/repository/service locator, a cross-module mutation path, or a way to read domain fields such as status, amount, or customer. Consumers needing domain data still use concrete provider-owned public contracts.

### Business numbering and sequences

Provide opt-in business-facing numbering independent of internal IDs and public ULIDs. A code-declared sequence has a stable key, owner, installation/global or Team scope, optional prefix/pattern, supported year/month tokens, padding, and never/yearly/monthly reset policy.

Issuance is atomic and unique in scope under concurrency and immutable after issue. Configuration changes affect only future numbers. No normal Admin action sets or resets `next_number`; reset follows policy. Do not promise universal gaplessness. Preview and validate formats without consuming numbers, and audit configuration changes.

## Tasks

Workstreams are strictly sequential. Only the earliest incomplete workstream is active.

### P36-W01 — Existing pattern and consumer audit and canonical contract design

- [ ] Use Phase 33 evidence to inventory current consumers and semantic variants for all six capabilities.
- [ ] Define narrow ownership, public contracts, adoption criteria, and forbidden generic abstractions.
- [ ] Identify exactly which current consumers must migrate.

### P36-W02 — Optimistic locking and shared conflict UX

- [ ] Implement opt-in backend version checking and canonical HTTP conflict mapping.
- [ ] Implement shared localized frontend conflict UX with safe reload/retry guidance.
- [ ] Migrate justified consumers and add concurrency, Audit, HTTP, and rendered regression coverage.

### P36-W03 — Canonical Change Reason

- [ ] Implement the normalized reason input/application/Audit contract and reusable form/modal behavior.
- [ ] Preserve use-case-owned requirement, validation, permission, and optional dictionary-code semantics.
- [ ] Migrate repeated equivalent consumers and add localization and test helpers.

### P36-W04 — EffectiveDateRange and EffectiveInstantRange

- [ ] Implement separate date-only and instant value contracts with half-open/open-ended semantics.
- [ ] Add overlap, current-value, future scheduling, boundary, timezone, and DST coverage.
- [ ] Migrate only proven consumers while leaving persistence module owned.

### P36-W05 — Opt-in provenance/source metadata

- [ ] Implement a minimal typed provenance contract and safe source metadata.
- [ ] Keep provenance immutable as origin and distinct from Audit history.
- [ ] Adopt it only in accepted consumers and add secret/raw-payload guardrails.

### P36-W06 — Safe cross-module object references

- [ ] Implement typed owner/type/public-ID references and owner-registered presentation resolvers.
- [ ] Enforce Authorization, ModuleGate, localization, and neutral unavailable behavior.
- [ ] Add guards against Eloquent/table identities, arbitrary field access, mutation, service location, and privacy leakage.
- [ ] Add representative deep-link, missing/deleted/disabled, and Admin-diagnostic tests.

### P36-W07 — Global and Team business numbering sequences

- [ ] Implement code-declared global/Team sequences, format validation/preview, and supported reset policies.
- [ ] Make issuance atomic, scoped, unique, immutable, and concurrency tested.
- [ ] Prevent manual next-counter reset, historical renumbering, and false gapless guarantees.
- [ ] Add audited configuration changes and owner-aware public contracts.

### P36-W08 — Existing-consumer adoption, architecture guards, browser tests, and documentation

- [ ] Migrate every duplicate pattern assigned by Phase 33 or document why it is semantically different.
- [ ] Add architecture guards and meaningful backend/frontend/browser acceptance.
- [ ] Update affected module, architecture, UI, and testing documentation.
- [ ] Run applicable quality and documentation gates and record closure evidence.

## Out of scope

- Universal base entities, tables, repositories, or temporal history.
- Optimistic-lock columns on every table.
- Magic stale-write merging or replay.
- One global business-reason dictionary.
- Arbitrary cross-module object reads or mutations.
- A universal gapless-number promise or manual next-counter control.
- Generic workflow, rules, or approval engines.

## Completion criteria

- [ ] Every primitive remains opt-in, narrow, and owner-aware.
- [ ] Existing duplicates identified by Phase 33 are migrated or documented as semantically different.
- [ ] Stale writes fail explicitly with shared safe UX and no silent overwrite.
- [ ] Date and instant ranges preserve half-open, open-ended, timezone, and DST semantics.
- [ ] Provenance remains distinct from Audit.
- [ ] Safe references preserve module, authorization, and privacy boundaries.
- [ ] Global/Team sequence issuance is atomic and issued numbers are immutable.
- [ ] Tests and canonical documentation are current and `WORKROAD.md` status is `complete`.
