# Production deployment, backup, restore, and recovery

Canonical production topology and operational procedures. This document complements the binding deployment roadmap phase.

## Production Topology

- The baseline production topology is one application host or VM running Docker Compose.
- Public traffic enters only through the reverse proxy on ports 80/443.
- PostgreSQL runs inside the production Docker Compose stack under project control and uses a durable persistent volume.
- Redis, Meilisearch, ClamAV, Horizon, queue workers, scheduler, and the Chromium renderer remain private.
- Use versioned releases tied to exact commits/tags/images and switch through a `current` symlink only after readiness succeeds.
- Do not introduce Kubernetes, Docker Swarm, or a distributed cluster unless explicitly requested for a concrete project.
- Secrets stay outside the repository and application containers are never edited manually in place.
