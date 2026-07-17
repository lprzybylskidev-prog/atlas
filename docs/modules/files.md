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
