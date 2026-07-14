# Module: <Module Name>

## Purpose

Describe the module's current responsibility and the business or technical boundary it owns.

## Boundaries

### Owns

- Data, invariants, workflows, and public capabilities owned by this module.

### Does not own

- Responsibilities intentionally delegated to other modules or shared capabilities.

## Public API

### Commands

- Command name, input DTO, authorization requirements, result, and errors.

### Queries

- Query name, input DTO, result DTO/page type, scope, ordering, and errors.

### DTOs and value objects

- Public immutable data shapes.

## Integration Events

### Publishes

- Stable event type, schema version, triggering condition, and payload meaning.

### Consumes

- Event type, idempotency behavior, and resulting action.

## Permissions and scope

- Permission keys.
- Team/data scope.
- Module activation requirements.
- Administrative-mode or high-risk requirements.

## Dependencies

- Required modules.
- Optional modules.
- External services and adapters.

## Core workflows and invariants

- Current behavior, precedence, state transitions, and failure handling.

## Conceptual data model

- Main entities, identifiers, relationships, retention, and sensitive data.

## Configuration

- Typed settings, defaults, precedence, and operational impact.

## Administration and operations

- Admin screens, jobs, health/readiness, alerts, retries, and recovery.

## Extension points

- Supported contracts and safe future evolution.

## Related documentation

- Roadmap phases.
- ADRs.
- Shared architecture.
- Operations.
