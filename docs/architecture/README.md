# Architecture documentation

Read these documents only when the task touches the described cross-module mechanism.

- [Modular-monolith architecture](modular-monolith.md) — layers, module boundaries, public contracts, Outbox, IDs, database rules, cache, queues, and API.
- [Module registry and activation](module-registry-and-activation.md) — ModuleGate, dependencies, availability, schedules, and deactivation guards.
- [Administrative mode and impersonation](admin-mode-and-impersonation.md) — Admin mode, high-risk reauthentication, impersonation, prohibitions, and audit.
- [Audit, privacy, deletion, and anonymization](audit-privacy-and-deletion.md) — audit evidence and irreversible data lifecycle operations.
- [Frontend and shared UI architecture](frontend-ui.md) — themes, layouts, routing, accessibility, forms, and shared Atlas UI.
- [Atlas UI glossary](ui-glossary.md) — binding Polish/English concepts, page and form labels, action verbs, status labels, and technical internal names.
- [Tables, reports, exports, charts, and print](tables-reports-exports-and-print.md) — shared reporting and document-generation behavior.
- [Data contracts, formatting, validation, errors, and concurrency](data-contracts-validation-and-concurrency.md) — transport and application boundary conventions.
- [Bilingual mail](mail.md) — shared branded template, translation keys, effective-locale ordering, safe content, and delivery-path rules.
- [Security baseline](security-baseline.md) — cross-system security, rate limits, and malware-scanning rules.
- [Foundation repair target contracts](foundation-repair-target-contracts.md) — accepted Phase 28 target contracts for module graph, audit, UI, migrations, seeders, mail, runtime, and guardrails.
