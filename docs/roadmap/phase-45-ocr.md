# Phase 45 — Optional OCR providers and built-in Tesseract

**Status:** `not started`

## Objective

Add an Optional provider-neutral OCR capability for Files-owned documents/images.

OCR runs only on explicit request.

Uploading a qualifying file must not automatically trigger OCR.

Atlas provides:

- a common OCR provider contract;
- provider configuration/selection;
- normalized OCR result storage;
- queued processing;
- failure/retry/health behavior;
- a built-in baseline provider implemented with Tesseract.

Organizations may replace or supplement Tesseract with external/company OCR providers.

## Dependencies

- Files;
- Managed Processes/queues;
- Settings;
- Integrations;
- Search;
- Audit;
- Authorization;
- ModuleGate;
- privacy/retention;
- operational Health.

## Ownership

Create an Optional `OCR` module.

Files remains owner of original files.

OCR owns:

- OCR requests;
- selected provider/profile;
- processing state;
- normalized OCR result;
- page result metadata;
- provider/version metadata;
- failure/retry metadata.

OCR does not duplicate original file bytes as its source of truth.

## Explicit request

OCR starts only through an explicit authorized request from:

- a User;
- a business module;
- another registered capability.

Do not automatically OCR every PDF/image uploaded to Files.

Producer-driven requests must support idempotency.

## Provider registry

Provider types are code-registered.

Configured provider instances use Settings/secret contracts.

Support:

- built-in Tesseract;
- external API;
- company-owned OCR service;
- local custom service;
- future provider adapters.

Do not expose provider secrets.

## Built-in Tesseract provider

Provide a functional baseline Tesseract adapter.

It must use a real supported Tesseract runtime, not a fake production implementation.

Baseline deployment/runtime documentation must include required binary/runtime/language-data installation.

At minimum support the repository's baseline Polish/English operational needs while keeping provider/language design extensible.

Tests may use deterministic fakes separately from production Tesseract.

## Input

OCR source is a Files-owned authorized artifact.

Validate supported MIME/content type and safe availability.

Do not bypass Files quarantine/scanning rules.

## Normalized result

Represent at least:

- request public ID;
- source file reference;
- provider key/version;
- processing timestamps;
- detected/requested language metadata;
- complete normalized text;
- page list;
- page text;
- confidence where provider supplies it;
- optional word/region/bounding-box evidence where provider supplies it;
- warnings;
- terminal status.

Do not fabricate confidence when a provider does not supply meaningful confidence.

Provider-specific raw responses may be retained only when genuinely required, bounded, privacy-classified, and subject to retention.

## Language

Allow an explicit requested language/profile.

Provider capability metadata states supported language behavior.

If provider supports auto-detection, expose detected language as provider output rather than pretending detection is universal.

## Search

OCR text may contribute to Search only under source-file authorization.

Search projection must be removed when source/result retention removes the content.

Do not make OCR Search results a bypass to Files authorization.

## Queue and retries

OCR processing is queued.

Use Managed Processes where user-visible long-running orchestration is warranted.

Define:

- attempts;
- retryable vs terminal failures;
- timeout;
- cancellation where supported;
- idempotent completion.

Do not create another scheduler/process subsystem.

## Privacy and retention

OCR text may contain all sensitive data present in the source.

Treat OCR output at least as sensitively as the source File.

Retention must be linked to source/business policy.

Deleting a source File through authoritative lifecycle must not leave indefinitely discoverable orphan OCR content.

## Authorization

Validate:

- source Files access;
- OCR request permission;
- result access;
- reprocess permission.

Admin Health/Diagnostics cannot read private OCR content merely for operations.

## Audit

Audit user/business requests, provider/config changes, administrative retry/reprocess, and result lifecycle where meaningful.

Do not copy full OCR text into Audit.

## Operational visibility

Health exposes:

- provider configured/available state;
- Tesseract runtime availability;
- queue/failure counts;
- safe latency/error aggregates.

No private recognized text in Health.

## Module activation

OCR is Optional.

When inactive, provider/request capability is unavailable.

Modules/profiles that explicitly require OCR cannot activate/configure that behavior while OCR is inactive.

## Failure behavior

Provider outage/failure leaves source File intact.

Malformed provider responses fail explicitly.

Retry never corrupts the last valid result.

Reprocessing records provider/version lineage.

## Explicit non-goals

- automatic OCR on every upload;
- original Files ownership;
- document classification;
- business field extraction;
- direct business-domain mutation;
- mandatory cloud provider;
- assumption that confidence exists for every provider.

## Tasks

### P45-W01 — Module, request/result contracts

- [ ] Create Optional OCR module, schema, permissions, public contracts, provider interfaces, module metadata, and docs.
- [ ] Define explicit-request/idempotency behavior.
- [ ] Integrate Files authorization.

### P45-W02 — Normalized OCR results

- [ ] Implement result/page/text/language/confidence/evidence model.
- [ ] Implement privacy/retention linkage.
- [ ] Add provider-version lineage.

### P45-W03 — Built-in Tesseract provider

- [ ] Implement real Tesseract adapter.
- [ ] Define runtime/language dependencies.
- [ ] Add deterministic fixtures and real-runtime smoke coverage.

### P45-W04 — External provider configuration

- [ ] Implement provider registry/configuration through Settings/Integrations.
- [ ] Add deterministic fake external provider.
- [ ] Add timeout/retry/malformed-response/secret tests.

### P45-W05 — Queue, Managed Processes, Search, Audit, Health

- [ ] Queue OCR work.
- [ ] Add retries/cancellation where safe.
- [ ] Add authorization-safe Search projection.
- [ ] Add Audit and operational Health without content leakage.

### P45-W06 — UI, activation, browser tests, docs

- [ ] Implement explicit user OCR request/result/reprocess UX where allowed.
- [ ] Add PL/EN/light/dark coverage.
- [ ] Test module activation/dependencies/reduced modes.
- [ ] Update Files/Search/operations/deployment/privacy/testing documentation.

## Completion criteria

- [ ] OCR never runs merely because a file was uploaded.
- [ ] Files remains source owner.
- [ ] Tesseract provides a working built-in baseline.
- [ ] External OCR providers use the same normalized contract.
- [ ] Search/retention/privacy cannot bypass source authorization.
- [ ] Failures/retries/provider lineage are explicit.
- [ ] Deployment requirements are documented for Phase 52 adoption.
- [ ] `WORKROAD.md` status is `complete`.
