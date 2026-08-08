# Security baseline

Canonical security baseline that applies across Atlas. Read together with the affected module and operations documentation.

## Phase 28 target

Current state: Atlas has a security baseline, but Phase 28 tracks additional durable enforcement for audit catalogs, critical-change audit atomicity, no raw technical tokens in user/manager UI, hardcoded mail-copy rejection, secret-safe runtime configuration, no production fake scanner, real readiness, and guarded high-risk workflows.

Target state: security-sensitive operations are cataloged, audited for success/rejection/failure, transactionally evidenced, localized safely, and backed by executable guardrails across application, mail, frontend, runtime, and operations.

Tracked issue IDs: `P28-AUDIT-*`, `P28-UI-002`, `P28-MAIL-*`, `P28-RUNTIME-003`, `P28-RUNTIME-008`, `P28-GUARD-*`.

## Security

- least privilege;
- all input is untrusted;
- CSRF protection;
- secure headers;
- no production stack traces;
- explicit mass-assignment fields;
- no unsafe deserialization;
- no `eval`;
- encrypt sensitive values where justified;
- no secrets in logs;
- no secrets in audit;
- dependency vulnerability checks;
- destructive operations require reauthentication;
- rate limits for login, API, search, import, export, and expensive operations;
- rate limiting may vary by user, IP, team, and operation;
- bypass requires explicit permission and configuration.

---

## Rate Limiting

- Define rate limits as named policies in code and configuration.
- Do not allow administrators to edit policy thresholds through the UI.
- Do not provide one global switch that disables rate limiting.
- Login and MFA security limits are mandatory and cannot be disabled.
- Administrative counter resets require explicit confirmation and audit.
- User-facing errors must not reveal exact thresholds when that would assist abuse.

## System Protection

Atlas applies HTTP security headers through global middleware:

- `Content-Security-Policy`;
- `X-Frame-Options`;
- `X-Content-Type-Options`;
- `Referrer-Policy`;
- `Permissions-Policy`;
- `Strict-Transport-Security` on HTTPS responses.

Dependency audit command coverage for Composer and pnpm lockfiles is tracked as technical configuration and test coverage. It is not exposed as an Admin screen because it does not provide an operator workflow.

## Malware Scanning

- All uploaded files pass through a `MalwareScanner` contract.
- Production uses a real scanner such as ClamAV; a fake scanner is development-only and must never run in production.
- The development fake scanner still exercises quarantine, asynchronous status transitions, checksum binding, and failure/infected test scenarios; it must not merely bypass the scanning workflow by marking every upload clean.
- New files remain quarantined until a successful `clean` result.
- `failed`, `unsupported`, and scanner unavailability never imply that a file is safe.
- Administrators may retry scans but may never manually mark a file as `clean`.
- When the Files module is active in production, scanner unavailability must fail readiness.
