# Phase 22 — Search

**Status:** `complete`

## Objective

Implement module-owned Meilisearch projections after Outbox, audit, module activation, active-team context, health, privacy participation, and operational visibility are ready.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 13 — Sessions and active team](phase-13-sessions-active-team.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 20b — Managed processes, process logs, and scheduler](phase-20b-managed-processes.md)
- [Search module documentation](../modules/search.md)
- [Modular monolith architecture](../architecture/modular-monolith.md)

## Implementation contract

- Meilisearch is used only for real large full-text catalogs such as people, addresses, counterparties, or similarly large business search.
- Ordinary filtering, tabular reports, and small selectors use PostgreSQL.
- Search indexes are derived and rebuildable.
- Index changes use queues.
- Every index declares searchable, filterable, and sortable fields.
- Team and permission filtering is mandatory in search results.
- Deletion and anonymization remove/de-identify search documents.
- Provide versioned rebuild commands and health visibility.
- Meilisearch is a disposable projection/read model and never the source of truth.
- Every index is owned by one explicit module, which defines its document DTO, mapping, searchable/filterable/sortable fields, and deletion/anonymization policy.
- Indexing uses queues and committed Outbox events; consumers are idempotent.
- Deletion, anonymization, and visibility changes update or remove indexed documents.
- Meilisearch outages do not block core business writes.
- Search is degraded-readiness by default; a Atlas may explicitly mark it critical.
- UI shows a clear unavailable state.
- Do not automatically fall back to expensive unrestricted `ILIKE '%...%'` queries on large tables.
- Provide full and per-module rebuild commands, count comparison, discrepancy detection, and indexing-lag reporting through managed-process runs.
- Rebuild into a new physical index and switch a stable alias only after successful validation.
- Failed rebuilds leave the current alias unchanged.
- Admin shows health, queue lag, last sync, discrepancies, and rebuild status, and may start a confirmed audited rebuild as a managed process.
- Do not index sensitive data without an explicit need.
- Backend permissions, active-team scope, module activation, and visibility rules are verified independently of Meilisearch filters.

## Tasks

- [x] Define module-owned index descriptors and immutable search document DTOs.
- [x] Feed indexing from committed Outbox events and make consumers idempotent.
- [x] Propagate deletion, anonymization, and visibility changes.
- [x] Implement degraded versus critical readiness configuration.
- [x] Add unavailable UI state without broad database fallback.
- [x] Add full and per-module rebuild, count comparison, discrepancy, and lag commands backed by managed-process runs.
- [x] Implement new-index build plus stable-alias switch.
- [x] Build Admin search health, lag, sync, discrepancy, and rebuild views integrated with managed-process run details.
- [x] Add confirmed and audited Admin rebuild action.
- [x] Test that permissions and team scope do not rely on Meilisearch filters.
- [x] Create `Search` module.
- [x] Define search contracts.
- [x] Configure Laravel Scout and Meilisearch for the Search module.
- [x] Define derived and rebuildable indexes.
- [x] Define searchable, filterable, and sortable fields.
- [x] Enforce team filtering.
- [x] Enforce permission filtering.
- [x] Enforce ModuleGate for search queries, indexing jobs, rebuild commands, and Admin search operations.
- [x] Document degraded or unavailable behavior when Search or a searched module is inactive.
- [x] Queue index updates.
- [x] Add rebuild commands.
- [x] Remove indexed data during deletion/anonymization.
- [x] Document when Meilisearch is allowed and when normal PostgreSQL queries must be used.
- [x] Commit Search module.

## Completion criteria

- [x] Search indexes are derived, rebuildable, module-owned, and fed from committed Outbox events.
- [x] Backend authorization, active-team, module activation, deletion, anonymization, and visibility rules do not rely on Meilisearch filters alone.
- [x] Search health and rebuilds are visible and safe.
- [x] Relevant tests and documentation are current.
