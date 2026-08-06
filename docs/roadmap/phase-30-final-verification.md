# Phase 30 — Final test audit, full-app E2E review, and foundation verification

**Status:** `not started`

## Objective

Verify the complete technical foundation after every prerequisite phase is finished and before debt collection business modules begin, with a full audit of the test suite and a browser-level E2E review of the whole application.

## Dependencies

- [Phase 29 — Production deployment, backup, restore, and rollback](phase-29-deployment-backup-rollback.md)
- [Quality gates and git](../operations/quality-gates-and-git.md)
- [Testing environment](../operations/testing-environment.md)
- [Production deployment, backup, and recovery](../operations/production-deployment-backup-and-recovery.md)

## Implementation contract

- Final verification is not a superficial test pass. It must prove that the Atlas can be cloned as a stable corporate base and that its important behavior is protected by meaningful automated tests.
- Phase 30 owns the full test-suite review. It must identify weak, missing, duplicated, overly implementation-focused, or misleading tests across PHPUnit, Vitest, and Playwright.
- Phase 30 owns a full browser-level application review through E2E coverage. Every shipped shell, major Admin area, operational workflow, localization surface, theme surface, permission/module gate, export/import/file/search/notification workflow, and critical error/empty/loading state must be exercised either by Playwright or by a documented lower-level test with a clear rationale.
- Existing tests must be evaluated for product value, not only pass/fail status. Tests that only prove that an implementation detail exists must be strengthened, replaced, or documented as structural guardrails.
- Rendered UI behavior must be verified where backend tests cannot prove the user experience. This includes visible copy, language switching, toast/notification behavior, table interactions, dialogs, destructive confirmations, empty states, dark/light theme rendering, browser console cleanliness, and asset/API request cleanliness.
- For localization, Phase 30 must prove that Polish and English are complete in rendered UI, backend-provided props, validation messages, flash/toast messages, notification text, breadcrumbs, forms, tables, and operational helper copy. It must include negative assertions against accidental English user-facing copy in Polish mode except for allowed technical diagnostic values.
- For messaging, Phase 30 must prove ownership and noise limits for user feedback. Workflows such as exports, imports, retries, scans, rebuilds, managed processes, and integrations must not create duplicate flashes, toast storms, or competing terminal notifications.
- The E2E suite must be treated as an application walkthrough, not just a smoke test. It should cover the real login path, active-team selection, Admin mode, navigation, permissions, module activation, core operational screens, and representative successful/failing workflows.
- The review must produce either implemented test hardening in this phase or explicit follow-up phases for any remaining gaps that are too large to close safely before final release.
- Cross-check every accepted decision against `AGENTS.md`, this file, documentation, ADRs, and tests.
- No accepted behavior may exist only in historical chat.
- Verify module activation, dependency blocking, ineffective permissions, role template behavior, admin mode, impersonation, manager hierarchy, TimeTracking isolation, reports, exports, imports, files, search, notifications, managed processes, light/dark themes, translations, backup/restore, deploy/rollback, liveness/readiness, and security controls.
- Review starter cloning and namespace/application identity replacement.
- Tag a stable release only after complete verification.
- `PRODUCTION_DEPLOYED=true` is set only in a Atlas after its first actual production deployment, not merely when the Atlas is released.

- The final repository context uses `AGENTS.md`, `WORKROAD.md`, the project-owner `CHATGPT_PROMPT.md`, and the canonical linked documentation under `docs/`.
- Working-only files such as temporary discussion notes, continuation prompts, and review drafts are not part of the final package.
- Before final delivery, ensure every accepted rule, implementation contract, task, module description, architectural decision, and operational procedure exists in its canonical root or `docs/` location.
- A fresh session must be able to resume by reading the root entry files and only the relevant linked documentation.

## Tasks

