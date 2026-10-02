# Phase 44 — Optional document templates and document generation

**Status:** `not started`

## Objective

Add an Optional provider-neutral Document Generation capability with Admin-managed, versioned templates for business documents and business email content.

Business modules own document meaning and context data.

Document Generation owns template lifecycle, validation, rendering orchestration, preview, generation lifecycle, and Files artifact creation.

It must not know domain entities such as Invoice, Complaint, Customer, Contract, Case, or another Application-specific model.

## Dependencies

- Files;
- Audit;
- Authorization;
- Settings/Localization;
- Managed Processes;
- Notifications;
- ModuleGate;
- Core Exports/PDF/browser rendering foundations where reusable;
- Phase 37 business numbering and safe references where needed.

## Ownership

Create an Optional `DocumentGeneration` module.

The module owns:

- template metadata;
- template versions;
- template activation;
- registered template/profile definitions;
- generation requests;
- generation status/history;
- rendering provider configuration.

Files owns:

- uploaded binary template source files where applicable;
- generated DOCX/PDF artifacts;
- images/brand assets where stored through Files.

## Code-owned template definitions

A business/source module registers a stable template definition/profile.

The definition owns:

- stable template key;
- owner module;
- supported template kind/output;
- allowed/required context schema;
- merge fields;
- localization behavior;
- authorization contract;
- allowed generation modes.

Admin may create/manage template instances and versions only for registered definitions.

Admin may not invent arbitrary executable merge-field contracts unknown to code.

## Admin-managed templates

Provide Admin UI for:

- template instance creation;
- version upload/edit according to template kind;
- draft/active/inactive lifecycle;
- preview;
- version comparison/metadata;
- localization;
- company identity/brand selection where configured;
- permission-protected activation.

A version used for a generated final artifact is immutable.

Editing creates a new version.

## Supported content

Baseline capability supports:

- DOCX template generation;
- PDF generation;
- email subject templates;
- email text/HTML body templates;
- merge fields;
- tables;
- lists;
- images;
- company identity/brand values;
- locale-aware values.

Do not execute arbitrary PHP/JS/template code supplied by Admin.

Template syntax must be sandboxed/bounded to supported merge features.

## Context

Business modules supply context data through framework-independent public contracts.

Document Generation validates context against the registered template definition before rendering.

It must not query the business module's tables.

Generation requests capture enough immutable context/version/locale information to make queued rendering deterministic.

Do not persist unnecessary sensitive source data after the artifact lifecycle no longer requires it.

## Numbering

Registered templates may accept business numbers issued through Phase 37 sequences.

Where a number must appear in the final document, issue/resolve it before final rendering.

Issued numbers are immutable.

Document Generation does not create a second sequence engine.

## Preview

Preview must clearly distinguish non-final preview from final generated artifact.

Preview must not accidentally issue irreversible business numbers unless the owner explicitly requests a real issuance.

Use non-consuming sequence preview for formatting where appropriate.

## Queued and batch generation

Small preview may be synchronous when safe.

Heavy, batch, or final generation uses existing queues/Managed Processes as appropriate.

Do not create another job/process platform.

Provide:

- progress;
- retry;
- deterministic failure state;
- partial-batch result where relevant;
- cancellation where safe.

## Files

Final files are Files-owned.

Generation stores only stable file references and generation metadata.

Files scanning/download/retention/privacy remains authoritative.

## Authorization

Validate:

- actor permission;
- template definition permission;
- template/version availability;
- source-module generation authorization.

Do not assume Admin can generate private business documents without source authorization.

## Audit

Audit:

- template definition/instance management;
- version activation;
- generation request;
- generation completion/failure;
- final artifact creation;
- administrative regeneration/retry.

Do not write full sensitive document bodies into Audit.

## Search

Generated artifacts may participate in Search through Files and source-module projections.

Do not create duplicate full-text indexing of the same artifact unless explicitly required.

## Retention

Generated artifact retention follows Files plus source-module business retention where applicable.

Template versions referenced by retained artifacts must remain resolvable as historical metadata even if inactive.

## Notifications

Use Notifications for meaningful asynchronous generation completion/failure when the initiating workflow requires it.

Avoid duplicate notifications with Managed Processes.

## Provider-neutral rendering

Rendering is behind capability-specific infrastructure contracts.

Concrete libraries/rendering tools remain Infrastructure details as long as the accepted output/security/determinism contract is preserved.

Support deterministic fake/test renderer adapters.

## Module activation

DocumentGeneration is Optional.

A consumer that requires it cannot activate/configure the relevant capability without it.

Inactive module removes routes/UI/provider availability safely.

## Localization

Templates may be localized.

PL/EN management/presentation is required.

Template locale selection must be explicit/deterministic for queued work.

## Operational visibility

Health exposes renderer/runtime availability and safe queue/failure summaries.

Do not expose rendered private content in health/diagnostics.

## Failure behavior

Rendering failures produce explicit failed state with safe error metadata.

Do not create a final successful artifact on partial render failure.

Retry must not accidentally allocate a second immutable business number when retrying the same idempotent generation request.

## Explicit non-goals

- business-domain ownership;
- arbitrary executable templates;
- WYSIWYG low-code application builder;
- automatic business workflow;
- duplicate Files storage layer;
- duplicate Managed Processes;
- generic form/entity builder.

## Tasks

### P44-W01 — Module boundary, definitions, contracts

- [ ] Create Optional DocumentGeneration module, schema, permissions, module metadata, public contracts, and docs.
- [ ] Define code-owned template definitions and typed context schema contracts.
- [ ] Add architecture/security guards.

### P44-W02 — Template lifecycle and versioning

- [ ] Implement Admin-managed template instances/versions.
- [ ] Implement immutable used versions, activation/deactivation, localization, company identity integration, and Audit.
- [ ] Integrate Files-owned template assets where applicable.

### P44-W03 — Rendering foundation

- [ ] Implement bounded merge fields, tables, lists, images, and locale-aware formatting.
- [ ] Implement DOCX and PDF generation.
- [ ] Add deterministic test adapter and failure coverage.

### P44-W04 — Email templates

- [ ] Add versioned subject and text/HTML email template kinds.
- [ ] Reuse the same definition/context/versioning model.
- [ ] Do not create a separate email-template engine.

### P44-W05 — Preview, queued/batch generation, numbering

- [ ] Implement safe preview.
- [ ] Integrate Managed Processes/queues for heavy and batch work.
- [ ] Integrate business sequence numbers without double issuance on retry.
- [ ] Persist deterministic generation snapshots.

### P44-W06 — Files, privacy, Search, Notifications, operational visibility

- [ ] Create Files-owned final artifacts.
- [ ] Add retention/privacy lifecycle.
- [ ] Add Search/Notifications only through accepted ownership.
- [ ] Add renderer health and safe failures.

### P44-W07 — UI, browser tests, docs, closure

- [ ] Implement Admin template management and consumer-facing generation UX.
- [ ] Add PL/EN, responsive, light/dark coverage.
- [ ] Add authorization, Audit, queue/retry, batch, version, Files, retention, and provider tests.
- [ ] Update canonical documentation.

## Completion criteria

- [ ] Templates are Admin-managed but template/context contracts remain code-owned.
- [ ] DOCX/PDF/email use one coherent versioned template capability.
- [ ] Business modules provide data; DocumentGeneration renders it.
- [ ] Final artifacts are Files-owned.
- [ ] Queues/Managed Processes are reused.
- [ ] Retry cannot duplicate immutable numbers/artifacts incorrectly.
- [ ] No business-domain or low-code workflow ownership leaks into the module.
- [ ] `WORKROAD.md` status is `complete`.
