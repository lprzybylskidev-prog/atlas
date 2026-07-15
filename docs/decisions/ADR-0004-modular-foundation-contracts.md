# ADR-0004: Establish explicit modular foundation contracts

- **Status:** Accepted
- **Date:** 2026-07-15
- **Decision owners:** Project owner
- **Related phases:** [Phase 5](../roadmap/phase-05-modular-architecture.md)

## Context

Atlas needs durable module boundaries before large debt collection workflows are added.

The foundation must support reliable Integration Events, explicit deployed module registration, module access decisions, public contracts, and operational safeguards without introducing hidden scanning or distributed-service complexity.

## Decision

Atlas uses explicit shared foundation contracts for the modular monolith skeleton:

- deployed modules are listed in `config/modules.php` and represented by `ModuleDefinition`;
- `ModuleRegistry` validates duplicate keys, missing required dependencies, dependency cycles, and startup order during application registration;
- reliable Integration Events are recorded through the shared transactional Outbox and published only by the relay;
- consumers deduplicate processing by stable event ID and consumer name;
- `ModuleGate` is the central source of truth for deployed availability, dependencies, activation state, active team, and effective permission;
- modules expose synchronous cross-module APIs only through `Application/Public`;
- public Query results use framework-independent DTO result shapes;
- modules participate in deactivation safety checks and deletion/anonymization through typed shared contracts;
- module UI and runtime contributions use explicit contracts for menu, permissions, breadcrumbs, health checks, scheduler, and frontend entrypoints.

Directory or namespace scanning is not used for module discovery.

## Consequences

### Positive

- module ownership remains visible and testable;
- invalid deployed-module configuration fails early;
- Integration Event publication is reliable and auditable;
- public contracts stay framework-independent;
- future modules can plug into shared Admin/UI/runtime surfaces through typed declarations.

### Negative / trade-offs

- each new module must declare its manifest and contributions explicitly;
- the foundation adds upfront structure before all consumers exist;
- architecture tests and documentation must evolve with module conventions.

## Alternatives considered

- Directory scanning for modules: rejected because it hides ownership and startup behavior.
- Direct event dispatch from use cases: rejected because it cannot guarantee after-commit publication.
- Laravel paginator and collection types in public contracts: rejected because they leak framework and persistence concerns across module boundaries.

## Migration or implementation notes

- Phase 5 introduces the first Identity module manifest and shared contracts.
- Later phases implement concrete Admin/runtime consumers for the contribution contracts.
- Permission/module-gated UI e2e scenarios remain pending until the corresponding authorization surfaces are available.

## Supersedes / superseded by

- Complements [ADR-0003](ADR-0003-modular-monolith-and-single-repository.md).
