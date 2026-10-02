# Phase 37 — Reusable data integrity and business-support primitives

**Status:** `not started`

## Objective

Consolidate a small set of proven reusable data-integrity and business-support patterns required by current and future Atlas modules without creating a generic business framework:

- opt-in optimistic locking;
- canonical Change Reason handling;
- date and instant effective ranges;
- opt-in provenance/source metadata;
- safe cross-module object references with owner-controlled resource authorization;
- reusable contact/address value primitives;
- optional provider-neutral address validation;
- business numbering/sequences.

Every capability remains narrow, opt-in, and owner-aware.

## Dependencies

- [Phase 34 — Foundation extension-point, duplication, and consumer audit](phase-34-foundation-extension-points-and-duplication-audit.md) must be complete.
- [Phase 36 — Runtime Settings, localized reference data, Team timezones, and Business Calendars](phase-36-runtime-settings-localized-reference-data-team-timezones-business-calendars.md) must be complete.
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

#### Resource access authorization

Cross-cutting capabilities that attach data to a foreign resource must not infer access from possession of a public ID.

The resource owner must expose a narrow framework-independent authorization contract capable of answering only the capability-specific questions required by registered cross-cutting consumers. At minimum support a privacy-safe read-access check for a typed resource reference.

Where a capability needs mutation/contribution access, use a capability-specific operation key rather than arbitrary policy/service invocation.

The contract must:

- be implemented by the resource owner;
- be Authorization- and ModuleGate-aware;
- avoid returning domain fields;
- avoid returning Eloquent models;
- avoid table access;
- collapse inaccessible/missing/deleted states where distinction would leak information.

This exists to support capabilities such as Comments, Tags, Custom Fields, Tasks, and Business Timeline.

It is not a generic authorization service locator.

#### Registered action targets

Provide a typed resource action-target primitive for future Work Management.

An action target consists of:

- owner module;
- stable resource type;
- public resource ID;
- stable code-owned action key.

The owning resource module resolves the action key to an authorized safe application route/action.

Do not persist arbitrary URLs as the canonical integration model.

A safe fallback deep link may still be returned by the owner resolver for presentation.

### Contact and address value primitives

Provide business-domain-neutral value primitives for:

- email address;
- phone number;
- structured postal address.

They are shared value/validation/formatting primitives, not entities.

They must not create a global Person, Customer, Company, Contractor, Contact, or address-book domain.

Business modules remain owners of their contact/address data.

#### Email

Provide:

- normalized value representation;
- syntax validation;
- safe formatting;
- equality semantics appropriate to the accepted normalization rules.

Do not claim mailbox existence validation.

#### Phone

Provide:

- raw input normalization;
- country calling-code aware representation;
- normalized/canonical representation where deterministically possible;
- international formatting;
- structural validation.

Do not claim that a number is assigned or reachable unless an external provider explicitly verifies that fact.

#### Structured address

Support international structured addresses without assuming the Polish address shape.

The model must accommodate at least:

- country;
- postal code;
- locality/city;
- street/address lines;
- building/premise;
- unit/sub-premise;
- region/state/province where relevant;
- additional country-specific lines where required.

Do not force every country into `street + postal code + city`.

### Optional Address Validation Provider

Provide a provider-neutral optional validation/normalization contract.

The address primitives and every consuming business module must work without a configured provider.

A configured provider may be:

- an external API;
- a company-owned service;
- a geocoding/address standardization system;
- a national/postal address service;
- a central company address database.

A provider may return:

- validation status;
- normalized/canonical suggestion;
- confidence where available;
- structured corrections;
- provider reference;
- safe provider metadata.

The provider does not become the owner of business-domain contact data.

Accepting a provider suggestion remains an explicit consuming use case.

Secrets/configuration use the Settings/Integrations security model.

Provider failures must not corrupt or silently discard the original submitted address.

### Business numbering and sequences

Provide opt-in business-facing numbering independent of internal IDs and public ULIDs. A code-declared sequence has a stable key, owner, installation/global or Team scope, optional prefix/pattern, supported year/month tokens, padding, and never/yearly/monthly reset policy.

Issuance is atomic and unique in scope under concurrency and immutable after issue. Configuration changes affect only future numbers. No normal Admin action sets or resets `next_number`; reset follows policy. Do not promise universal gaplessness. Preview and validate formats without consuming numbers, and audit configuration changes.

## Tasks

Workstreams are strictly sequential. Only the earliest incomplete workstream is active.

