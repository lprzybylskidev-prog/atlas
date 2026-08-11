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

The Chromium/PDF readiness check verifies the real PDF rendering runtime, not only the browser binary. A healthy check requires:

- a configured or auto-discovered executable Chromium-compatible browser;
- `node` available on `PATH`;
- the Atlas PDF renderer script at `tools/reports/render-pdf.mjs`;
- the runtime `playwright` package installed under `node_modules`.

When `ATLAS_HEALTH_CHROMIUM_BINARY` is not set, Atlas auto-discovers common runtime locations, including Playwright browsers under `/ms-playwright`, system `chromium`/`chromium-browser`/Google Chrome on `PATH`, and common `/usr/bin/*` browser paths.

## Module Technical Availability

Phase 28 connects readiness to module activation through `App\Shared\Application\Modules\Contracts\ModuleTechnicalAvailability`.

Current module availability rules:

- modules without declared `ModuleDefinition::healthChecks()` are technically available when deployed;
- modules with declared health checks are technically available only when every named readiness check is present and not unhealthy;
- degraded readiness remains visible to operators but does not block ModuleGate unless the underlying check reports an unhealthy state;
- stale decorative health-check names are invalid for current manifests and must be removed or backed by a real readiness check before use;
- the Health module reports the readiness graph but does not declare all checks as requirements for its own availability.

Module activation validates this contract before enabling a module immediately or scheduling a future enablement. Disabling an unavailable module remains allowed so operators can move the system to a safer state.

## Admin Diagnostics

Admin System Status includes a Readiness card loaded from `GET /admin/system-status/readiness`.

The Admin card includes per-check labels, blocking/degraded classification, status, safe descriptions, and non-secret metadata such as scheduler freshness thresholds.

Admin System Status also includes a Release card loaded from `GET /admin/system-status/release`.

Module issue rows inside Admin System Status are not Health-owned readiness checks. They are collected from module-owned `ModuleOperationalDiagnostics` contributors, while Health continues to own liveness/readiness and technical availability input.

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

## Phase 28 foundation repair target

Current state: Health exposes real liveness/readiness and operator diagnostics, and ModuleGate consumes declared readiness requirements through technical availability. The production foundation smoke verifies queue/Horizon, scheduler heartbeat, storage, Meilisearch, ClamAV/EICAR, Chromium/PDF, and the backup/storage boundary.

Target state: Health provides real dependency-chain checks for module technical availability and runtime readiness across development, test, production, and manual server modes, without leaking secrets.

Tracked issue IDs: `P28-MOD-002`, `P28-MOD-003`, `P28-RUNTIME-007`, `P28-RUNTIME-012`, `P28-MODAUD-008`.
