# Phase 46 — Optional document extraction provider orchestration and human review

**Status:** `not started`

## Objective

Add an Optional provider-neutral orchestration capability for structured extraction from documents.

Atlas does not implement one universal Structured Document Extraction algorithm.

Atlas provides the machine into which organizations plug their own extractors.

Supported extractor examples include:

- company regex/rule scripts;
- deterministic parsers;
- company-owned AI models;
- local inference services;
- external AI/SaaS providers;
- specialized document-processing products.

The capability standardizes input, profile/schema, execution, normalized proposed results, evidence, decision policies, human review, and validated handoff.

## Dependencies

- Files;
- Settings;
- Integrations;
- Managed Processes;
- Audit;
- Authorization;
- ModuleGate;
- privacy/retention;
- Phase 37 typed references;
- optional Phase 45 OCR;
- optional Phase 42 Work Management for human review.

## Ownership

Create an Optional `DocumentExtraction` module.

It owns:

- extractor provider configuration;
- extraction profiles;
- extraction requests;
- normalized proposed results;
- confidence/evidence;
- decision-policy evaluation;
- human-review state/history;
- validated result handoff state.

It does not own the source File.

It does not own the target business entity.

## Code-owned extraction profile/schema

A business module registers a stable extraction profile.

The profile defines:

- owner module;
- stable profile key;
- schema version;
- expected output fields/types;
- required/optional fields;
- validation rules that belong at the extraction contract boundary;
- supported input modes;
- allowed consumer/handoff contract;
- whether automatic acceptance is allowed;
- fields considered critical where applicable.

Admin cannot invent arbitrary target business schemas unknown to code.

## Provider configuration

Provider types are code-registered.

Admin/runtime configuration may map a registered extraction profile to a configured provider instance and policy.

Provider adapters may represent:

- regex/parser code;
- HTTP service;
- AI service;
- local model;
- company service.

Provider implementation method is opaque to the orchestration layer.

## Input modes

A provider/profile may declare one or more supported input modes:

- Files artifact;
- OCR result/text;
- page images;
- another explicit normalized input supported by the registered contract.

OCR is not a universal required dependency.

If a configured profile requires OCR input:

- OCR must be active;
- the profile cannot become operational while OCR is inactive/unavailable.

A provider that accepts the original File can operate without OCR.

## Normalized result

A result contains at least:

- request/profile/schema version;
- provider identity/version;
- source File reference;
- proposed fields;
- typed normalized values;
- overall confidence if meaningful;
- per-field confidence if meaningful;
- source evidence where available;
- warnings;
- validation result;
- timestamps;
- decision state.

Do not fabricate confidence.

## Source evidence

Evidence may include:

- OCR page/region reference;
- source page;
- quoted bounded source fragment;
- provider evidence token/reference;
- deterministic rule reference.

Evidence must be privacy-safe and authorized with the source.

## Decision policies

Policy is configurable per registered extraction profile.

Support exactly these baseline modes:

### `automatic_after_validation`

A valid result may proceed automatically after schema/profile validation and explicit business-consumer validation.

Confidence is not required unless the profile separately requires it.

### `confidence_gated`

Policy may define:

- overall threshold;
- per-field thresholds;
- critical fields requiring threshold;
- conditions that route to human review.

If required confidence is absent, route to review rather than inventing confidence.

### `always_human_review`

Every result requires human review.

## Business handoff

The extraction module never writes directly to foreign business tables.

A business module that accepts an extraction result exposes an explicit framework-independent consumer/use-case contract.

Automatic handoff is allowed only when:

- the profile allows automatic acceptance;
- extraction/schema validation succeeds;
- configured decision policy succeeds;
- business-consumer validation succeeds;
- authorization/idempotency requirements succeed.

The business module remains owner of the final mutation.

## Human review

Do not create a separate global Human Review Queue module.

DocumentExtraction owns its review UI and review result.

Work Management provides the work item/queue when active and configured.

A review Task points to a typed action target owned by DocumentExtraction.

Reviewer sees authorized:

- source document;
- proposed fields;
- confidence;
- source evidence;
- validation issues.

