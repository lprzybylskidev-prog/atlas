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

After the first real production deployment:

```text
PRODUCTION_DEPLOYED=true
```

After `PRODUCTION_DEPLOYED=true`, migrations are forward-only and already deployed migrations must not be edited.
