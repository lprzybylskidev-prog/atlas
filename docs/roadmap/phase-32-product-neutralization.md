# Phase 32 — Product neutralization and domain-assumption removal

**Status:** `complete`

## Objective

Make the live Atlas repository a product-neutral modular business application foundation rather than a debt-collection-specific product.

Atlas historically started as a debt collection system. That product direction is no longer the canonical identity of the current foundation.

This phase removes current product-specific naming, metadata, examples, UI copy, demo assumptions, documentation language, and hidden Core/Optional/shared dependencies that incorrectly imply that Atlas itself owns a debt-collection domain.

The result must describe Atlas as a reusable modular business application foundation/platform on which concrete business domains are implemented under `Application/*`.

Historical completed roadmap phases remain historical evidence and must not be rewritten.

Phase 31 also remains historical implementation history once completed and must not be rewritten as part of this cleanup.

## Dependencies

- Phase 31 implementation baseline through `P31-W11`; the repository owner explicitly authorized Phase 32 to execute before the remaining Phase 31 packages.
- Existing modular-monolith architecture.
- Existing Core / Optional / Application module classification.
- Existing architecture tests and module-boundary guards.
- Current canonical root and `docs/` documentation.

## Related documentation

- `AGENTS.md`
- `README.md`
- `WORKROAD.md`
- `composer.json`
- Architecture: `../architecture/modular-monolith.md`
- Module documentation index under `../modules/`
- Current canonical operations, development, testing, and deployment documentation.

## Implementation contract

### Canonical product identity

The canonical live description of Atlas must become product-neutral.

Use terminology equivalent to:

`Atlas is a modular business application foundation/platform for building secure, auditable, extensible business systems.`

Do not describe the foundation itself as:

- a debt collection system;
- a debt recovery system;
- a debtor management system;
- a collections platform;
- or another specific business vertical.

Concrete future business products may use Atlas as their foundation and may own debt collection or any other domain under `Application/*`.

### Current-state cleanup only

Update current/live repository state, including where applicable:

- `AGENTS.md`;
- `README.md`;
- `WORKROAD.md`;
- `composer.json`;
- package description and keywords;
- current architecture documentation;
- current module documentation;
- current operations/development/testing documentation;
- UI copy;
- navigation labels where domain-specific;
- demo data;
- seed descriptions;
- examples;
- comments or metadata that incorrectly define Atlas itself as debt-collection-specific.

Do not rewrite completed roadmap phase files.

Do not rewrite historical implementation contracts.

Do not rewrite past phase rationale merely because terminology changed later.

### Domain-assumption inventory

Search the current repository for terms and concepts including at least:

- `debt`;
- `debtor`;
- `collection`;
- `collections`;
- `debt collection`;
- `debt recovery`;
- domain-specific customer/debtor assumptions;
- product-specific role, example, fixture, seed, route, permission, or namespace naming that leaked into shared foundations.

Classify every match as:

1. historical and intentionally preserved;
2. legitimate Application-domain content;
3. current canonical product identity requiring neutralization;
4. hidden Core/Optional/shared domain coupling requiring repair.

Do not perform blind search-and-replace when a word has another legitimate technical meaning.

### Foundation ownership audit

Core, Optional, and shared infrastructure must remain business-domain neutral.

They must not depend on debt-collection-specific:

- entities;
- tables;
- schemas;
- permissions;
- events;
- statuses;
- services;
- DTOs;
- routes;
- seeds;
- terminology;
- configuration.

Where a business domain is required, it belongs under `Application/*` and communicates through accepted public contracts.

### Application boundary

Preserve `Application/*` as the location for real product/business domains.

Do not create a generic dynamic entity system as part of neutralization.

Do not move genuine reusable infrastructure into Application merely to hide a naming problem.

### UI and localization

User-facing current product/foundation copy must be neutral in Polish and English.

Do not leave translation keys or fallback copy implying that Atlas itself is debt-collection-specific.

Historical roadmap Markdown is excluded.

### Demo data and examples

Foundation demo data must demonstrate generic users, Teams, files, tasks/capabilities, settings, and technical behavior without requiring a debt-collection story.

Examples in developer documentation must use neutral domains unless a deliberately labeled example module demonstrates an Application-domain extension.

### Architecture guards

Add or extend permanent architecture/documentation guards where practical so Core/Optional/shared foundation namespaces and canonical product metadata cannot silently regain known product-specific debt-collection dependencies.

Do not create brittle guards that ban legitimate words in historical files or Application modules.

### Search and generated artifacts

Search indexes, generated docs, cached frontend dictionaries, generated API docs, or other checked-in/generated artifacts must be rebuilt or updated where the canonical source changed.

### Privacy and security

Neutralization must not weaken:

- authorization;
- Audit;
- retention;
- privacy;
- module boundaries;
- secrets handling;
- existing production/runtime safeguards.

## Explicit non-goals

