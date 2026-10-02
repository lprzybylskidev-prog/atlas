# Phase 47 — Optional duplicate detection and resolution policies

**Status:** `not started`

## Objective

Add an Optional provider-neutral Duplicate Detection capability.

Atlas does not define one global meaning of duplicate.

Each business/resource owner defines a registered duplicate-detection profile and chooses/configures the provider/strategy and decision policy appropriate to that resource.

The capability standardizes normalization, fingerprints, candidates, score/confidence, evidence, execution, policy evaluation, and optional human review.

## Dependencies

- Phase 37 safe references and reusable primitives.
- Optional Phase 42 Work Management for human review.
- Authorization.
- Audit.
- Settings.
- Integrations.
- ModuleGate.
- Managed Processes where needed.
- privacy/retention.

## Ownership

Create an Optional `DuplicateDetection` module.

It owns:

- provider/strategy registry;
- configured duplicate profiles;
- detection requests;
- candidate/result records;
- score/confidence/evidence;
- decision-policy outcome;
- human-review integration/history.

It does not own the source/target business records.

It does not merge/delete/block business records directly.

## Registered profiles

A business module registers a stable duplicate profile containing:

- owner module;
- resource type;
- profile key/version;
- input contract;
- supported normalization/fingerprint features;
- provider capabilities;
- allowed policy actions;
- consumer/result contract.

Admin may configure a registered profile.

Admin may not invent arbitrary resource types or query foreign tables.

## Providers/strategies

Support provider-neutral strategies including:

- deterministic normalization/fingerprint logic;
- owner-supplied algorithm;
- company service;
- external similarity API;
- ML/AI model;
- other registered adapter.

Atlas may provide reusable normalization/fingerprint primitives but does not impose one business algorithm.

## Normalization

Provide reusable safe primitives where appropriate, but the resource owner decides which values participate.

Do not assume email, phone, name, address, amount, or identifier means the same thing across domains.

## Candidate result

A candidate contains:

- source reference;
- candidate reference;
- provider/profile/version;
- score/confidence where meaningful;
- deterministic exact-match flags where meaningful;
- source evidence/reasons;
- timestamps;
- decision state.

Do not fabricate a score.

## Configurable policy

Policy is configured per profile.

Allow combinations equivalent to:

- allow/no duplicate action;
- warn;
- block requested business operation;
- automatic duplicate decision;
- human review.

Support configurable score bands/thresholds and deterministic exact-match rules.

Examples are configuration, not hardcoded global behavior.

There is no global `duplicate_threshold`.

## Business ownership of blocking

DuplicateDetection returns a typed decision.

The business module owns the actual create/update/merge use case and decides how that decision affects its mutation according to the registered profile contract.

DuplicateDetection never directly mutates foreign records.

## Human review

If the configured policy requires review:

- Work Management must be active;
- create an authorized review Task;
- reviewer opens DuplicateDetection-owned review UI;
- show candidates, scores, and evidence;
- reviewer confirms/rejects/corrects the duplicate determination.

Do not create another review queue module.

## Review history

Preserve automated result, reviewer, decision, reason, evidence, before/after where correction exists, and timestamps.

Audit the lifecycle.

## Provider failure

Define per-profile safe failure behavior.

Do not silently convert provider failure into `not duplicate`.

Profiles may be configured to:

- fail closed/block pending retry;
- require human review;
- fail the originating operation explicitly.

The allowed failure policy is code-bounded by the profile.

## Privacy

Candidate generation must not leak existence of records the actor is unauthorized to know about.

Human review candidates require authorization.

Provider payloads must minimize personal/business data.

External providers follow Integrations secret/privacy contracts.

## Search

DuplicateDetection is not a global Search engine.

Do not use Search results as an authorization bypass.

## Module activation

DuplicateDetection is Optional.

Human-review policies require active Work Management.

Automatic-only profiles may work without it.

## Explicit non-goals

- one global deduplication algorithm;
- automatic universal merging;
- arbitrary foreign data access;
- global customer master;
- universal Person/Company identity resolution;
- hidden workflow engine.

## Tasks

### P47-W01 — Module/profile/provider contracts

- [ ] Create Optional DuplicateDetection module, permissions, persistence, public contracts, provider interfaces, metadata, and docs.
- [ ] Implement code-owned duplicate profile registration.

### P47-W02 — Normalization/fingerprints/candidates

- [ ] Add reusable normalization/fingerprint primitives.
- [ ] Implement typed candidate/result/evidence model.
- [ ] Add deterministic tests without imposing global criteria.

### P47-W03 — Provider configuration

- [ ] Support internal/external provider adapters.
- [ ] Add deterministic fake provider.
- [ ] Add timeout/malformed-result/privacy/secret tests.

### P47-W04 — Configurable policies

- [ ] Implement configurable exact-match and score-band policies.
- [ ] Support allow/warn/block/automatic/human-review outcomes.
- [ ] Prohibit one global threshold.

### P47-W05 — Human review

- [ ] Integrate review Tasks through Work Management when configured.
- [ ] Implement review UI and auditable decision/history.
- [ ] Enforce candidate authorization/privacy.

### P47-W06 — Business handoff/failure behavior

- [ ] Implement typed owner-controlled result contract.
- [ ] Prohibit direct foreign merge/delete/mutation.
- [ ] Implement explicit configured provider-failure behavior.

### P47-W07 — Activation, operational visibility, tests, docs

- [ ] Test module dependency/reduced-mode behavior.
- [ ] Add provider health without data leakage.
- [ ] Add PL/EN/light/dark and authorization/browser coverage.
- [ ] Update architecture/Integrations/WorkManagement/privacy/testing docs.

## Completion criteria

- [ ] Duplicate criteria are profile/owner-specific.
- [ ] Providers/strategies are pluggable.
- [ ] Policies are configurable and may include human review.
- [ ] No global threshold/algorithm exists.
- [ ] DuplicateDetection never directly mutates foreign business records.
- [ ] Provider failures cannot silently become false negatives.
- [ ] Human review is Work Management-integrated and audited.
- [ ] `WORKROAD.md` status is `complete`.
