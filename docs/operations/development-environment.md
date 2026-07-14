# Development environment

Canonical operational rules for Dev Containers, Docker development services, VS Code integration, rebuild restrictions, and local workflow.

## Development Environment

The first project action is:

```text
git init
```

Create final root `AGENTS.md` at the beginning.

Because PHP, Composer, and Node are not assumed to exist on the host, establish Dev Container and Docker foundations before Laravel installation.

Development services:

- application workspace container;
- nginx;
- php-fpm;
- PostgreSQL;
- Redis;
- Meilisearch;
- Mailpit;
- pgAdmin;
- RedisInsight;
- Playwright browsers.

The application Dev Container also includes Docker CLI and the Docker Compose plugin.
It uses Docker-outside-of-Docker by mounting the host Docker socket at `/var/run/docker.sock`.
This is development-only tooling for inspecting and controlling the local Atlas Compose stack from inside VS Code.
The application Dev Container also includes Python 3, `pip`, and `venv` as development tooling for local automation, scripts, and future AI-adjacent experiments. Python is not part of the Atlas application runtime unless a later accepted phase explicitly adds it.

## Initial Dev Container start

Open the repository in VS Code and choose:

```text
Dev Containers: Reopen in Container
```

The Dev Container uses:

- `.devcontainer/devcontainer.json`;
- `.devcontainer/docker-compose.yml`;
- `docker/dev/app/Dockerfile`;
- `.env.example` for documented development defaults.

The application container runs as the non-root `vscode` user.
The `vscode` user uses `/bin/bash` as its login shell, and VS Code opens integrated terminals with the `bash` profile so command history, readline shortcuts, and bash completion work consistently.

To allow `vscode` to run Docker commands without `sudo`, set `DOCKER_GID` in the local development environment to the group id of the host Docker socket before the Dev Container is created:

```text
stat -c '%g' /var/run/docker.sock
```

The documented default is:

```text
DOCKER_GID=998
```

If the host uses a different socket group id, keep `.env.example` as the documented default and set the local `.env` override instead.

Forwarded/local development ports:

- `8000` for nginx serving Laravel through php-fpm;
- `5173` for Vite once installed;
- `5432` for PostgreSQL;
- `6379` for Redis;
- `7700` for Meilisearch;
- `8025` for Mailpit UI;
- `5050` for pgAdmin;
- `5540` for RedisInsight.

Run static validation from the host with:

```text
docker compose -f .devcontainer/docker-compose.yml --env-file .env.example config
```

After the Dev Container is running with the Docker socket mounted, the same command can also be run from inside the container:

```text
docker compose -f .devcontainer/docker-compose.yml --env-file .env.example config
```

To inspect the local Atlas development stack from inside the Dev Container:

```text
docker compose -f .devcontainer/docker-compose.yml --env-file .env.example ps
```

Validate required environment defaults with:

```text
bash scripts/validate-env.sh .env.example
```

Open the application from the host browser at:

```text
http://localhost:8000
```

The `app` service is the VS Code/Codex workspace container and intentionally runs `sleep infinity`.
HTTP traffic goes through the development `nginx` service and the separate `php-fpm` service.

To apply nginx/php-fpm service changes without rebuilding the Dev Container:

```text
docker compose -f .devcontainer/docker-compose.yml up -d --no-build nginx php-fpm
```

This may start existing runtime images and recreate only the affected runtime services. It must not rebuild the Dev Container.

### Dev Container rebuild rule

After the first successful Dev Container start, rebuilding is categorically forbidden as normal work because it may break the Codex VS Code extension.

When Dev Container changes are required:

1. edit Dev Container files;
2. apply equivalent commands inside the currently running container;
3. document that a rebuild will apply the changes cleanly in the future.

Changes that require new mounts, such as the Docker socket mount, cannot be fully applied to an already running container. In that case, install any equivalent in-container packages if useful, document the limitation, and apply the mount by reopening the Dev Container when the user is ready.

A rebuild is allowed only as a final unavoidable option and with explicit user awareness.

The agent must never rebuild unilaterally.

### VS Code extensions

Host:

- `ms-vscode-remote.remote-containers`

Inside Dev Container:

- `bmewburn.vscode-intelephense-client`
- `laravel.vscode-laravel`
- `MehediDracula.php-namespace-resolver`
- `xdebug.php-debug`
- `shufo.vscode-blade-formatter`
- `Vue.volar`
- `bradlc.vscode-tailwindcss`
- `dbaeumer.vscode-eslint`
- `esbenp.prettier-vscode`
- `stylelint.vscode-stylelint`
- `cweijan.vscode-database-client2`
- `docker.docker`
- `redhat.vscode-yaml`
- `EditorConfig.EditorConfig`
- `mikestead.dotenv`
- `DavidAnson.vscode-markdownlint`
- `usernamehw.errorlens`

Do not install Vetur.

Avoid extension packs and duplicate formatters.

---

## Dev Container Rebuild Scope

- The no-rebuild rule applies only to the development Dev Container after its first successful startup, because rebuilding it may break the Codex extension workflow.
- It does not apply to production containers or production images.
- Production images and containers must be rebuilt normally whenever application code, runtime dependencies, operating-system packages, configuration, or security updates require it.
- Any temporary package installation performed inside a running Dev Container must also be recorded in repository configuration so the environment remains reproducible.
