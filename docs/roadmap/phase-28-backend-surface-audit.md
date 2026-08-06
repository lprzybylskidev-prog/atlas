# Phase 28 — Backend and database surface audit

**Status:** `not started`

## Objective

Audit Atlas after the foundation modules are implemented and before production deployment to find capabilities, workflows, tables, settings, policies, jobs, commands, and persisted states that exist in backend code or database schemas but are not reachable through an appropriate frontend, Admin, manager, user, CLI, or documented operational surface.

This phase prevents hidden or half-exposed foundation work from shipping as dead backend/database weight or as workflows that can only be operated by direct database access.

## Dependencies

- [Phase 27 — Optional TimeTracking module](phase-27-time-tracking.md)
- [Phase 27a — Foundation architecture and quality-gate hardening](phase-27a-foundation-architecture-quality-hardening.md)
- [Phase 27b — Demo and test seeder invariant repair](phase-27b-demo-seeder-invariant-repair.md)
- [Phase 27c — Bilingual email templates and notification mail audit](phase-27c-bilingual-email-templates.md)
- [Identity, authentication, users, and sessions](../modules/identity-authentication-and-sessions.md)
- [Notifications module](../modules/notifications.md)
- [TimeTracking module](../modules/time-tracking.md)
- [Frontend and shared UI architecture](../architecture/frontend-ui.md)

## Implementation contract

- Inventory backend and persistence capabilities across all completed foundation modules.
- For every Atlas-owned table, enum, status, setting, permission, policy, job, command, public contract, and workflow state, identify the intended owner surface:
  - regular user UI;
  - manager UI;
  - Admin UI;
  - CLI/operations;
  - automated scheduler/worker;
  - documented internal-only technical mechanism.
- Flag any capability that is implemented in backend/database but lacks a real operation surface, discoverable route, documented CLI command, automated owner, or explicit internal-only rationale.
- Distinguish legitimate internal mechanisms from unfinished product workflows. Internal mechanisms must still be documented, tested, and reachable through their owning automation or diagnostics when operationally necessary.
- Do not keep unused database tables, columns, DTOs, policies, notification types, settings, routes, permissions, translations, seed data, or tests merely because they were implemented earlier. Remove them when the accepted product contract says they will never be used.
- Do not expose technical internals to regular users just to make backend code reachable. Choose the correct surface: Admin for technical operations, manager for scoped work, user for self-service, CLI for operational procedures.
- For every discovered gap, either:
  - implement the missing surface in this phase when the scope is small and already accepted;
  - remove the unused backend/database implementation when the capability is not part of the accepted product;
  - document a new explicit follow-up phase when the capability is valid but too large to complete safely inside this audit.
- Verify user-facing names, routes, breadcrumbs, titles, navigation, validation, translations, permissions, module gates, demo/e2e visibility, and tests for every added or removed surface.
- The audit must include TimeTracking correction, settlement, report, and operational-control workflows because Phase 27 exposed concrete examples of backend/database work that was not fully represented in UI.

## Tasks

- [ ] Inventory all Atlas-owned database tables and columns by owning module and intended operation surface.
- [ ] Inventory all module permissions and verify each non-internal permission gates at least one reachable route, action, command, or documented workflow.
- [ ] Inventory all public contracts and confirm each has an implemented caller, operation surface, or explicit future-facing rationale.
- [ ] Inventory all queued jobs, scheduled tasks, managed processes, and console commands and confirm they are reachable through scheduler, Admin UI, CLI documentation, or automated workflows.
- [ ] Inventory all settings and policy tables and confirm each setting is editable, documented as configuration-only, or removed.
- [ ] Inventory all notification types and email templates and confirm each can actually be triggered by an accepted workflow and controlled by the correct user/admin surface.
- [ ] Inventory all frontend routes, breadcrumbs, navigation links, page titles, and route names for orphaned, stale, duplicated, or hidden paths.
- [ ] Inventory all translations for user-facing features removed or no longer reachable.
- [ ] Audit TimeTracking corrections, settlement periods/settings, technical logs, manager workflows, and Admin operations for missing or obsolete surfaces.
- [ ] Audit Admin tables and operational screens for backend features that currently require direct database access.
- [ ] Audit manager screens for backend manager capabilities that are not exposed with manager-scope controls.
- [ ] Audit regular user screens for backend self-service capabilities that are not exposed or are exposed through technical routes/names.
- [ ] Remove unused backend/database elements that are explicitly not part of the accepted product.
- [ ] Add missing UI/CLI/operations surfaces for accepted capabilities that are small enough to close in this phase.
- [ ] Create follow-up roadmap phases for valid, larger product workflows that cannot safely fit in this audit.
- [ ] Update canonical module, architecture, and operations documentation to reflect each removed, exposed, or deferred capability.
- [ ] Add or update tests proving removed capabilities are gone, exposed capabilities are reachable through the correct surface, and internal-only mechanisms remain documented and protected.
- [ ] Run relevant backend, frontend, and E2E quality gates for changed surfaces.

## Completion criteria

- [ ] No Atlas-owned backend/database capability remains unintentionally unreachable from its correct operation surface.
- [ ] Every remaining internal-only mechanism has a documented owner, tests, and operational rationale.
- [ ] Unused tables, columns, policies, routes, permissions, notification types, translations, and tests discovered by the audit are removed or explicitly justified.
- [ ] Follow-up phases exist for any accepted but intentionally deferred operation surfaces.
- [ ] Documentation and tests match the actual reachable application behavior.
