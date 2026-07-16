# Phase 14a — PostgreSQL module schemas

**Status:** `implementation complete, pending commit`

## Objective

Move Atlas-owned database tables out of the default `public` schema and into explicit PostgreSQL schemas owned by modules or shared infrastructure before new module tables are added in Phase 15 and later phases.

## Dependencies

- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Atlas modular-monolith architecture](../architecture/modular-monolith.md)
- [Quality gates and git workflow](../operations/quality-gates-and-git.md)

## Related documentation

- Architecture: `../architecture/modular-monolith.md`

## Implementation contract

- PostgreSQL schema ownership is part of module ownership.
- Existing Atlas-owned tables currently in `public` are pre-production foundation debt and must be moved before Phase 15 creates notification and realtime persistence.
- `public` remains allowed only for documented PostgreSQL/Laravel bootstrap objects, extension metadata, and explicitly documented transitional compatibility.
- Every Atlas-owned table must have a documented owner schema.
- Schema names use stable lowercase `snake_case` names and match the owning module or shared infrastructure boundary.
- Migrations create required schemas explicitly before creating schema-qualified tables.
- Table names, foreign keys, indexes, raw SQL, Eloquent `$table` definitions, package configuration, seeders, factories, and tests use schema-qualified names for Atlas-owned tables.
- Application code must not rely on PostgreSQL `search_path` for Atlas-owned tables.
- PostgreSQL schema separation must not be used to bypass module boundaries:
  - modules still cannot query or mutate another module's schema directly;
  - cross-module reads use `Application/Public` contracts;
  - cross-module asynchronous behavior uses Integration Events;
  - cross-schema foreign keys require an explicit architecture allowance and do not grant direct SQL access.
- Because Atlas has not reached first production deployment, existing migrations may be edited in place. If production deployment is marked complete before this phase starts, implement the move through forward-only migrations instead.
- The implementation must include a guard test or static architecture check that fails when a new Atlas-owned table is created in `public` without an explicit allowlist entry.

## Tasks

- [x] Inventory every current PostgreSQL table, package-owned table, shared infrastructure table, and framework bootstrap table.
- [x] Document the final table-to-schema ownership map in `docs/architecture/modular-monolith.md`.
- [x] Add a shared schema creation helper or explicit migration convention that keeps schema creation deterministic.
- [x] Update existing pre-production migrations to create schemas and schema-qualified Atlas-owned tables.
- [x] Update foreign keys, indexes, unique constraints, and raw SQL references to use schema-qualified names.
- [x] Update Eloquent models, query builders, Spatie Permission configuration, Laravel runtime table configuration, seeders, factories, feature tests, integration tests, and e2e setup references.
- [x] Keep `public` limited to the documented allowlist.
- [x] Add an automated architecture guard against unqualified Atlas-owned table creation.
- [x] Run the required backend, frontend, and migration quality gates.
- [ ] Commit PostgreSQL module schema ownership foundation.

## Completion criteria

- [x] No Atlas-owned module table remains in `public` except documented transitional allowlist entries.
- [x] All Atlas-owned persistence references work from schema-qualified names without relying on PostgreSQL `search_path`.
- [x] Module boundary rules remain enforced at the code and database documentation levels.
- [x] Relevant automated tests and quality gates pass.
- [x] Roadmap and architecture documentation are current.
