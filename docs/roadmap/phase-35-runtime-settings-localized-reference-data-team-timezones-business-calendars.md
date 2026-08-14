# Phase 35 — Runtime Settings, localized reference data, Team timezones, and Business Calendars

**Status:** `not started`

## Objective

Harden and extend the existing Atlas Settings and localization foundation so safe runtime product configuration is manageable through Admin UI, deployment-only configuration remains deployment-owned, localized technical/reference values use stable identities, Teams own business timezone context, and future modules can use named working-day calendars without hardcoded Polish-only behavior.

This phase extends the existing Settings system. It must not create a second Settings platform.

## Dependencies

- [Phase 33 — Foundation extension-point, duplication, and consumer audit](phase-33-foundation-extension-points-and-duplication-audit.md) must be complete.
- [Phase 34 — Module-owned routing, navigation, breadcrumbs, and application surfaces](phase-34-module-owned-routing-navigation-breadcrumbs.md) must be complete.
- Existing Settings, Localization, Teams, Authorization, Audit, and Phase 31 Calendar foundations.

## Related documentation

- Modules: [Settings](../modules/settings.md)
- Modules: [Authorization](../modules/authorization.md)
- Modules: [Calendar](../modules/calendar.md)
- Architecture: [Data contracts, validation, and concurrency](../architecture/data-contracts-validation-and-concurrency.md)
- Operations: [Development environment](../operations/development-environment.md)
- Operations: [Production deployment, backup, and recovery](../operations/production-deployment-backup-and-recovery.md)

## Implementation contract

### Configuration classification and `.env` boundary

Audit `.env`, `.env.example`, `config/*.php`, typed and module Settings, runtime environment lookups, hardcoded defaults, Admin configuration screens, infrastructure credentials, restart-required values, and product/runtime values. Classify every relevant item as deployment/bootstrap owned, deployment secret, safe runtime Admin-editable, runtime Admin-editable secret, runtime system-owned, obsolete, duplicate, or incorrectly located.

Atlas web code must never mutate `.env`; Admin UI is not an `.env` editor. Startup-critical concerns such as `APP_KEY`, database/Redis connectivity, deployment networking, storage bootstrap, and master encryption material remain deployment owned. `env()` remains restricted to configuration/bootstrap boundaries.

### Runtime Admin Settings and secrets

Extend the typed Settings registry with stable key, type, default, validation, owner, localized label/help, permission, sensitivity, runtime/deployment ownership, and restart semantics where applicable. Do not expose arbitrary config keys or let Admin invent application setting keys. Complex module UI may remain module-owned while using shared Settings and secret contracts.

Admin-managed runtime secrets use write-only UX: `Configured` and `Replace secret`. Plaintext is never redisplayed after saving. Secrets are encrypted at rest through the canonical Atlas mechanism, excluded from logs, Audit payloads, and ordinary frontend props, replaceable, and structurally audited without secret material. Startup-critical deployment secrets must not be moved into the database merely to maximize Admin coverage.

### Localized system and dynamic values

Audit user-visible system-owned values such as permission labels, starter/system role labels, and canonical classifications/status presentations. Stable code-owned lifecycle values, permission keys, Integration Event types, and other code-driving concepts remain code-owned and non-Admin-editable, with Laravel translations as canonical copy.

Admin-created dynamic values use an immutable technical code and localized labels. Polish and English labels are required initially, but storage must be locale keyed and extensible; do not permanently encode `name_pl`/`name_en` columns into every dictionary or custom-role table. Apply this to custom roles and similar dynamic technical values where appropriate.

### Reference Dictionaries

Provide an explicitly declared, code-owned reference-dictionary foundation for genuine Admin-editable reasons, categories, classifications, channels, and module-specific types. Admin cannot invent dictionary types unknown to application code. Each definition declares installation/global or Team scope.

Each entry has an immutable stable technical code, required localized labels for baseline locales, display order, owning dictionary/module, scope, active/deleted state, and Audit metadata. Hard deletion is forbidden. A code remains reserved and cannot be reused for another meaning. Normal selection uses non-deleted active values; historical rendering may include soft-deleted values. Provide restore/reactivation where safe.

### Team timezone and temporal semantics

Each Team has one canonical IANA business timezone. `APP_TIMEZONE` becomes only the technical fallback when no Team/business context exists; it is not the business timezone for every Team. Do not add per-user timezone selection.

Real instants remain PostgreSQL `timestamptz`; date-only business values remain `date`. Timezone controls wall-clock interpretation and presentation, not durable instant representation.

Scheduled/recurring wall-clock objects persist the resolved relevant timezone at creation. Switching active Team or later changing a Team timezone must not reinterpret an existing recurrence. Team-timezone changes are Admin-managed, warned, audited, affect future defaults and appropriate future presentation, and do not rewrite historical instants, past business data, or the timezone pinned to existing schedules. Repair hardcoded `Europe/Warsaw` assumptions in Calendar and current documentation during this phase while retaining it as a supported example.

### Business Calendars

Business Calendar is distinct from Phase 31 personal/Meeting Calendar UI. Provide named calendars with stable technical codes, localized presentation, lifecycle, regular working/non-working weekdays, holidays, company days off, and Team default assignment. Multiple calendars such as `COMPANY_PL` and `COMPANY_UK` may coexist. A future module may use the Team default or explicitly choose another named calendar.

