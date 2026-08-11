# Production secret mounts

Secret values are never committed or copied into an image. The Phase 28 internal
HTTP stack reads these host files through Compose secrets:

- `app_key.txt` (a Laravel `base64:` application key);
- `postgres_password.txt`;
- `redis_password.txt`;
- `meilisearch_master_key.txt`;
- `mail_password.txt`;
- `sentry_laravel_dsn.txt` (may be empty while Sentry is intentionally disabled);
- `files_s3_access_key_id.txt`;
- `files_s3_secret_access_key.txt`.

Use owner-only permissions (`0600`). Every default path may be replaced with the
matching `ATLAS_SECRET_*_FILE` variable from `docker/production/.env.example`.
The runtime entrypoint also accepts the standard `<VARIABLE>_FILE` convention
when a different orchestrator mounts secrets outside this Compose definition.

The files in this directory are ignored. Phase 29 will own host provisioning,
secret rotation, HTTPS, release switching, deployment, and rollback.
