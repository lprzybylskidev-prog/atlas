## Phase 1 — Dev Container and Docker skeleton

### Implementation contract

- The user does not keep PHP, Composer, or Node installed on the host; the development environment must be usable entirely through Docker and VS Code Dev Containers.
- Development services must include the application, PostgreSQL, Redis, Meilisearch, Mailpit, pgAdmin, RedisInsight, and Playwright browsers.
- Production services must include nginx, php-fpm, workers, scheduler, PostgreSQL, Redis, Meilisearch, and a backup service.
- Mailpit, pgAdmin, and RedisInsight are development-only.
- Only HTTP and HTTPS may be exposed publicly in production.
- Persistent data must use durable non-public volumes.
- `APP_TIMEZONE` centrally controls timezone configuration, defaulting to `Europe/Warsaw`, and must be applied to PHP, Laravel, PostgreSQL, containers, queues, scheduler, logs, and reports.
- Secrets must never be committed. Production uses Docker secrets or protected environment variables.
- `.env.example` and the development `.env` must be kept in sync whenever configuration changes.
- Critical configuration is validated at startup; critical values must not silently fall back.
- Required VS Code extensions are exactly those listed in `AGENTS.md`; Vetur, extension packs, and duplicate formatters are forbidden.
- After the first successful Dev Container start, a rebuild is forbidden as normal work because it can break the Codex extension.
- For later Dev Container changes, edit the container definition and apply equivalent commands inside the currently running container.
- A rebuild is allowed only as a final unavoidable action after explicitly informing the user. Codex must never rebuild unilaterally.
- The initial production Docker setup is a correct skeleton. Final deploy, backup, restore, and rollback mechanics are completed after the application foundations exist.
- The prohibition on rebuilds applies only to the development Dev Container after its first successful start.
- It exists because Dev Container rebuilds may break or destabilize the Codex extension.
- This rule does not restrict rebuilding production images or production containers.
- Production containers must be rebuilt whenever required by code, dependency, configuration, base-image, or security changes.
- Temporary changes made inside a running Dev Container must also be reflected in repository-controlled Dev Container/Docker configuration.

- [x] Create Docker and Dev Container architecture before Laravel installation.
- [x] Add development containers for application, PostgreSQL, Redis, Meilisearch, Mailpit, pgAdmin, and RedisInsight.
- [x] Add Playwright browser support in development.
- [x] Add initial production container skeleton for nginx, php-fpm, workers, scheduler, PostgreSQL, Redis, Meilisearch, and backups.
- [x] Ensure only HTTP/HTTPS are intended for public exposure in production.
- [x] Add durable volumes for PostgreSQL, Redis, Meilisearch, and application storage.
- [x] Add shared network and health-check foundations.
- [x] Add non-root container users where practical.
- [x] Add timezone configuration through `APP_TIMEZONE`.
- [x] Add Docker secrets/protected-environment conventions.
- [x] Add development `.env.example`.
- [x] Add startup validation for critical environment variables.
- [x] Configure required VS Code extensions.
- [x] Configure format-on-save.
- [x] Document the hard Dev Container no-rebuild rule after first successful start.
- [ ] Start the Dev Container successfully for the first time.
- [x] Record the exact non-rebuild workflow for future Dev Container changes.
- [ ] Commit the infrastructure skeleton.
