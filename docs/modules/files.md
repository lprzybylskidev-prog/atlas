# Files module

Canonical current behavior for private files, authorization, validation, checksums, quarantine, malware scanning, retention, and administrative recovery.

## Files

Use Laravel Filesystem.

Development may use local storage.

Production uses S3-compatible storage.

Rules:

- private by default;
- authorized download use cases;
- generated physical names;
- original filename as metadata only;
- MIME, extension, size, and content validation;
- checksum and deduplication;
- metadata stored separately;
- async handling for large uploads;
- temporary-file TTL cleanup;
- antivirus scan and quarantine;
- all operations audited;
- anonymization covers files.

## Current implementation

`App\Modules\Core\Files\FilesModule` is deployed as a Core module.

Persistence is owned by the `core_files` PostgreSQL schema:

- `file_objects` stores private file metadata, generated storage path, original filename, MIME type, extension, size, checksum, scan state, quarantine timestamps, and availability timestamps;
- `file_scan_evidence` stores one evidence record per scan attempt with provider, engine/signature version when available, scan timestamp, result, threat name, and the exact checksum that was scanned.

The public storage contract is `App\Modules\Core\Files\Application\Public\Contracts\FileStorage`.

Current rules:

- files are written to the configured private disk from `atlas.files.disk`;
- local development defaults to `atlas_files`;
- production defaults to `atlas_files_s3`;
- uploaded physical paths are generated and do not use the original filename;
- clean duplicate uploads may reuse the canonical physical object by checksum and size while preserving separate metadata records;
- uploaded files enter `pending` quarantine and are unavailable until scan completion;
- only `clean` files may be returned by the download use case;
- `pending`, `scanning`, `infected`, `failed`, and `unsupported` files remain blocked;
- checksum mismatch between stored metadata and scan evidence invalidates the result and returns the file to `pending`;
- scanner retry exhaustion leaves the file in `failed`;
- uploads above `ATLAS_FILES_LARGE_UPLOAD_SCAN_THRESHOLD_BYTES` route their scan work to `ATLAS_FILES_LARGE_SCAN_QUEUE`;
- expired temporary scan files are pruned by `files:prune-temporary`;
- upload, scan start/completion, blocked download, successful download, rescan, replacement, deletion, anonymization, retention copy, and temporary cleanup actions are written to the Audit module.

## Lifecycle and retention

The lifecycle contract is `App\Modules\Core\Files\Application\Public\Contracts\FileLifecycle`.

Current operations:

- `replace` stores a replacement file, quarantines/scans it, marks the previous metadata record deleted, and audits the replacement;
- `delete` marks metadata deleted, blocks future downloads, and deletes the physical object only when no live metadata record still references it;
- `anonymize` replaces the original filename with an anonymized label, stores only a hash of the former name in metadata, and audits the irreversible operation;
- `createRetentionCopy` creates a controlled private copy with `retention_purpose` and source-file linkage for legal/retention/export workflows.
- `createRetentionExport` creates a controlled private export copy under the retention export path and audits the export separately from ordinary retention copies.

The maintenance contract is `App\Modules\Core\Files\Application\Public\Contracts\FileMaintenance`.

Current maintenance:

- `files:prune-temporary` removes expired orphan scan temporary files under the configured temporary prefix;
- the scheduler runs this cleanup hourly;
- cleanup is audited with deleted and failed counts.

## Malware scanning

The module-owned scanner contract is `App\Modules\Core\Files\Application\Contracts\MalwareScanner`.

Adapters:

- `ClamAvMalwareScanner` uses the ClamAV daemon `INSTREAM` protocol and is the default production scanner;
- `FakeMalwareScanner` is configurable for local/development outcomes: `clean`, `infected`, `failed`, and `unsupported`;
- the fake scanner is forbidden in production.

Configuration:

- `ATLAS_FILES_DISK`;
- `ATLAS_FILES_SCANNER`;
- `ATLAS_FILES_MAX_UPLOAD_BYTES`;
- `ATLAS_FILES_LARGE_UPLOAD_SCAN_THRESHOLD_BYTES`;
- `ATLAS_FILES_SCAN_QUEUE`;
- `ATLAS_FILES_LARGE_SCAN_QUEUE`;
- `ATLAS_FILES_ALLOWED_EXTENSIONS`;
- `ATLAS_FILES_ALLOWED_MIME_TYPES`;
- `ATLAS_FILES_SCAN_MAX_ATTEMPTS`;
- `ATLAS_FILES_TEMPORARY_TTL_MINUTES`;
- `ATLAS_FILES_TEMPORARY_SCAN_PREFIX`;
- `ATLAS_FILES_FAKE_SCANNER_RESULT`;
- `ATLAS_FILES_CLAMAV_HOST`;
- `ATLAS_FILES_CLAMAV_PORT`;
- `ATLAS_FILES_CLAMAV_TIMEOUT_SECONDS`;
- S3-compatible production storage through the `ATLAS_FILES_S3_*` environment variables.

When Files are deployed in production, ClamAV readiness is blocking if no daemon endpoint is configured or reachable.

## Admin operations

The Admin file browser is available at `/admin/files`.

It is metadata/status oriented only. It exposes file metadata, scan states, latest scan evidence, blocked/infected/failure visibility, and a rescan action. It does not expose arbitrary server filesystem browsing and does not provide any manual override to mark a file as `clean`.

Permissions:

- `admin.files.index`;
- `admin.files.rescan`;
- `files.download`.

## Remaining phase work

Phase 19 implementation is complete except for the repository commit step, which requires explicit user approval under the Atlas git workflow.
