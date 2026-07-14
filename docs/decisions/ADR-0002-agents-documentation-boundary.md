# ADR-0002: Keep AGENTS.md concise and move system specifications to canonical documentation

- **Status:** Accepted
- **Date:** 2026-07-14
- **Related scope:** Repository documentation architecture

## Context

`AGENTS.md` had grown to approximately 2,900 lines and contained detailed specifications for individual modules and system mechanisms, including manager hierarchy, impersonation, TimeTracking, files, reports, deployment, and operational behavior.

This caused three problems:

- every non-trivial agent session paid a large context cost;
- module behavior had multiple potential sources of truth;
- permanent coding instructions were mixed with system specifications relevant only to selected tasks.

## Decision

`AGENTS.md` is the concise permanent engineering constitution of Atlas.

It contains only broadly applicable working, coding, architecture, security, testing, documentation, Git, and Definition of Done rules.

Detailed current behavior moves to canonical documentation:

- module behavior under `docs/modules/`;
- cross-module mechanisms under `docs/architecture/`;
- operational procedures under `docs/operations/`;
- historical implementation contracts under `docs/roadmap/`;
- durable decision reasoning under `docs/decisions/`.

`AGENTS.md` contains a documentation map and requires targeted reading based on task scope.

New detailed system specifications must not be added back to `AGENTS.md`.

## Consequences

- Agent context cost scales with the touched area.
- Module and architecture documentation becomes the canonical current-state source.
- Documentation must be updated with implementation.
- Contributors must identify the affected scope before reading or editing documentation.
- System-wide reviews may still require multiple documents.

## Migration verification

The previous detailed sections were preserved in new English canonical module, architecture, and operations documents.

All local Markdown links resolve, and `AGENTS.md` remains below 700 lines.
