# ADR-0001: Split the roadmap into a lightweight index and phase files

- **Status:** Accepted
- **Date:** 2026-07-13
- **Decision owners:** Project owner
- **Related phases:** All all Atlas roadmap phases

## Context

The roadmap is intentionally append-only and continues growing throughout Atlas foundation construction and later debt collection system development.

Keeping every implementation contract and checkbox in one mandatory-read `WORKROAD.md` would create an unbounded context and token cost for every agent session, including tasks that touch only one module or phase.

The project still requires complete historical preservation, reliable resumption without chat history, and a fast overview of system evolution.

## Decision

`WORKROAD.md` is a lightweight phase index.

Every phase's binding implementation contract and task history lives in one self-contained file under `docs/roadmap/`.

Agents read the index, then only the phase, module, ADR, architecture, and operations documents relevant to the current task.

Completed tasks and accepted phase history remain append-only.

Current module behavior lives under `docs/modules/`, while roadmap phase files preserve implementation and evolution history.

This restructuring changes physical document location and reading discipline. It does not weaken any implementation contract or acceptance criterion.

## Consequences

### Positive

- Context cost scales with the task instead of total project history.
- Roadmap history remains complete.
- Modules gain canonical current-state documentation.
- Cross-cutting decisions and operations have dedicated canonical locations.
- Future application growth follows the same system.

### Negative / trade-offs

- A complete review requires reading multiple linked files.
- Phase status must remain synchronized with the index.
- Contributors must follow documentation placement rules.
- External conceptual review packages may need the relevant `docs/` files in addition to the three root entry files.

## Alternatives considered

### Keep one monolithic WORKROAD.md and rely on targeted reads

Rejected because the permanent required-reading rule would remain easy to misuse and the single file would grow without structural bounds.

### Replace history with summaries

Rejected because it would lose accepted contracts, completed tasks, and evolution traceability.

### Split only the current Atlas foundation phases

Rejected because the same token-cost problem would reappear during later application development.

## Migration and verification

All existing phase headings, implementation contracts, and checkboxes were moved to `docs/roadmap/phase-*.md`.

The migration was verified at the time of the split by comparing source and destination hashes and checkbox counts. The temporary verification artifact was later removed because the current roadmap index in `WORKROAD.md` and the active phase files under `docs/roadmap/` are the canonical sources.

## Supersedes / superseded by

Supersedes the former rule that all binding roadmap detail lives physically inside `WORKROAD.md`.
