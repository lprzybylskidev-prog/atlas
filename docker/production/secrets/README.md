# Production secrets

Production secret values are not committed.

Create these files on the production host before starting the production Compose stack:

- `postgres_password.txt`
- `meilisearch_master_key.txt`

The files are ignored by Git and mounted through Docker Compose secrets.
