# Atlas

Atlas is a large debt collection system developed for a debt collection company.

The system is designed as a modular monolith with strict module boundaries, typed public contracts, explicit infrastructure, strong security controls, complete auditability, and documentation that remains usable as the project grows.

The current roadmap begins with the technical and operational foundation required before the debt collection business modules are implemented. Atlas is the final product and is developed continuously as one system.

## Core principles

- **Explicit over magic**
- **Modular monolith over accidental coupling**
- **Application and Domain logic separated from frameworks**
- **Secure defaults and complete auditability**
- **PostgreSQL-first data design**
- **One source of truth for every contract**
- **Documentation that scales without loading the whole project into context**
- **Long-term evolution without rewriting completed history**

## Technology foundation

Atlas is designed around:

- Laravel and modern PHP
- PostgreSQL
- Redis for cache, sessions, queues, locks, and rate limiting
- Vue 3, TypeScript, Inertia, Vite, and Tailwind CSS
- TailAdmin Free initially, with an explicit licensing checkpoint before any Pro-only use
- PHPUnit, PHPStan/Larastan, and Pint
- ESLint, Prettier, Stylelint, Vitest, and Playwright
- Docker Compose and VS Code Dev Containers
- Horizon, Sentry, Meilisearch, ClamAV, and Chromium/Playwright where their capabilities are active

The permanent PHP root namespace is:

```text
App
```

Baseline project identity:

- Repository: `lprzybylskidev-prog/atlas`
- Docker Compose project: `atlas`
- Default database: `atlas`
- Application name: `Atlas`

## Repository documentation

Start with these files:

1. [`AGENTS.md`](AGENTS.md) — permanent engineering, architecture, security, quality, documentation, and agent-working rules.
2. [`WORKROAD.md`](WORKROAD.md) — lightweight roadmap index and current phase pointer.

[`CHATGPT_PROMPT.md`](CHATGPT_PROMPT.md) is a separate helper prompt for the project owner during external ChatGPT architecture and roadmap discussions. Repository agents skip it during ordinary work unless explicitly asked to inspect or edit it.

Then read only the documentation relevant to the task:

```text
docs/
├── roadmap/       # Binding phase contracts, tasks, and implementation history
├── modules/       # Canonical current-state documentation for each module; see docs/modules/README.md
├── decisions/     # One architectural decision per ADR
├── architecture/  # Current cross-module architecture; see docs/architecture/README.md
├── operations/    # Development, deployment, and runtime procedures; see docs/operations/README.md
└── guides/        # Focused workflows and usage guides
```

Do not load the entire documentation tree by default. Follow the reading discipline defined in `AGENTS.md`.

This README is living system documentation. It must be expanded and updated as Atlas gains business modules, user-facing capabilities, integrations, operational procedures, and major architectural changes.

## Roadmap

Atlas is implemented from the ordered roadmap in [`WORKROAD.md`](WORKROAD.md).

Each roadmap phase has its own file under [`docs/roadmap/`](docs/roadmap/). Phase files contain the binding implementation contract and executable checklist. Completed history is never removed or rewritten.

New significant business modules, capabilities, migrations, and later initiatives receive new sequential phase files.

## Module architecture

Modules live under:

```text
app/Modules/<category>/<Module>/
├── Domain/
├── Application/
├── Infrastructure/
└── Presentation/
```

General rules:

- Domain is framework-independent.
- Application contains use cases, orchestration, transactions, Commands, Queries, and public contracts.
- Infrastructure contains persistence and external adapters.
- Presentation contains HTTP/UI delivery.
- Cross-module access uses only typed public APIs and Integration Events.
- Reliable Integration Events use the transactional Outbox.
- Direct access to another module's models, tables, Domain internals, or Infrastructure is prohibited.

Detailed rules live in `AGENTS.md` and the relevant architecture and module documentation.

## System scope

The planned shared system capabilities include:

- Identity and authentication
- Users
- Teams
- Authorization
- Manager hierarchy
- Sessions and active-team context
- Audit and security audit
- Settings and localization
- Notifications
- Files
- Admin operations
- Health and readiness
- Module availability and activation
- Imports
- Search
- Integrations
- Feature flags
- TimeTracking

Debt collection business functionality belongs in `Application` modules and will be added as later roadmap phases. These modules may cover areas such as portfolios, debtors, creditors, claims, cases, payments, settlements, contact history, documents, legal proceedings, enforcement proceedings, reporting, and integrations with external debt collection services. Exact scope is defined only through accepted roadmap decisions.

## Development workflow

Before non-trivial work:

1. Read `AGENTS.md`.
2. Read the `WORKROAD.md` index.
3. Open only the relevant roadmap phase file.
4. Read documentation for the modules touched by the task.
5. Read only directly related ADRs, architecture documents, and operations documents.
6. Continue from the first unfinished applicable roadmap item unless the user explicitly selects another.

Local quality gates and Lefthook are the baseline workflow. Atlas intentionally does not bundle a CI provider; CI may be added later if the company requires it, using the same public quality commands.

## Environments

Atlas requires:

- local/development
- production

Staging is optional when operationally useful.

The baseline production topology uses one host or VM with Docker Compose. PostgreSQL runs inside the production Compose stack with durable storage. Only the reverse proxy exposes public ports.

The Dev Container no-rebuild restriction applies only to the development Dev Container after its first successful start. It does not restrict normal rebuilding of production images and containers.

The development application container includes Docker CLI and the Docker Compose plugin through a development-only host Docker socket mount, allowing the local Atlas Compose stack to be inspected from inside VS Code.

## Current status

Atlas currently has a detailed architecture, implementation roadmap, documentation system, Docker/Dev Container foundation, and a Laravel 13 application foundation running on PostgreSQL and Redis in development.

The Laravel foundation includes Fortify, Horizon, Scout with Meilisearch, Sentry, Spatie Laravel Permission with teams, Diglactic Breadcrumbs, Ziggy, Pint, PHPUnit, PHPStan/Larastan, Vite/Tailwind assets, committed Polish/English Laravel translation catalogs, request IDs, release identity, and startup configuration validation.

The baseline frontend shell is available through Inertia/Vue with strict TypeScript, light and dark themes, responsive auth/application/admin layouts, PL/EN frontend localization with Polish default for regular UI, English-only Admin shell copy, the Atlas logo and favicon, and a local demo reset command documented in [`docs/operations/seeding-and-demo-data.md`](docs/operations/seeding-and-demo-data.md).

The implementation status and first unfinished phase are always shown in [`WORKROAD.md`](WORKROAD.md).

As the project grows, this README must present the current high-level system scope, major modules, supported workflows, setup entry points, and operational expectations.

## License and third-party assets

Third-party dependencies and assets must be used according to their licenses.

TailAdmin Pro requires explicit purchase confirmation and license verification before any Pro-only asset is introduced into Atlas.
