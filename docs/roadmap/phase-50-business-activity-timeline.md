# Phase 50 — Optional business activity timeline

**Status:** `not started`

## Objective

Add an Optional Business Activity Timeline that provides a user-friendly immutable history of meaningful events around a business resource.

Business Activity Timeline is explicitly distinct from Audit.

Audit is technical/security evidence.

Business Activity Timeline answers:

`What meaningful business events happened to this resource?`

Example events include:

- resource created;
- Task assigned;
- Task completed;
- status changed;
- document generated;
- extraction reviewed;
- approval completed;
- correspondence sent;
- correspondence delivered.

## Dependencies

- Phase 37 typed resource references and authorization.
- Audit.
- Authorization.
- ModuleGate.
- localization.
- privacy/retention.
- Integration Events/Outbox where used for reliable asynchronous contribution.
- relevant Optional producer modules from Phases 40–49.

## Ownership

Create an Optional `BusinessTimeline` module.

It owns materialized timeline entries and timeline presentation/query surfaces.

It does not own producer business state.

It does not replace Audit.

## Immutable user-facing history

Timeline entries are immutable from normal user UI.

Users cannot edit or delete historical business entries.

Human-authored editable narrative belongs in Comments/Notes, not Timeline.

Privacy/legal lifecycle may redact/anonymize/delete content when required by authoritative policy; such lifecycle is not ordinary user editing.

## Explicit contribution only

Do not derive Business Timeline by scraping Audit.

Do not infer business meaning from database diffs.

Producer modules explicitly publish timeline contributions/events with stable code-owned event types.

Phase 50 may add the narrow shared/public contribution port/integration-event contract required for this real consumer.

Do not create a generic event/service locator.

## Optional producer dependency

Producer modules must continue to work when BusinessTimeline is inactive.

Use a neutral optional contribution mechanism / integration event / no-op binding consistent with Atlas architecture.

Do not make every producer require the Optional Timeline module to activate.

## Entry model

A timeline entry contains at minimum:

- public ID;
- source resource reference;
- stable event type;
- occurred-at instant;
- producer module;
- producer event/public ID for idempotency;
- optional actor safe reference/presentation;
- localized presentation key;
- bounded typed presentation parameters;
- optional related resource reference;
- privacy classification/metadata required by lifecycle;
- created/materialized timestamp.

Do not persist arbitrary HTML.

Do not persist whole foreign business objects.

## Localization

Store stable event type/presentation keys and typed parameters.

Render user-facing PL/EN text at presentation time according to the canonical localization model.

Do not persist one English sentence as canonical business history when structured data can render localized text.

Historical event meaning must remain stable even if presentation wording improves.

## Authorization

Timeline visibility follows source-resource authorization.

Do not create a second per-entry visibility ACL.

If the User cannot see the source resource, the User cannot access its timeline.

Related-resource labels/actions are separately authorization-safe.

## Idempotency/order

Contributions are idempotent by producer/event identity.

At-least-once event delivery must not duplicate entries.

Order primarily by business occurrence time with deterministic tie-breaking.

Do not rewrite occurrence time merely because materialization was delayed.

## Producer examples

During this phase add explicit contributions from relevant implemented modules, including where meaningful:

- Comments: comment added/edited only if product value warrants an entry; do not copy private full body;
- Work Management: Task created/assigned/claimed/completed/cancelled;
- DocumentGeneration: final document generated;
- DocumentExtraction: review accepted/corrected/rejected where meaningful;
- Approvals: requested/approved/rejected/cancelled;
- Correspondence: submitted/sent/delivered/failed/returned.

Do not create timeline noise for every technical retry.

## Search

Do not create a global full-text timeline Search baseline unless a demonstrated consumer requires it.

Timeline is primarily shown on the source resource.

If later Search integration is added, source authorization remains mandatory.

## Files

Timeline may reference Files-owned artifacts through related safe resource/file links.

It does not own file bytes.

## Audit

Creating/materializing a Timeline entry is not a replacement for required Audit.

Do not duplicate all Timeline presentation payload into Audit.

## Privacy/retention

Timeline participates in source privacy/deletion/anonymization/legal-hold lifecycle.

Entries must not preserve sensitive source content after authoritative removal unless legal retention requires it.

Actor display must handle anonymized/deleted Users safely.

## Module activation

BusinessTimeline is Optional.

When inactive:

- producer business actions continue;
- no Timeline UI is shown;
- optional contribution delivery safely no-ops/is not materialized according to the accepted integration contract.

Activation later does not require replaying all historical system events unless an explicit migration/backfill is defined.

Do not promise automatic reconstruction from Audit.

## UI

Provide a reusable source-resource timeline component with:

- localized event text;
- timestamp;
- actor where safe;
- related-resource link where authorized;
- pagination/cursor for long history;
- loading/empty/error states.

PL/EN, responsive, light/dark required.

## Operational visibility

Expose materialization failure/lag/idempotency metrics without exposing business content.

## Explicit non-goals

- Audit replacement;
- editable user history;
- Comments replacement;
- event sourcing;
- generic global event store;
- workflow engine;
- arbitrary HTML events;
- reconstruction from Audit;
- per-entry visibility ACL.

## Tasks

### P50-W01 — Timeline contracts and module

- [ ] Create Optional BusinessTimeline module, schema, permissions, module metadata, public contracts, and docs.
- [ ] Define stable contribution/event/idempotency contract.
- [ ] Implement source authorization integration.

### P50-W02 — Materialization and localization

- [ ] Implement immutable materialized entries.
- [ ] Add PL/EN presentation-key/typed-parameter rendering.
- [ ] Add ordering/idempotency/retry behavior.

### P50-W03 — Producer adoption

- [ ] Add explicit meaningful contributions to implemented producer modules.
- [ ] Avoid technical retry/noise events.
- [ ] Keep producers functional with Timeline inactive.

### P50-W04 — UI, privacy, lifecycle

- [ ] Implement reusable source timeline UI.
- [ ] Add privacy/anonymization/deletion/legal-hold behavior.
- [ ] Add safe actor/related-resource degradation.

### P50-W05 — Activation, operations, tests, docs

- [ ] Test active/inactive producer behavior.
- [ ] Add at-least-once/idempotency/materialization-failure coverage.
- [ ] Add PL/EN/light/dark/browser coverage.
- [ ] Update Audit/architecture/module/privacy/testing docs.

## Completion criteria

- [ ] Timeline is user-friendly business history, not Audit.
- [ ] Entries are explicit, immutable, localized, and idempotent.
- [ ] Visibility follows source-resource authorization.
- [ ] Producers work when Timeline is inactive.
- [ ] No workflow/event-sourcing/global-event-store scope exists.
- [ ] Privacy/retention and operational failure behavior are tested.
- [ ] `WORKROAD.md` status is `complete`.
