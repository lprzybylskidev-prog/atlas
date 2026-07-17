# Health

Canonical current-state description of the Core Health module.

## Scope

The Health module owns Atlas liveness and readiness reporting.

Current endpoints:

- `GET /health/live` — public liveness probe;
- `GET /health/ready` — public readiness probe.

Public health responses expose minimal operational information only. They do not expose exception messages, filesystem paths, connection details, credentials, raw configuration values, hostnames beyond public endpoint paths, or per-check diagnostic payloads.

Detailed health diagnostics are exposed through Admin System Status for authorized operators.

## Liveness

`/health/live` confirms that the Laravel process can respond.

It returns:

```json
{
  "status": "ok"
}
```

It does not check dependencies.

## Readiness

`/health/ready` evaluates whether Atlas is ready to serve traffic safely.

The public payload contains:

- overall status: `healthy`, `degraded`, or `unhealthy`;
- release version and release ID;
- checked timestamp;
- blocking failure count and total;
- degraded failure count and total.

The endpoint returns HTTP `503` only when at least one blocking dependency fails. Degraded optional dependencies keep HTTP `200` so Docker and deploy health checks can continue when the core application is safe to run.

Current checks:

- critical configuration;
- PostgreSQL;
- required Redis capabilities;
- queue backend reachability/configuration;
- writable application storage;
- scheduler heartbeat freshness;
- Meilisearch as degraded by default unless configured as critical.
- ClamAV as blocking when configured as critical, or when Files is deployed in production;
- Chromium/PDF renderer as degraded by default unless configured as critical.

Meilisearch is configured as critical through `ATLAS_HEALTH_MEILISEARCH_CRITICAL=true`.

ClamAV health configuration:

- `ATLAS_HEALTH_CLAMAV_CRITICAL`;
- `ATLAS_HEALTH_CLAMAV_HOST`;
- `ATLAS_HEALTH_CLAMAV_PORT`, default `3310`.

Chromium/PDF renderer health configuration:

- `ATLAS_HEALTH_CHROMIUM_CRITICAL`;
- `ATLAS_HEALTH_CHROMIUM_BINARY`.

## Admin Diagnostics

Admin System Status includes a Readiness card loaded from `GET /admin/system-status/readiness`.

The Admin card includes per-check labels, blocking/degraded classification, status, safe descriptions, and non-secret metadata such as scheduler freshness thresholds.

Admin System Status also includes a Release card loaded from `GET /admin/system-status/release`.

The Release card includes:

- `ATLAS_RELEASE_VERSION`;
- `ATLAS_RELEASE_ID`;
- current application environment;
- optional `ATLAS_RELEASE_DEPLOYED_AT`;
- optional `ATLAS_RELEASE_DEPLOYED_BY`;
- optional `ATLAS_RELEASE_SOURCE`.

Access requires:

- authenticated Admin route access;
- password confirmation;
- active team context;
- `admin.system-status.release` or `admin.system-status.readiness` permission, depending on the status endpoint.

## Security

Health checks must not log or expose secrets.

Public health endpoints are intentionally unauthenticated but minimal.

Admin diagnostics may include operational detail, but still must not expose secrets, full exception traces, credentials, arbitrary files, or raw service responses.

---
