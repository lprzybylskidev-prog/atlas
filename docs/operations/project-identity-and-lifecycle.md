# Project identity and lifecycle

Canonical operational identity values and lifecycle flags for Atlas.

## Identity values

- System name: `Atlas`
- Application display name: `Atlas`
- Permanent PHP root namespace: `App`
- Repository: `lprzybylskidev-prog/atlas`
- Docker Compose project name: `atlas`
- Default PostgreSQL database name: `atlas`
- Default PostgreSQL user name: `atlas`

Do not introduce a branded PHP root namespace such as `Atlas`.

## Production deployment lifecycle

Before the first real production deployment:

```text
PRODUCTION_DEPLOYED=false
```

While `PRODUCTION_DEPLOYED=false`, migrations may be edited in place because no production database has accepted them.

### Local reset after a pre-production migration squash

Pulling a change that removes, renames, reorders, or folds an existing migration into a canonical create migration requires a destructive local database reset. The migration repository in an existing development database cannot replay edited files safely and is not a supported upgrade path while `PRODUCTION_DEPLOYED=false`.

Use `composer demo:reset` when the standard deterministic local review data is wanted. Use `php artisan migrate:fresh --seed` for a production-safe seeded local schema, or `php artisan migrate:fresh` for an empty schema. These commands delete all local Atlas data. Export any disposable local reference data before the reset if it must be recreated manually; do not add compatibility migrations or widen `DB_SEARCH_PATH` to preserve an old pre-production database.

Atlas keeps PostgreSQL `search_path` at `public`. Its `db:wipe` command, which `migrate:fresh` invokes, additionally drops the exact Atlas-owned schemas registered in `DatabaseSchema::all()`. Test-database preparation uses the same registry and remains a defensive cleanup for interrupted test runs.

After the first real production deployment:

```text
PRODUCTION_DEPLOYED=true
```

After `PRODUCTION_DEPLOYED=true`, migrations are forward-only and already deployed migrations must not be edited.
