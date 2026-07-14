## Phase 19 — Search

### Implementation contract

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
- Provide full and per-module rebuild commands, count comparison, discrepancy detection, and indexing-lag reporting.
- Rebuild into a new physical index and switch a stable alias only after successful validation.
- Failed rebuilds leave the current alias unchanged.
- Admin shows health, queue lag, last sync, discrepancies, and rebuild status, and may start a confirmed audited rebuild.
- Do not index sensitive data without an explicit need.
- Backend permissions, active-team scope, module activation, and visibility rules are verified independently of Meilisearch filters.

- [ ] Define module-owned index descriptors and immutable search document DTOs.
- [ ] Feed indexing from committed Outbox events and make consumers idempotent.
- [ ] Propagate deletion, anonymization, and visibility changes.
- [ ] Implement degraded versus critical readiness configuration.
- [ ] Add unavailable UI state without broad database fallback.
- [ ] Add full and per-module rebuild, count comparison, discrepancy, and lag commands.
- [ ] Implement new-index build plus stable-alias switch.
- [ ] Build Admin search health, lag, sync, discrepancy, and rebuild views.
- [ ] Add confirmed and audited Admin rebuild action.
- [ ] Test that permissions and team scope do not rely on Meilisearch filters.
- [ ] Create `Search` module.
- [ ] Define search contracts.
- [ ] Configure Laravel Scout and Meilisearch for the Search module.
- [ ] Define derived and rebuildable indexes.
- [ ] Define searchable, filterable, and sortable fields.
- [ ] Enforce team filtering.
- [ ] Enforce permission filtering.
- [ ] Queue index updates.
- [ ] Add rebuild commands.
- [ ] Remove indexed data during deletion/anonymization.
- [ ] Document when Meilisearch is allowed and when normal PostgreSQL queries must be used.
- [ ] Commit Search module.
