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

- application;
- PostgreSQL;
- Redis;
- Meilisearch;
- Mailpit;
- pgAdmin;
- RedisInsight;
- Playwright browsers.

### Dev Container rebuild rule

After the first successful Dev Container start, rebuilding is categorically forbidden as normal work because it may break the Codex VS Code extension.

When Dev Container changes are required:

1. edit Dev Container files;
2. apply equivalent commands inside the currently running container;
3. document that a rebuild will apply the changes cleanly in the future.

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
