# Phase 32 — Product neutralization and domain-assumption removal

**Status:** `not started`

## Objective

Make the live Atlas repository a product-neutral modular business application foundation rather than a debt-collection-specific product.

Atlas historically started as a debt collection system. That product direction is no longer the canonical identity of the current foundation.

This phase removes current product-specific naming, metadata, examples, UI copy, demo assumptions, documentation language, and hidden Core/Optional/shared dependencies that incorrectly imply that Atlas itself owns a debt-collection domain.

The result must describe Atlas as a reusable modular business application foundation/platform on which concrete business domains are implemented under `Application/*`.

Historical completed roadmap phases remain historical evidence and must not be rewritten.

Phase 31 also remains historical implementation history once completed and must not be rewritten as part of this cleanup.

## Dependencies

- Phase 31 must be complete.
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

## Tasks

### P32-W01 — Inventory current product-specific assumptions

- [ ] Search current root files, canonical docs, source, UI, translations, configuration, metadata, seeds, fixtures, and examples for debt-collection-specific identity or coupling.
- [ ] Classify findings as historical, Application-owned, current canonical identity, or forbidden foundation coupling.
- [ ] Record the exact cleanup set before mutation.

### P32-W02 — Root metadata and canonical identity

- [ ] Neutralize current `AGENTS.md`, `README.md`, `WORKROAD.md`, `composer.json`, package description/keywords, and other current root metadata.
- [ ] Preserve historical roadmap records.
- [ ] Ensure root entry points consistently describe Atlas as a modular business application foundation/platform.

### P32-W03 — Core/Optional/shared coupling repair

- [ ] Remove any discovered debt-collection-specific dependency from Core, Optional, or shared foundation code.
- [ ] Move genuine domain ownership to an appropriate Application boundary only when such domain code actually exists.
- [ ] Add boundary regression coverage for repaired leaks.

### P32-W04 — UI, localization, demo, seed, and example cleanup

- [ ] Replace current product-specific copy and examples with neutral equivalents.
- [ ] Update Polish and English translations consistently.
- [ ] Keep demos useful for authorization, Teams, modules, files, Search, notifications, and other current features.

### P32-W05 — Canonical documentation cleanup

- [ ] Update current architecture/module/operations/testing/development documentation.
- [ ] Do not edit completed historical roadmap files.
- [ ] Ensure future roadmap documents use product-neutral terminology.

### P32-W06 — Verification

- [ ] Search again for debt-collection-specific terms and inspect all remaining matches.
- [ ] Verify every remaining match is intentionally historical or Application-domain-owned.
- [ ] Run architecture/documentation/localization/test gates affected by the cleanup.
- [ ] Verify no Core/Optional/shared dependency on a product-specific domain remains.

## Completion criteria

- [ ] Current Atlas identity is product-neutral.
- [ ] Root metadata and canonical documentation describe a modular business application foundation/platform.
- [ ] Core, Optional, and shared foundations contain no hidden debt-collection domain dependency.
- [ ] Application remains the location for concrete business domains.
- [ ] Current PL/EN UI/demo/example copy is neutral.
- [ ] Completed historical phases and Phase 31 were not rewritten.
- [ ] Remaining product-specific terminology is intentionally historical or Application-domain-owned.
- [ ] Permanent guards cover any concrete boundary leak discovered by this phase.
- [ ] `WORKROAD.md` status is updated to `complete`.
