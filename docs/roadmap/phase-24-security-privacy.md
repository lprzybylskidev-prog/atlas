## Phase 24 — Security, privacy, deletion, and anonymization

### Implementation contract

- Three distinct data-removal modes exist:
  - soft delete as default where appropriate;
  - hard delete only through dedicated high-risk Admin use cases;
  - irreversible anonymization.
- Every foreign key uses `RESTRICT`; cascade delete is forbidden.
- Hard delete requires separate permission, strong reauthentication, multi-step confirmation, mandatory reason, exact impact preview, full audit, and dry-run for mass operations.
- Financial, audit, legal, and retention-controlled records generally cannot be hard deleted.
- Anonymization is an explicit use case and must de-identify every controlled copy: core/related tables, permitted audit/log fields, files, attachments, search indexes, cache, queues, read models, exports, and copies controlled by the project.
- Preserve only neutral technical records required by law or integrity.
- Respect retention obligations.
- Security includes least privilege, CSRF, secure headers, no production stack traces, explicit mass assignment, no unsafe deserialization/eval, encryption where justified, dependency vulnerability checks, secret-free logs/audit, and reauthentication for destructive actions.
- Central rate limits cover login, API, search, imports, exports, and expensive operations by user/IP/team/operation.
- Admin can view blocks/abuse. Bypass exists only through explicit permission and configuration.

- [ ] Add central hard-delete framework.
- [ ] Add separate permissions.
- [ ] Add reauthentication.
- [ ] Add dry-run.
- [ ] Add impact preview.
- [ ] Add typed confirmation.
- [ ] Add reason and audit.
- [ ] Add irreversible anonymization framework.
- [ ] Cover related tables.
- [ ] Cover files.
- [ ] Cover search indexes.
- [ ] Cover cache.
- [ ] Cover queued and derived data under project control.
- [ ] Cover exports.
- [ ] Document legal-retention exceptions.
- [ ] Add security headers.
- [ ] Add dependency vulnerability checks.
- [ ] Add rate-limit management and visibility.
- [ ] Add secret-safe logs and audit verification.
- [ ] Add authorization regression tests.
- [ ] Commit security and privacy foundation.
