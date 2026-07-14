## Phase 18 — Imports

### Implementation contract

- Use one transport-independent import pipeline:
  source adapter -> parsing -> normalization -> input DTO -> validation -> deduplication/idempotency -> domain use cases -> audit/error report.
- Supported adapters include CSV, XLSX, XML, internal API, and external API.
- An API adapter cannot bypass validation, use cases, invariants, authorization, or audit.
- Every import process stores ID, source, original file, status, statistics, and row/field errors.
- Large imports run in queues and publish progress notifications.
- Preserve original files according to retention.
- Import jobs are idempotent and safe to retry according to explicit rules.
- Admin can inspect status, source, statistics, errors, and allowed retries.

- [ ] Create optional `Imports` module.
- [ ] Define source adapter contracts.
- [ ] Add CSV adapter.
- [ ] Add XLSX adapter.
- [ ] Add XML adapter.
- [ ] Add internal API adapter.
- [ ] Add external API adapter.
- [ ] Implement parsing.
- [ ] Implement normalization.
- [ ] Implement typed input DTOs.
- [ ] Implement validation.
- [ ] Implement deduplication and idempotency.
- [ ] Route imported data through domain use cases.
- [ ] Store process ID, source, file, status, statistics, and row/field errors.
- [ ] Queue large imports.
- [ ] Preserve original import files.
- [ ] Add retry rules.
- [ ] Add import administration.
- [ ] Add notifications and progress.
- [ ] Commit Imports module.
