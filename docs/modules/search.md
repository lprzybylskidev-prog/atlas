# Search module

Canonical current behavior for Meilisearch projections, authorization scope, indexing, rebuild, consistency, health, and failure behavior.

## Search

Use Meilisearch only for justified large full-text search.

Do not use it for ordinary filters, reports, or small lookup lists.

The optional `Search` module is deployed as an explicit Atlas module with the key `search`.
It depends on identity, authorization, teams, audit, notifications, health, and managed processes.
It participates in global and team activation.

Search indexes:

- are derived and rebuildable;
- update through queues;
- declare searchable, filterable, and sortable fields;
- enforce team and permission filtering;
- are cleaned during deletion/anonymization;
- provide rebuild commands.

Current foundation contracts:

- `SearchIndexDescriptor` declares the owning module, stable alias, searchable fields, filterable fields, sortable fields, and deletion/anonymization support.
- `SearchDocument` is an immutable payload DTO for derived Meilisearch documents and carries module, team, permission, and visibility-hash fields alongside module-owned document fields.
- `SearchIndexRegistry` resolves only explicitly tagged index descriptors from `atlas.search_index_descriptors`.
- `SearchEventProjector` lets owning modules translate committed `IntegrationEventMessage` records into upserted or deleted search documents.
- `HandleSearchOutboxEvent` runs on the `search` queue, uses the Outbox consumer deduplicator with the `search.indexing` consumer key, enforces module availability for Search and the indexed module, configures Meilisearch index fields, and applies document upserts/deletions through the search document store.
- `SearchQuery` requires an index key, non-empty term, active team public ID, user public ID, and caller permission scope. `SearchClient` enforces Search module availability and the indexed module availability before querying Meilisearch.
- `SearchRebuildDocumentProvider` supplies module-owned rebuild documents and expected counts. Rebuilds write to a new physical index, validate counts, and promote it through the stable alias only after successful validation.
- `SearchLifecycleProjector` maps delete/anonymize lifecycle subjects to indexed document IDs so Search can remove projected data idempotently during privacy workflows.
- `SearchIndexMaintenanceService` provides rebuild, count comparison, discrepancy, and lag report summaries used by managed-process rebuilds and Admin Search.
- Search permissions are registered as `search.query`, `admin.search.index`, and `admin.search.rebuild`.
- `search.rebuild` is registered as a managed process on the `search` queue. With no registered index descriptors it succeeds as a safe no-op; concrete modules add descriptors before rebuild orchestration can index their documents.
- `search:rebuild` starts the `search.rebuild` managed process from CLI. It requires `--actor` and `--team` so rebuilds remain authorized and audited, and accepts optional `--module` and `--index` filters.
- `/admin/search` shows Search readiness, registered index descriptors, recent rebuild managed-process runs, and a confirmed rebuild action. The rebuild action requires the `REBUILD SEARCH` confirmation phrase and redirects to the managed-process run details.

## Full-Text Search

- Treat Meilisearch as a disposable projection/read model, never as a source of truth.
- Every index belongs to one explicit module.
- Indexing is asynchronous, idempotent, and fed through committed Outbox events.
- Do not automatically fall back to expensive broad database `ILIKE '%...%'` searches.
- Backend authorization and team scope remain mandatory; search filters are not a security boundary.
- Do not index sensitive data without an explicit module-level need.

Every Meilisearch query includes the active team and permission scope as filters. Backend use cases must still authorize and scope the operation before calling Search; the Meilisearch filters are projection constraints, not the only security control.

## Availability

Meilisearch readiness is degraded by default and becomes blocking only when Atlas is configured with `ATLAS_HEALTH_MEILISEARCH_CRITICAL=true`.

Search outages must not block core business writes. Search UI and Admin operations must show an unavailable state rather than issuing unrestricted broad PostgreSQL fallback queries against large tables.

The Admin Search page reports missing Meilisearch configuration as degraded by default or unhealthy when `ATLAS_HEALTH_MEILISEARCH_CRITICAL=true`. Full runtime reachability remains visible in Admin System Status.
