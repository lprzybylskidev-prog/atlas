# ADR-0003: Build Atlas as a DDD modular monolith in one repository

- **Status:** Accepted
- **Date:** 2026-07-14
- **Decision owners:** Project owner
- **Related phases:** Phase 0, Phase 5, later application modules

## Context

Atlas is a large debt collection system that must support sensitive business workflows, strict authorization, auditability, operational recovery, and long-term module growth.

The project needs clear ownership boundaries without the deployment and distributed-systems cost of microservices during the initial foundation and business-module development.

## Decision

Atlas is developed as one continuously evolving system in one repository.

The application uses a DDD-oriented modular monolith:

- modules own their Domain, Application, Infrastructure, Presentation, data, permissions, settings, and events;
- Domain code remains framework-independent;
- cross-module synchronous access uses typed public Application contracts;
- cross-module asynchronous communication uses versioned Integration Events;
- reliable Integration Events use the transactional Outbox;
- debt collection business rules belong to `Application` modules, not shared Core or technical modules.

The permanent PHP root namespace is `App`.

## Consequences

Positive:

- module boundaries are explicit while deployment remains simple;
- cross-module contracts can evolve atomically inside one repository;
- shared security, audit, operations, and UI foundations stay consistent;
- future business modules can be added without introducing early network boundaries.

Trade-offs:

- architecture tests and review discipline are required to prevent accidental coupling;
- module boundaries must be enforced by code, tests, and documentation;
- splitting a module into a separate service later would require an explicit ADR and migration plan.

## Alternatives considered

### Microservices from the beginning

Rejected because the initial system needs strong internal consistency and a stable foundation more than independent service deployment.

### Conventional layered Laravel application without module boundaries

Rejected because it would make ownership, authorization, audit, and later debt collection workflows harder to maintain.

### Separate repositories per module

Rejected because Atlas modules are part of one product and should evolve through atomic commits, shared quality gates, and one deployable system.
