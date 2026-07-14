# Imports module

Canonical current behavior for import definitions, mapping, validation, preview, execution, idempotency, progress, errors, and audit.

### Imports

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

Supported sources may include:

- XLSX;
- CSV;
- XML;
- internal API;
- external API.

An API import must not bypass domain rules.

Each import process records:

- ID;
- source;
- file;
- status;
- statistics;
- row and field errors.

Large imports use queues.

Keep original import files according to retention policy.
