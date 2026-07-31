# Production deployment, backup, restore, and recovery

Canonical production topology and operational procedures. This document complements the binding deployment roadmap phase.

## Production Topology

- The baseline production topology is one application host or VM running Docker Compose.
- Public traffic enters only through the reverse proxy on ports 80/443.
- PostgreSQL runs inside the production Docker Compose stack under project control and uses a durable persistent volume.
- Redis, Meilisearch, ClamAV, Horizon, queue workers, scheduler, and the Chromium renderer remain private.
- The production PHP runtime image includes Node.js, the runtime `playwright` package, and system Chromium so queued PDF exports can render through the same Node/Playwright/Chromium chain that readiness verifies.
- Queue workers and Horizon are configured to tolerate long managed-process and import jobs. Keep worker timeout and Redis `retry_after` aligned so operational scripts that may run for hours are not duplicated while still executing.
- Use versioned releases tied to exact commits/tags/images and switch through a `current` symlink only after readiness succeeds.
- Do not introduce Kubernetes, Docker Swarm, or a distributed cluster unless explicitly requested for a concrete project.
- Secrets stay outside the repository and application containers are never edited manually in place.

## Manual Ubuntu/Debian runtime parity

Atlas may also be installed directly on an Ubuntu/Debian-style server without the production containers. That installation must satisfy the same external mechanism contract as the Dev Container and production image:

- PHP with the extensions used by the production PHP image;
- Composer dependencies installed from the committed lockfile;
- Node.js available on `PATH`;
- production Node dependencies installed with `pnpm install --frozen-lockfile --prod`;
- an executable Chromium-compatible browser, preferably the distribution `chromium` package or an explicit path configured with `ATLAS_HEALTH_CHROMIUM_BINARY`;
- PostgreSQL, Redis, Meilisearch, queue workers, and scheduler configured to the same service contracts as the container topology;
- writable application storage and private file storage paths owned by the runtime user.

After installation, run `/health/ready` before accepting traffic. Admin System Status must show the same external mechanism state that would be expected in the Dev Container and production Docker stack. Chromium/PDF is healthy only when Node, the Atlas renderer script, the runtime `playwright` package, and an executable browser are all available to the PHP runtime user.

## Release checklist

Before any release containing copied third-party UI templates, paid component source, paid chart source, or proprietary design-system assets, verify and document that the license permits the intended Atlas use, redistribution, and source-transfer model.
