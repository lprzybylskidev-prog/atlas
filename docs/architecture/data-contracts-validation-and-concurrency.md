# Data contracts, formatting, validation, errors, and concurrency

Canonical shared rules for transport formats, dates, money, enums, null handling, formatters, validation layers, exceptions, and concurrency.

## Phase 28 closure state

Phase 28 closed the recorded locale-source drift, missing-translation fallback behavior, save-scope ambiguity, stale-write coverage in authorization/team workflows, audit before/after requirements, and owner-owned public contract boundary gaps.

Public contracts are small typed owner-owned capabilities; formatters use one effective locale source; missing Atlas-owned translations fail gates; every form save scope is explicit; stale writes are rejected and audited; and validation errors never expose implementation identifiers.

Tracked issue IDs: `P28-ARCH-004`, `P28-FORM-002`, `P28-FORM-003`, `P28-AUTH-004`, `P28-LOC-001`, `P28-LOC-002`.

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

Frontend formatters obtain their default locale from `effectiveLocale`; passing a locale remains available only for an explicit alternate-output contract. Backend mail, export, browser-print, and PDF generation use the effective request locale, and asynchronous export work persists that locale in its immutable snapshot so a later worker cannot format using its process default. Existing pre-locale export records render in Polish as the documented compatibility fallback.

Laravel translation files are the only Atlas-owned copy catalogs. Missing frontend keys render as an explicit `[translation:key]` diagnostic marker and fail localization guard tests; they are never humanized into plausible English-like text. Accessibility labels and error-page copy use the same canonical translation source. Unknown status or enum tokens follow the same explicitly marked diagnostic rule on Admin diagnostic surfaces and are forbidden on ordinary user and manager surfaces.

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