- Do not rewrite completed roadmap files.
- Do not rewrite Phase 31.
- Do not implement a business domain.
- Do not create Customer, Debtor, Person, Company, Case, Contract, or another universal business entity.
- Do not create a dynamic entity builder.
- Do not create a workflow/rules engine.
- Do not rename the permanent PHP root namespace `App`.
- Do not replace Atlas with a new product name.
- Do not alter existing business-neutral functionality merely for aesthetic refactoring.

## Implementation record

Phase 32 was completed on 2026-10-02 at the repository owner's explicit direction while Phase 31 remained in progress. This is a sequencing override, not a rewrite of Phase 31: no Phase 31 contract or completed historical roadmap file was changed.

The pre-mutation inventory classified the cleanup set as follows:

- historical and intentionally preserved: completed roadmap phase files, architectural decision records, Phase 31, and the roadmap's product-repositioning rationale;
- legitimate Application-owned product content: none, because the repository does not yet contain a concrete product-domain module under `Application/*`;
- current canonical identity requiring neutralization: `AGENTS.md`, `README.md`, the live `WORKROAD.md` purpose, Composer metadata, modular-monolith and frontend guidance, Chat lifecycle wording, PL/EN authentication and mail branding, and Admin example placeholders;
- forbidden foundation leakage requiring repair: debtor/collections identifiers in Optional Imports and Managed Processes fixtures, plus debt-collection examples in Integrations, Search, Privacy, Calendar, TimeTracking, Authorization, Chat, mail, and browser-test fixtures;
- legitimate technical matches: PHP/Laravel collection types and result collections, Polish words such as `dłuższe`, the Phase 32 contract itself, and the later final-verification assertion;
- constitutionally excluded non-canonical owner material: `CHATGPT_PROMPT.md`, which `AGENTS.md` explicitly excludes from ordinary repository work unless the user requests that file specifically; it was neither read nor changed.

No runtime entity, table, schema, permission, event, service, route, or configuration dependency on a product-specific domain was found. The repaired leaks were examples and deterministic fixture identifiers rather than a hidden runtime business implementation. Existing module-boundary architecture tests remain the durable protection against business-domain coupling in Shared, Core, and Optional code. Phase 32 deliberately does not add a lexical terminology blacklist: product-specific words may be legitimate in future `Application/*` domains, historical records, technical prose, or deliberately labeled examples.

## Tasks

### P32-W01 — Inventory current product-specific assumptions

- [x] Search current root files, canonical docs, source, UI, translations, configuration, metadata, seeds, fixtures, and examples for debt-collection-specific identity or coupling.
- [x] Classify findings as historical, Application-owned, current canonical identity, or forbidden foundation coupling.
- [x] Record the exact cleanup set before mutation.

### P32-W02 — Root metadata and canonical identity

- [x] Neutralize current `AGENTS.md`, `README.md`, `WORKROAD.md`, `composer.json`, package description/keywords, and other current root metadata.
- [x] Preserve historical roadmap records.
- [x] Ensure root entry points consistently describe Atlas as a modular business application foundation/platform.

### P32-W03 — Core/Optional/shared coupling repair

- [x] Remove any discovered debt-collection-specific dependency from Core, Optional, or shared foundation code.
- [x] Move genuine domain ownership to an appropriate Application boundary only when such domain code actually exists.
- [x] Verify existing module-boundary regression coverage remains sufficient; no runtime boundary leak requiring a new guard was discovered.

### P32-W04 — UI, localization, demo, seed, and example cleanup

- [x] Replace current product-specific copy and examples with neutral equivalents.
- [x] Update Polish and English translations consistently.
- [x] Keep demos useful for authorization, Teams, modules, files, Search, notifications, and other current features.

### P32-W05 — Canonical documentation cleanup

- [x] Update current architecture/module/operations/testing/development documentation.
- [x] Do not edit completed historical roadmap files.
- [x] Ensure future roadmap documents use product-neutral terminology.

### P32-W06 — Verification

- [x] Search again for debt-collection-specific terms and inspect all remaining matches.
- [x] Verify every remaining in-scope match is intentionally historical, guard-owned, or Application-domain-owned.
- [x] Run architecture/documentation/localization/test gates affected by the cleanup.
- [x] Verify no Core/Optional/shared dependency on a product-specific domain remains.

## Completion criteria

- [x] Current Atlas identity is product-neutral.
- [x] Root metadata and canonical documentation describe a modular business application foundation/platform.
- [x] Core, Optional, and shared foundations contain no hidden debt-collection domain dependency.
- [x] Application remains the location for concrete business domains.
- [x] Current PL/EN UI/demo/example copy is neutral.
- [x] Completed historical phases and Phase 31 were not rewritten.
- [x] Remaining in-scope product-specific terminology is intentionally historical or Application-domain-owned.
- [x] Existing module-boundary guards remain authoritative; no brittle terminology ban was added.
- [x] `WORKROAD.md` status is updated to `complete`.
