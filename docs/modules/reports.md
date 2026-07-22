# Reports module

Canonical current behavior for the optional Reports module.

## Reports

Reports is an optional module for named reports, report-specific chart providers, report catalogs, and future business reporting workflows.

Reports does not own the export/PDF/print artifact lifecycle. Reusable export request snapshots, generation jobs, render credentials, artifact access, retention cleanup, CSV/XLSX/PDF generators, browser print layouts, local report fonts, and export provider registries are owned by the Core [Exports](exports.md) module.

Current behavior:

- `ReportsModule` is deployed as the optional `reports` module.
- Reports depends on the Core `exports` module.
- Report-specific data access must be exposed to Exports through typed provider contracts; Reports must not duplicate the Core export engine.
- Report-specific chart providers may contribute meaningful charts through the Core Exports chart provider registry.
- Future report catalogs and named business reporting workflows belong here, while artifact generation and download authorization remain in Core Exports.
