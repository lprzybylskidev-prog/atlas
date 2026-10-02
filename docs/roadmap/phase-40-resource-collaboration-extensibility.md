# Phase 40 — Optional resource collaboration and extensibility

**Status:** `not started`

## Objective

Add three independently activatable Optional capabilities for resource-level collaboration and controlled extensibility:

1. Comments/Notes;
2. Tags/Labels;
3. Custom Fields.

All three operate only on resource types explicitly registered by their owning module.

They use Phase 37 safe typed resource references and owner-controlled authorization.

They must not use loose cross-module Laravel polymorphic models, foreign Eloquent models, foreign tables, or generic ORM lookup.

They must not become workflow, rules, entity-builder, or dynamic-database systems.

## Dependencies

- Phase 34 extension-point audit.
- Phase 35 module-owned application surfaces.
- Phase 36 localization/reference foundations.
- Phase 37 safe resource references and owner authorization.
- Existing Authorization, Teams, ModuleGate, Audit, Files, Search, Core Exports, localization, DataTable, and privacy foundations.

## Architecture and ownership

Implement three separate Optional modules/capabilities so each can be enabled independently:

- `Comments`;
- `Tags`;
- `CustomFields`.

Do not create one giant generic `ResourceMetadata` module.

Each module owns its own persistence, permissions, UI, Audit events, retention participation, public contracts, and module activation metadata.

A source resource remains owned by its business module.

## Shared source-resource contract

Every attachment to a foreign resource uses:

- owner module;
- stable resource type;
- stable public ID.

The source owner must explicitly register support.

Possessing a reference does not grant access.

Source-resource read authorization is checked through Phase 37 owner-controlled authorization.

Cross-cutting modules do not read foreign tables.

## Comments / Notes

Comments/Notes provide human-authored text associated with a registered resource.

A comment/note contains:

- public ID;
- source resource reference;
- author User reference;
- body;
- created timestamp;
- updated timestamp;
- edit history/version history;
- optional mentions;
- optional Files-owned attachments.

### Visibility

Do not create another per-comment visibility system.

If a user may view the source resource, the user may view its Comments/Notes.

If source-resource access is lost, Comments/Notes access is lost.

Do not add:

- private notes;
- team-only notes;
- restricted-note ACLs;
- hidden managerial notes.

Mutation permissions remain explicit capability permissions in addition to source access.

### Editing/history

Edits preserve history.

Do not silently replace prior text without trace.

Deletion behavior must be explicit, audited, privacy-aware, and must not falsify history.

Use soft removal/tombstone behavior where required by retention/history.

### Mentions

Mentions use stable User identities and Notifications.

A mention never grants access to the source resource.

Do not notify a mentioned user who cannot access the source resource.

### Attachments

Attachments are Files-owned.

Comments stores only safe Files references/association metadata.

Files scanning, authorization, retention, and download controls remain authoritative.

### Search

Comments may contribute authorization-safe Search projections.

Search results must revalidate source-resource access.

Do not index inaccessible attachment content through Comments.

## Tags / Labels

Tags provide optional user-facing classification, not business workflow.

Definitions support:

- public ID;
- immutable stable code;
- localized label;
- optional localized description;
- display color token/value according to accepted UI contract;
- ordering;
- active/inactive lifecycle;
- installation/global scope;
- Team scope;
- Audit metadata.

Both global and Team-scoped tags are supported.

Global tag visibility does not grant global access to tagged resources.

Tag assignment uses typed resource references.

Tag permissions cover:

- definition management;
- assignment/removal.

Source-resource access is always required.

Tags must not:

- execute actions;
- change business status;
- encode hidden workflow;
- replace domain enums/states;
- become a rules engine.

### Search and tables

Registered resource owners may expose tags as Search/filter/DataTable/export dimensions through explicit integrations.

Do not make Tags query arbitrary foreign resource data.

## Custom Fields

Custom Fields provide optional supplementary fields on resource types that explicitly opt in.

A resource type either supports Custom Fields or it does not.

Do not define a second set of field definitions per Team.

Teams may choose operationally whether to populate available fields, but definitions belong to the registered resource type/capability configuration rather than Team-specific schemas.

Supported baseline field types:

- text;
- number;
- date;
- datetime;
- boolean;
- select;
- multi-select.

### Definitions

A definition includes:

- public ID;
- immutable stable code;
- owning registered resource type;
- localized label;
- optional localized help;
- field type;
- required/optional rule where supported by the owner;
- ordering;
- active/inactive lifecycle;
- validation metadata bounded by the supported field type;
- select/multi-select options where applicable;
- Audit metadata.

Definitions are Admin-managed only for resource types that have explicitly registered Custom Field support.

Do not allow Admin to invent resource types.

### Select options

Simple select/multi-select options belong to the Custom Field definition.

