# Atlas documentation

This directory contains canonical documentation for Atlas.

Read only the documentation relevant to the task. `AGENTS.md` defines the required reading discipline and the rules for keeping this documentation current.

## Entry points

- [`../README.md`](../README.md) — high-level system overview.
- [`../AGENTS.md`](../AGENTS.md) — permanent engineering and agent-working rules.
- [`../WORKROAD.md`](../WORKROAD.md) — lightweight roadmap index.
- [`roadmap/`](roadmap/) — binding implementation contracts, tasks, and historical evolution.
- [`modules/`](modules/) — canonical current behavior of individual modules.
- [`architecture/`](architecture/) — cross-module architecture and shared mechanisms.
- [`operations/`](operations/) — development, deployment, runtime, backup, recovery, and observability procedures.
- [`decisions/`](decisions/) — one durable architectural decision per ADR.
- [`guides/`](guides/) — focused workflows that do not belong to another canonical category.

## Maintenance rule

Create or update documentation together with the code and configuration it describes.

Do not duplicate detailed behavior between files. Link to the canonical document.

When a document becomes too large, split it losslessly and retain a lightweight `index.md` entry point.
