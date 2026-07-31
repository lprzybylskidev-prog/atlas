# Imports module

Canonical current behavior for import definitions, source adapters, mapping, validation, execution, idempotency, progress, errors, and audit.

## Imports

Imports are external-input workflows built on the shared [Managed processes](managed-processes.md) foundation.

Use one transport-independent pipeline:

```text
source adapter
-> parsing
-> normalization
-> input DTO
-> validation
-> deduplication/idempotency
-> domain use cases
-> audit and error reporting
```

Supported source adapter contracts exist for:

- XLSX;
- CSV;
- XML;
- internal API;
- external API.

The current implementation provides the import contracts, persistence, Admin visibility, and managed-process linkage. Real adapters and import definitions are registered by owning modules as those workflows are implemented.

An API import must not bypass domain rules.

Each import process records import-specific data while the managed-process foundation records shared run lifecycle, queue state, progress, logs, retry/cancel, schedules, notifications, and audit.

Import-specific records include:

- ID;
- source;
- file;
- API request or external reference when no file exists;
- statistics;
- row and field errors.

Every import execution is linked to a managed process run and is visible in the combined `/admin/managed-processes` run list and the corresponding `/admin/managed-processes/{run}` detail screen.

File-backed import definitions may expose both a watched-directory source and a manual upload start form. Manual uploads must use the Files module first and pass the resulting file public ID into the managed-process input snapshot; directory-backed starts store the directory reference as safe import source metadata.

Large imports use managed-process queues and may run as long single jobs. Import implementations are responsible for idempotency and deduplication so rerunning the same file or API import can recognize work already accepted by the import contract. Atlas does not force large imports to split into multiple visible process runs or multiple technical queue jobs.

Keep original import files according to retention policy.

Row and field errors are structured import error records and may also appear in the process timeline as warning or error events. They must not bypass the managed-process log redaction and safe-context rules.

Automated tests use isolated fixtures for import executions and row errors; development reset does not seed artificial import records.
