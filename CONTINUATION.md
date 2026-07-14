# Atlas Continuation Context

Atlas is a debt collection system developed as a modular monolith.

Canonical entry points:

1. `AGENTS.md` for permanent repository rules.
2. `WORKROAD.md` for the current phase and roadmap index.
3. The relevant `docs/roadmap/phase-*.md` file for binding phase work.
4. Relevant module, architecture, operations, and ADR documents only when the task touches them.

`CHATGPT_PROMPT.md` is reserved for separate project-owner ChatGPT architecture and roadmap discussions. Repository agents skip it during ordinary work unless explicitly asked to inspect or edit it.

## Current State

Atlas is in the initial technical foundation stage.

The first implementation phase is repository bootstrap, followed by the Docker and Dev Container skeleton before Laravel installation.

## Resume Rule

A fresh session must not rely on historical chat. Resume from `AGENTS.md`, `WORKROAD.md`, and only the relevant linked documentation.