### P37-W01 — Existing pattern and consumer audit and canonical contract design

- [ ] Use Phase 34 evidence to inventory current consumers and semantic variants for all accepted capabilities.
- [ ] Define narrow ownership, public contracts, adoption criteria, and forbidden generic abstractions.
- [ ] Identify exactly which current consumers must migrate.

### P37-W02 — Optimistic locking and shared conflict UX

- [ ] Implement opt-in backend version checking and canonical HTTP conflict mapping.
- [ ] Implement shared localized frontend conflict UX with safe reload/retry guidance.
- [ ] Migrate justified consumers and add concurrency, Audit, HTTP, and rendered regression coverage.

### P37-W03 — Canonical Change Reason

- [ ] Implement the normalized reason input/application/Audit contract and reusable form/modal behavior.
- [ ] Preserve use-case-owned requirement, validation, permission, and optional dictionary-code semantics.
- [ ] Migrate repeated equivalent consumers and add localization and test helpers.

### P37-W04 — EffectiveDateRange and EffectiveInstantRange

- [ ] Implement separate date-only and instant value contracts with half-open/open-ended semantics.
- [ ] Add overlap, current-value, future scheduling, boundary, timezone, and DST coverage.
- [ ] Migrate only proven consumers while leaving persistence module owned.
- [ ] Allow Reference Dictionary definitions from Phase 36 to opt into `EffectiveDateRange` or `EffectiveInstantRange` where their owner explicitly requires effective-dated validity.
- [ ] Keep ordinary dictionaries on their simpler activation/deletion lifecycle when effective dating is not required.

### P37-W05 — Opt-in provenance/source metadata

- [ ] Implement a minimal typed provenance contract and safe source metadata.
- [ ] Keep provenance immutable as origin and distinct from Audit history.
- [ ] Adopt it only in accepted consumers and add secret/raw-payload guardrails.

### P37-W06 — Safe cross-module object references

- [ ] Implement typed owner/type/public-ID references and owner-registered presentation resolvers.
- [ ] Enforce Authorization, ModuleGate, localization, and neutral unavailable behavior.
- [ ] Add guards against Eloquent/table identities, arbitrary field access, mutation, service location, and privacy leakage.
- [ ] Add representative deep-link, missing/deleted/disabled, and Admin-diagnostic tests.

### P37-W07 — Global and Team business numbering sequences

- [ ] Implement code-declared global/Team sequences, format validation/preview, and supported reset policies.
- [ ] Make issuance atomic, scoped, unique, immutable, and concurrency tested.
- [ ] Prevent manual next-counter reset, historical renumbering, and false gapless guarantees.
- [ ] Add audited configuration changes and owner-aware public contracts.

### P37-W08 — Contact/address primitives and optional address validation

- [ ] Implement framework-independent EmailAddress, PhoneNumber, and StructuredAddress primitives.
- [ ] Add normalization, formatting, structural validation, serialization, and international boundary tests.
- [ ] Implement the optional provider-neutral Address Validation contract.
- [ ] Add a deterministic fake provider for tests.
- [ ] Prove consumers work correctly with no provider configured.
- [ ] Add failure, timeout, malformed-provider-result, privacy, and secret-safety tests.
- [ ] Document the rule that contact data remains business-module-owned.

### P37-W09 — Existing-consumer adoption, architecture guards, browser tests, and documentation

- [ ] Migrate every duplicate pattern assigned by Phase 34 or document why it is semantically different.
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
- Central Customer/Person/Company/Contractor model.
- Central contact database owned by the primitive layer.
- Mandatory external address provider.
- Assumption that validation provider data is authoritative business-domain ownership.

## Completion criteria

- [ ] Every primitive remains opt-in, narrow, and owner-aware.
- [ ] Existing duplicates identified by Phase 34 are migrated or documented as semantically different.
- [ ] Stale writes fail explicitly with shared safe UX and no silent overwrite.
- [ ] Date and instant ranges preserve half-open, open-ended, timezone, and DST semantics.
- [ ] Provenance remains distinct from Audit.
- [ ] Safe references preserve module, authorization, and privacy boundaries.
- [ ] Resource authorization and action-target contracts remain owner-controlled and do not expose foreign persistence.
- [ ] Contact/address primitives remain business-domain neutral and usable without a configured validation provider.
- [ ] Global/Team sequence issuance is atomic and issued numbers are immutable.
- [ ] Tests and canonical documentation are current and `WORKROAD.md` status is `complete`.
