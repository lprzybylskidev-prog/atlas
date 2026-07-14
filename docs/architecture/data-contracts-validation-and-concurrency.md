# Data contracts, formatting, validation, errors, and concurrency

Canonical shared rules for transport formats, dates, money, enums, null handling, formatters, validation layers, exceptions, and concurrency.

## Data Transport and Formatting

### Dates

Transport date/time values in ISO format with timezone, for example:

```text
2026-07-13T14:30:00+02:00
```

Localize only for presentation.

### Money

Transport and store integer minor units.

Example:

```text
1250
```

Display using one shared formatter, for example:

```text
12,50 zł
```

Never divide manually in arbitrary components.

### Enums

Use native PHP enums with stable technical string values.

Labels use translation keys.

Transitions belong in the domain.

Provide typed frontend representations.

### Null and absent

Treat `null` and absent fields as different meanings.

### Shared formatters

Provide shared formatters for:

- date;
- time;
- money;
- percent;
- numbers;
- status;
- empty values.

---

## Validation, Errors, and Concurrency

### Validation layers

Presentation:

- shape;
- format;
- file constraints;
- basic cross-field checks.

Application:

- existence;
- process state;
- duplicates;
- authorization coordination.

Domain:

- invariants;
- value-object rules;
- legal state transitions.

Frontend validation is UX only.

Database constraints are the final defense against races.

### Exceptions

Use concrete domain and technical exceptions.

Do not throw generic `Exception` for expected failures.

Technical messages are in English.

Map exceptions centrally to:

- HTTP status;
- translation key;
- user-safe message;
- logging behavior.

No silent catches.

Catch only to:

- handle;
- map;
- log;
- rethrow.

Catch `Throwable` only at boundaries.

Retry transient failures only.

### Concurrency

Use:

- database constraints;
- Redis locks;
- optimistic locking where justified;
- explicit conflict errors;
- idempotency.

Never silently overwrite concurrent changes.

Financial operations require stricter conflict handling.

Add a `version` column only where real concurrent editing risk exists.

---
