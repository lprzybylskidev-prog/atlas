# Operations documentation

Read the operational document relevant to the environment or runtime capability being changed.

Phase 28 adds accepted target contracts for reproducible runtime parity, production image prerequisites, queues/Horizon, scheduler, Files/ClamAV, Chromium/PDF, seeding, bilingual mail testing, migration reset after squash, and `composer check:foundation`. These targets are tracked in [Phase 28 — Foundation repair and consolidation](../roadmap/phase-28-foundation-repair-and-consolidation.md) and summarized in the relevant operational documents below; the implementation is not started yet.

- [Development environment](development-environment.md) — Dev Containers, Docker development services, VS Code, and rebuild rules.
- [Project identity and lifecycle](project-identity-and-lifecycle.md) — Atlas naming, repository, Docker/database identity, and production deployment lifecycle.
- [Seeding and demo data](seeding-and-demo-data.md) — production-safe technical seeders and development-only demo data.
- [Quality gates, Git, and commits](quality-gates-and-git.md) — commands, hooks, checks, and commit workflow.
- [Testing environment](testing-environment.md) — PHPUnit, Vitest, Playwright, local database/Redis isolation, deterministic fixtures, and future CI lanes.
- [Health, observability, maintenance, and runtime diagnostics](health-observability-and-maintenance.md) — health, readiness, logging, alerts, diagnostics, and maintenance.
- [Production deployment, backup, restore, and recovery](production-deployment-backup-and-recovery.md) — production topology and recovery procedures.
- [Realtime, network, and browser behavior](realtime-network-and-browser.md) — WebSockets, reconnects, failures, tabs, and browser storage.
