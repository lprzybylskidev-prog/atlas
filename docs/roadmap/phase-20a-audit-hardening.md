# Phase 20a — Audit context and security category hardening

**Status:** `complete`

## Objective

Harden the completed Audit foundation before Imports add more CLI, queue, and non-HTTP producers.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 18 — Administrative mode and impersonation](phase-18-admin-impersonation.md)
- [Phase 20 — Integrations](phase-20-integrations.md)
- [Audit module documentation](../modules/audit.md)
- [Identity, authentication, users, and sessions](../modules/identity-authentication-and-sessions.md)

## Implementation contract

- Audit persistence records durable audit rows only; it must not discover actor, impersonation, session, or request context through global HTTP helpers.
- Current audit context is supplied through an explicit Audit-owned public contract.
- Web/session-specific context discovery is outside Audit persistence and has safe empty behavior for CLI, scheduler, queue, and request-less execution.
- Security audit categories are explicit enum values at producer boundaries.
- Runtime security audit classification must not infer categories from action-name fragments.
- Existing stored category strings remain stable for compatibility.
- The legacy Phase 11 data-import migration may keep historical action-name mapping for old local data only.

## Tasks

- [x] Add explicit current audit context provider contract.
- [x] Move impersonation session context discovery out of Audit persistence.
- [x] Preserve safe request-less audit recording.
- [x] Reuse Laravel `Context` for correlation IDs instead of reading global requests in infrastructure.
- [x] Add typed security audit category enum.
- [x] Require explicit security category for security audit events.
- [x] Reject security categories on non-security audit events.
- [x] Remove runtime action-name fallback security classification.
- [x] Update existing security audit producers to pass explicit categories.
- [x] Add tests for request-less audit, impersonation context enrichment, category invariants, and audit persistence architecture.
- [x] Update canonical documentation.

## Completion criteria

- [x] Audit can be recorded from HTTP, CLI, scheduler, queue, and request-less code paths without Audit persistence depending on Laravel HTTP globals.
- [x] Impersonated actions can be enriched with actual actor, impersonated user, impersonation session, and correlation context through explicit providers.
- [x] Security audit producers provide stable explicit categories.
- [x] Relevant tests and documentation are current.