Provide reusable mathematics for is/next/previous working day, add/subtract/count business days, and explicitly requested deadline normalization. Core performs calendar mathematics; consuming modules own business deadline rules. Do not require a holiday API, hardcode Poland into the engine, or add working-hours/shift planning without a real Phase 33 consumer.

## Tasks

Workstreams are strictly sequential. Only the earliest incomplete workstream is active.

### P35-W01 — Configuration, environment, and Settings inventory and ownership classification

- [ ] Inventory every relevant environment, config, typed Setting, module Setting, secret, default, and Admin configuration surface.
- [ ] Give each item exactly one ownership classification and identify duplicates, obsolete values, and direct runtime `env()` misuse.
- [ ] Document the deployment/bootstrap and runtime Admin boundary.

### P35-W02 — Typed runtime-setting registry and Admin configuration architecture

- [ ] Extend the existing typed registry with ownership, validation, localization, permission, sensitivity, and restart metadata.
- [ ] Build permission-protected module-aware Admin editing for declared safe runtime settings only.
- [ ] Preserve validation, caching, Audit atomicity, localization, and reduced-mode behavior.

### P35-W03 — Runtime-secret storage, write-only Admin UX, Audit, and security

- [ ] Implement canonical encrypted runtime-secret storage and replacement without plaintext redisplay.
- [ ] Prevent secrets from logs, Audit bodies, frontend props, exports, Diagnostics, and normal reads.
- [ ] Add structural Audit events, security tests, recovery documentation, and key-material boundaries.

### P35-W04 — System-owned localized catalogs and dynamic localized-value model

- [ ] Inventory system-owned user-visible codes and ensure canonical Laravel translations.
- [ ] Implement locale-keyed dynamic-label storage requiring Polish and English without language-specific schema columns.
- [ ] Adopt the model for custom roles and other accepted dynamic technical values.
- [ ] Guard code-owned lifecycle and permission/event keys against Admin mutation.

### P35-W05 — Global and Team Reference Dictionaries and soft-delete lifecycle

- [ ] Implement code-declared dictionary definitions with explicit installation/global or Team scope.
- [ ] Implement immutable codes, localized labels, ordering, activation, soft deletion, and safe restoration.
- [ ] Preserve historical rendering of soft-deleted values and prohibit hard delete/code reuse.
- [ ] Add Authorization, Audit, localization, browser, and persistence coverage.

### P35-W06 — Team IANA timezone foundation and temporal-context migration

- [ ] Add one validated canonical IANA timezone to every Team with safe migration/default behavior.
- [ ] Resolve Team business context through owner-owned public contracts and keep `APP_TIMEZONE` as technical fallback only.
- [ ] Add warned and audited Admin timezone changes without per-user timezone settings.
- [ ] Inventory and migrate current Team-context consumers without rewriting historical instants.

### P35-W07 — Calendar recurrence and scheduled-object timezone correction

- [ ] Persist resolved wall-clock timezone on applicable recurrence and scheduled objects.
- [ ] Prevent active-Team switches and later Team-timezone changes from reinterpreting existing schedules.
- [ ] Preserve `date` versus `timestamptz` semantics and add DST/boundary tests.
- [ ] Repair hardcoded universal `Europe/Warsaw` assumptions while preserving truthful migration history.

### P35-W08 — Named Business Calendars, Team defaults, and business-day calculations

- [ ] Implement named global calendar definitions, localized display, lifecycle, weekdays, holidays, and company days off.
- [ ] Add Team default Business Calendar selection and narrow public calculation contracts.
- [ ] Implement is/next/previous/add/subtract/count/normalize operations with boundary coverage.
- [ ] Demonstrate coexisting Polish and non-Polish calendars without an external holiday provider.

### P35-W09 — Existing-consumer adoption, browser acceptance, tests, documentation, and legacy cleanup

- [ ] Migrate accepted existing consumers and remove obsolete duplicate settings, language columns, and universal-timezone assumptions.
- [ ] Add backend, architecture, localization, security, and Chromium/Firefox browser acceptance where required.
- [ ] Update Settings, Teams, Authorization, Calendar, architecture, deployment, recovery, and testing documentation.
- [ ] Run applicable quality and documentation gates and record closure evidence.

## Out of scope

Do not make Admin UI edit `.env`, dynamically create setting keys or domain states, make every enum a dictionary, add per-user timezone, add an external holiday provider, build shift planning, or build a workflow/rules engine.

## Completion criteria

- [ ] Configuration ownership is finite, documented, and enforced.
- [ ] Safe runtime Settings and write-only secrets use the existing typed Settings foundation.
- [ ] Dynamic values require Polish and English labels through locale-extensible storage and immutable codes.
- [ ] Reference Dictionaries support global/Team scope, soft deletion, restoration, and historical rendering.
- [ ] Every Team has a canonical IANA timezone and `APP_TIMEZONE` is fallback only.
- [ ] Existing wall-clock schedules retain their pinned timezone across Team changes.
- [ ] Named Business Calendars and business-day calculations are reusable without domain workflow leakage.
- [ ] Canonical documentation and tests are current and `WORKROAD.md` status is `complete`.
