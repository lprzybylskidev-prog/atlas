## Phase 19 — Files

**Status:** `not started`

## Objective

Implement private file storage, quarantine, malware scanning, authorized downloads, and storage administration after audit, module activation, notifications, settings, and operational health exist.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Files module documentation](../modules/files.md)
- [Health, observability, and maintenance](../operations/health-observability-and-maintenance.md)

## Implementation contract

- Use Laravel Filesystem.
- Development may use local storage; production uses private S3-compatible storage.
- Files are private by default and downloaded only through an authorized use case.
- Physical filenames are random/generated. Original names are metadata only.
- Validate MIME, extension, size, and content.
- Store checksum and support deduplication where appropriate.
- Store file metadata separately from business records.
- Large uploads may be asynchronous.
- Temporary files have TTL cleanup.
- Files require antivirus scanning and quarantine before release.
- Every upload, scan, quarantine, release, download, replacement, deletion, and anonymization action is audited.
- The Admin storage browser is metadata/status oriented and must not become an arbitrary server filesystem editor.
- Anonymization and retention workflows include controlled file copies and exports.
- Define a module-owned `MalwareScanner` contract.
- Provide ClamAV as the default production adapter.
- Uploads enter quarantine before they become available to business workflows or downloads.
- File scan states are:
  - `pending`;
  - `scanning`;
  - `clean`;
  - `infected`;
  - `failed`;
  - `unsupported`.
- Only `clean` files may be downloaded or used by business processes.
- `infected` files remain blocked and are retained or deleted only through explicit retention, legal, and audit policy.
- `failed` and `unsupported` files remain blocked and are never treated as clean.
- Scanning is asynchronous for larger files and may be asynchronous for all files for consistency.
- Scan retries must be idempotent and safe.
- After retry exhaustion, the file remains blocked and requires operational intervention.
- A production fake scanner is forbidden.
- A clearly configured fake scanner may be used in local/development environments.
- When the Files module is active in production, unavailable malware scanning causes readiness failure.
- Store scan evidence including:
  - provider;
  - engine/signature version;
  - scanned timestamp;
  - result;
  - detected threat name where applicable;
  - exact file checksum.
- Any content or checksum change invalidates the previous result and requires a new scan.
- Admin may inspect scan queues, failures, and infected files and may trigger a rescan.
- Admin must never manually override a file to `clean`.

## Tasks

- [ ] Define the `MalwareScanner` contract.
- [ ] Implement the ClamAV production adapter.
- [ ] Implement a development-only fake scanner that exercises quarantine/status/checksum flows and configurable clean, infected, failed, and unsupported outcomes; prevent production use.
- [ ] Add quarantine storage/state before business availability.
- [ ] Implement `pending`, `scanning`, `clean`, `infected`, `failed`, and `unsupported` states.
- [ ] Block download and business use unless the file is `clean`.
- [ ] Implement asynchronous scan jobs and idempotent retries.
- [ ] Keep files blocked after retry exhaustion.
- [ ] Persist provider, engine/signature version, scanned time, result, threat name, and checksum.
- [ ] Invalidate scan results when file content/checksum changes.
- [ ] Make production readiness fail when Files are active and the scanner is unavailable.
- [ ] Build Admin views for scan queues, failures, infected files, and rescan actions.
- [ ] Prevent any manual Admin override to `clean`.
- [ ] Add tests for quarantine, scanner failures, unsupported files, infection, retry exhaustion, checksum changes, and readiness.
- [ ] Create `Files` module.
- [ ] Configure local development storage.
- [ ] Configure S3-compatible production storage.
- [ ] Make files private by default.
- [ ] Implement authorized download use cases.
- [ ] Generate physical filenames.
- [ ] Store original names as metadata.
- [ ] Validate MIME, extension, size, and content.
- [ ] Add checksums and deduplication.
- [ ] Add separate file metadata model.
- [ ] Add async large uploads.
- [ ] Add temporary-file TTL cleanup.
- [ ] Add antivirus scanning and quarantine.
- [ ] Audit upload, download, quarantine, release, and deletion/anonymization actions.
- [ ] Build secure admin storage browser.
- [ ] Commit Files module.

## Completion criteria

- [ ] Files are private, authorized, scanned/quarantined, auditable, and blocked unless clean.
- [ ] Production readiness fails when active Files require malware scanning and the scanner is unavailable.
- [ ] Later imports, reports, privacy, and business modules can store artifacts through one file contract.
- [ ] Relevant tests and documentation are current.
