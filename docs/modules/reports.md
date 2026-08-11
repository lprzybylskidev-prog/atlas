# Reports module

Canonical current behavior for the optional Reports module.

## Reports

Reports is an optional module for named reports, report-specific chart providers, report catalogs, and future business reporting workflows.

Reports does not own the export/PDF/print artifact lifecycle. Reusable export request snapshots, generation jobs, render credentials, artifact access, retention cleanup, CSV/XLSX/PDF generators, browser print layouts, local report fonts, and export provider registries are owned by the Core [Exports](exports.md) module.

Current behavior:

- `ReportsModule` is deployed as the optional `reports` module.
- Reports is currently an empty Optional module shell with no formal dependencies or registered runtime behavior. Core Exports owns the implemented report/export/PDF/print foundation; future Reports behavior must declare only dependencies evidenced by real registrations or shared integration ports.
- Report-specific data access must be exposed to Exports through typed provider contracts; Reports must not duplicate the Core export engine.
- Report-specific chart providers may contribute meaningful charts through the Core Exports chart provider registry.
- Future report catalogs and named business reporting workflows belong here, while artifact generation and download authorization remain in Core Exports.
# Phase 28 foundation repair target

Current state: Reports is an intentionally empty Optional shell for future module-owned named business reports. Core Exports owns every implemented export/PDF/print/chart artifact workflow; Reports declares no decorative entrypoint, dependency, or runtime behavior.

Target state: Reports metadata accurately reflects report surfaces, optional dependencies are safe, Exports ownership is clear, and report/PDF/print/chart workflows use canonical runtime, audit, notification, and UI contracts.

Tracked issue IDs: `P28-ARCH-003`, `P28-MOD-004`, `P28-RUNTIME-009`, `P28-MODAUD-017`.