Do not force every field option set into Reference Dictionaries.

Use Reference Dictionaries instead when the value is genuinely a reusable business dictionary shared independently of the field.

### Values

Values are stored by Custom Fields against typed resource references.

Use correct database types/normalized representation.

Do not store all values as an unvalidated opaque JSON blob.

Date-only values remain date-only.

Datetime values represent real instants and follow Atlas `timestamptz` rules.

### Validation

Backend validation is authoritative.

The field definition determines supported structural validation.

Custom Fields must not execute arbitrary code or expressions.

### Search and exports

Registered owners may expose accepted Custom Fields to Search and exports.

Search/export access follows source-resource authorization.

Inactive field definitions remain renderable historically where needed.

### History

Maintain value history where required to provide meaningful Audit/history for user changes.

Do not implement universal temporal versioning for every field beyond real requirements.

## Authorization

All three modules require:

1. source-resource authorization from the owner;
2. capability permission for the requested mutation where applicable.

Admin mode does not bypass private business-resource access unless the source owner already grants that access under canonical rules.

## Privacy and retention

All three modules participate in the shared privacy/deletion/anonymization lifecycle.

Deleting/anonymizing a source resource must not leave unauthorized discoverable metadata.

Mention/User identity handling follows User privacy lifecycle.

Files attachments follow Files retention.

## Module activation

Each capability is Optional and independently activatable.

ModuleGate must remove its UI/contributions when inactive.

Required dependencies must block activation/deactivation consistently.

Optional integration with another Optional module must have a tested reduced mode.

## UI

Provide reusable resource-level panels/components where appropriate without forcing one layout on every source module.

PL/EN localization is mandatory.

Light/dark behavior follows the shared design system.

Do not expose internal IDs or raw owner/type codes to normal users.

## Audit

Audit:

- definition creation/change/deactivation;
- comment creation/edit/removal;
- tag definition changes;
- tag assignment/removal where material;
- Custom Field definition changes;
- Custom Field value changes.

Do not duplicate whole sensitive bodies into Audit when structured metadata is sufficient.

## Operational visibility

Health should expose only technical capability state.

Admin operational views must not become a bypass to private comment/custom-field content.

## Explicit non-goals

- loose Laravel polymorphic relations across modules;
- private/restricted comment ACL layers;
- workflow tags;
- dynamic entities;
- dynamic database/schema builder;
- dynamic form platform;
- generic rules engine;
- business status replacement;
- foreign-table access.

## Tasks

### P40-W01 — Resource registration and authorization foundation

- [ ] Define explicit registered resource types and capability opt-in.
- [ ] Integrate Phase 37 typed references and owner authorization.
- [ ] Add architecture guards against foreign Eloquent/table access and loose polymorphism.

### P40-W02 — Comments/Notes

- [ ] Implement persistence, authoring, editing/history, mentions, Files attachments, permissions, Search, Audit, privacy, and UI.
- [ ] Prove visibility exactly follows source-resource visibility.
- [ ] Add negative mention/access tests.

### P40-W03 — Tags/Labels

- [ ] Implement global and Team-scoped tag definitions.
- [ ] Implement localized labels/descriptions, colors, ordering, active/inactive lifecycle, assignment, permissions, Audit, and resource filtering integration.
- [ ] Prove tags never grant source access or execute workflow.

### P40-W04 — Custom Fields definitions

- [ ] Implement registered resource-type opt-in and Admin-managed field definitions.
- [ ] Implement supported field types, localization, validation, ordering, lifecycle, and select options.
- [ ] Prohibit Team-specific schema forks and unknown resource types.

### P40-W05 — Custom Field values

- [ ] Implement typed value persistence and mutation.
- [ ] Add source authorization, Audit/history, Search/export integration, and date/datetime correctness.
- [ ] Preserve historical rendering for inactive definitions where required.

### P40-W06 — Activation, privacy, browser acceptance, and docs

- [ ] Test each Optional module independently active/inactive.
- [ ] Test dependency and reduced-mode behavior.
- [ ] Add PL/EN and light/dark browser coverage.
- [ ] Add privacy/deletion/anonymization coverage.
- [ ] Update canonical module, architecture, security, Search, Files, export, and testing documentation.

## Completion criteria

- [ ] Comments/Notes visibility follows source-resource access with no extra ACL layer.
- [ ] Tags support global and Team scope without becoming workflow.
- [ ] Custom Fields are resource-type opt-in, not Team-specific schemas or a domain-model replacement.
- [ ] All cross-module links use typed owner-controlled references.
- [ ] Files, Search, Audit, privacy, localization, and activation boundaries are correct.
- [ ] No foreign persistence access or loose polymorphic mechanism exists.
- [ ] Documentation/tests are current.
- [ ] `WORKROAD.md` status is `complete`.
