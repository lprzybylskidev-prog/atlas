# Search module

Canonical current behavior for Meilisearch projections, authorization scope, indexing, rebuild, consistency, health, and failure behavior.

## Search

Use Meilisearch only for justified large full-text search.

Do not use it for ordinary filters, reports, or small lookup lists.

Search indexes:

- are derived and rebuildable;
- update through queues;
- declare searchable, filterable, and sortable fields;
- enforce team and permission filtering;
- are cleaned during deletion/anonymization;
- provide rebuild commands.

## Full-Text Search

- Treat Meilisearch as a disposable projection/read model, never as a source of truth.
- Every index belongs to one explicit module.
- Indexing is asynchronous, idempotent, and fed through committed Outbox events.
- Do not automatically fall back to expensive broad database `ILIKE '%...%'` searches.
- Backend authorization and team scope remain mandatory; search filters are not a security boundary.
- Do not index sensitive data without an explicit module-level need.