- [ ] Inventory all current PHPUnit, Vitest, and Playwright tests by layer, module, workflow, and risk area.
- [ ] Identify test gaps for all completed phases and classify each gap as unit, integration, feature, Vitest, or Playwright coverage.
- [ ] Review tests for weak assertions, implementation-only assertions, duplicated coverage, missing authorization checks, missing negative cases, and missing regression value.
- [ ] Strengthen or replace misleading tests that pass while the rendered application can still be wrong.
- [ ] Add or update a durable test coverage map under canonical testing documentation.
- [ ] Run complete backend test suite.
- [ ] Run complete frontend test suite.
- [ ] Run complete frontend type, lint, style, and build checks.
- [ ] Run Playwright in Chromium.
- [ ] Run Playwright in Firefox.
- [ ] Expand Playwright into a full application walkthrough covering Auth, active team, regular shell, Admin shell, Admin mode, navigation, permissions, module gates, operational screens, dialogs, tables, exports, imports, files, search, notifications, and error states.
- [ ] Add rendered UI localization E2E coverage for Polish and English on all major shells and Admin operational screens.
- [ ] Add negative rendered UI assertions that Polish-mode screens do not expose accidental English user-facing copy except approved technical diagnostic values.
- [ ] Add E2E coverage for toast/flash/notification ownership, including export workflows proving no managed-process toast storm.
- [ ] Add E2E coverage for light and dark themes across Auth, regular application, and Admin shells.
- [ ] Add E2E coverage for permission-gated and module-gated visibility using deterministic e2e fixtures.
- [ ] Add E2E coverage for browser-console cleanliness and unexpected failed asset/API requests across the full walkthrough.
- [ ] Review Vitest coverage for frontend composables, formatters, UI services, localization helpers, network handling, table state, modal/toast behavior, and route/action helpers.
- [ ] Review PHPUnit Unit coverage for pure domain/application logic, value objects, enums, typed identifiers, policies, settings, and structural architecture rules.
- [ ] Review PHPUnit Integration coverage for persistence, Redis, queues, cache, search, files, notifications, outbox, managed processes, exports, imports, and module providers.
- [ ] Review PHPUnit Feature coverage for HTTP workflows, validation, authorization, Inertia props, backend localization, flash/session behavior, and protected Admin operations.
- [ ] Run production frontend build.
- [ ] Run PHPStan/Larastan at maximum configured level.
- [ ] Run dependency vulnerability checks.
- [ ] Verify all enabled modules and reduced modes.
- [ ] Verify light and dark themes.
- [ ] Verify UI translation completeness.
- [ ] Verify admin panel.
- [ ] Verify every Admin operational area manually and through E2E where possible.
- [ ] Verify flash/toast/notification behavior manually and through E2E for representative workflows.
- [ ] Verify impersonation.
- [ ] Verify module activation.
- [ ] Verify backup.
- [ ] Verify restore.
- [ ] Verify deploy.
- [ ] Verify rollback.
- [ ] Verify readiness and liveness.
- [ ] Review documentation completeness.
- [ ] Review ADR completeness.
- [ ] Review `CHATGPT_PROMPT.md`.
- [ ] Cross-check all accepted decisions against `AGENTS.md`, `WORKROAD.md`, ADRs, and canonical documentation under `docs/`.
- [ ] Verify that no accepted rule exists only in historical chat context.
- [ ] Produce a final test hardening report listing fixed gaps, remaining accepted risks, and any follow-up phases required before business-module development.
- [ ] Review starter cloning procedure.
- [ ] Mark Atlas stable.
- [ ] Tag the first stable Atlas release.
- [ ] Set `PRODUCTION_DEPLOYED=true` only after the first actual production deployment of a Atlas.
- [ ] Verify the final package contains `AGENTS.md`, the lightweight `WORKROAD.md`, `CHATGPT_PROMPT.md`, and all canonical linked documentation under `docs/`.
- [ ] Verify no accepted decision exists only in a working/context file.
- [ ] Verify a fresh session can resume from the root entry files plus only the relevant linked documentation.
- [ ] Exclude all working-only discussion, continuation, and review files from the final delivery package.

## Completion criteria

- [ ] All accepted technical-foundation contracts are implemented, tested, documented, and verifiable from canonical files.
- [ ] The test suite has been audited across PHPUnit, Vitest, and Playwright, and known weak spots have been strengthened or explicitly scheduled as follow-up work.
- [ ] The Playwright suite covers a full representative walkthrough of the application rather than only smoke-level shell checks.
- [ ] Rendered UI localization, theme behavior, permissions/module gates, operational workflows, and message ownership are protected by automated tests where browser behavior matters.
- [ ] No major shipped screen or workflow relies only on manual confidence without a documented testing rationale.
- [ ] Backup, restore, deploy, rollback, readiness, security controls, module activation, Admin mode, impersonation, manager hierarchy, TimeTracking isolation, reports, translations, and themes are verified.
- [ ] No accepted behavior exists only in chat history or working-only files.
- [ ] Atlas is ready for debt collection business-module phases.