Reviewer may:

- accept;
- correct;
- reject.

## Review Audit/history

Preserve:

- original provider result;
- provider/version;
- original confidence/evidence;
- every human correction;
- before/after;
- reviewer;
- timestamps;
- final decision.

Never overwrite the original proposed result as if the provider had returned the corrected values.

This history may support future provider-quality analysis.

It must not become employee productivity scoring.

## Work Management dependency

Work Management is an optional module dependency.

Profiles requiring human review cannot become operational unless Work Management is active.

Automatic-only profiles may continue to operate without Work Management.

## Queue/retries

Extraction execution is queued where appropriate.

Use Managed Processes for long-running user-visible operations.

Provider retry must be idempotent and must not create duplicate business mutations.

## Search

Do not globally index all proposed extraction values by default.

Source business modules decide which accepted values enter their Search projection.

## Privacy/retention

Extraction results may contain sensitive source data.

Retention follows source/business policy.

Rejected/obsolete proposals must not remain indefinitely searchable.

Admin diagnostics expose technical metadata, not private extracted fields, unless separately authorized.

## Provider health

Health exposes safe provider availability/latency/error state.

No private payload/result leakage.

## Module activation

DocumentExtraction is Optional.

Provider/profile dependency validation must account for optional OCR and Work Management.

## Explicit non-goals

- Atlas-built universal extraction algorithm;
- universal invoice/contract/customer schema;
- direct AI writes to business tables;
- mandatory OCR;
- global Human Review Queue module;
- employee scoring;
- workflow engine;
- dynamic entity/database builder.

## Tasks

### P46-W01 — Module, profiles, schemas, provider contract

- [ ] Create Optional DocumentExtraction module, permissions, schema, module metadata, public contracts, and docs.
- [ ] Implement code-owned profile/schema registration.
- [ ] Implement provider registry/configuration.

### P46-W02 — Input orchestration

- [ ] Support File and OCR input modes.
- [ ] Validate optional OCR dependency per configured profile.
- [ ] Add input authorization/idempotency.

### P46-W03 — Normalized extraction result and evidence

- [ ] Implement typed proposed values, confidence, evidence, warnings, and schema validation.
- [ ] Preserve provider/version lineage.
- [ ] Add malformed-provider-result tests.

### P46-W04 — Decision policies

- [ ] Implement automatic-after-validation.
- [ ] Implement confidence-gated overall/per-field/critical-field thresholds.
- [ ] Implement always-human-review.
- [ ] Route missing required confidence to review.

### P46-W05 — Human review and Work Management

- [ ] Implement extraction-owned review UI/lifecycle.
- [ ] Create review Tasks through Work Management when policy requires.
- [ ] Implement accept/correct/reject.
- [ ] Preserve full provider-versus-human history and Audit.

### P46-W06 — Explicit business handoff

- [ ] Implement owner-registered business result consumer contracts.
- [ ] Enforce validation, authorization, and idempotency.
- [ ] Prohibit direct foreign persistence mutation.

### P46-W07 — Queue, providers, privacy, Health

- [ ] Integrate Managed Processes/queues/retry.
- [ ] Add external/local provider test adapters.
- [ ] Add retention/privacy/Health/secret-safe failure coverage.

### P46-W08 — UI, activation, browser tests, docs

- [ ] Add Admin provider/profile/policy configuration UI.
- [ ] Add reviewer UI coverage.
- [ ] Add PL/EN/light/dark and module dependency tests.
- [ ] Update canonical architecture/Files/OCR/WorkManagement/Integrations/privacy/testing documentation.

## Completion criteria

- [ ] Atlas orchestrates extractors but does not implement a universal extraction algorithm.
- [ ] Different profiles can use different providers/input modes.
- [ ] OCR is required only for profiles that need it.
- [ ] Decision policy supports automatic, confidence-gated, and always-review modes.
- [ ] Human corrections preserve original provider output and full Audit.
- [ ] Business-domain mutation occurs only through explicit owner contracts.
- [ ] Work Management supplies review work rather than a second review queue platform.
- [ ] `WORKROAD.md` status is `complete`.
