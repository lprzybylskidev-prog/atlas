# Phase 35 — Final test audit, full-app E2E review, and foundation verification

**Status:** `not started`

## Objective

Verify the complete technical foundation after every prerequisite phase is finished and before debt collection business modules begin, with a full audit of the test suite and a browser-level E2E review of the whole application.

## Dependencies

- [Phase 28 — Foundation repair and consolidation](phase-28-foundation-repair-and-consolidation.md)
- [Phase 29 — Foundation acceptance repair and rendered workflow closure](phase-29-foundation-acceptance-repair.md)
- [Phase 30 — Authorization, Team Structure, and mutation feedback repair](phase-30-authorization-team-structure-feedback-repair.md)
- [Phase 31 — Optional internal company chat, calendar, calls, meetings, and realtime communication](phase-31-chat.md)
- [Phase 32 — Error reporting, user bug reports, and application diagnostics](phase-32-error-reporting-and-diagnostics.md)
- [Phase 33 — Private production deployment, installer, backup, restore, and rollback](phase-33-deployment-backup-rollback.md)
- [Phase 34 — Database Query Efficiency and Route Performance Audit](phase-34-database-query-efficiency-and-route-performance-audit.md) must be complete before this phase begins.
- [Quality gates and git](../operations/quality-gates-and-git.md)
- [Testing environment](../operations/testing-environment.md)
- [Production deployment, backup, and recovery](../operations/production-deployment-backup-and-recovery.md)

## Implementation contract

- Final verification is not a superficial test pass. It must prove that the Atlas can be cloned as a stable corporate base and that its important behavior is protected by meaningful automated tests.
- Phase 35 owns the full test-suite review. It must identify weak, missing, duplicated, overly implementation-focused, or misleading tests across PHPUnit, Vitest, and Playwright.
- Phase 35 owns a full browser-level application review through E2E coverage. Every shipped shell, major Admin area, operational workflow, localization surface, theme surface, permission/module gate, export/import/file/search/notification workflow, and critical error/empty/loading state must be exercised either by Playwright or by a documented lower-level test with a clear rationale.
- Existing tests must be evaluated for product value, not only pass/fail status. Tests that only prove that an implementation detail exists must be strengthened, replaced, or documented as structural guardrails.
- Rendered UI behavior must be verified where backend tests cannot prove the user experience. This includes visible copy, language switching, toast/notification behavior, table interactions, dialogs, destructive confirmations, empty states, dark/light theme rendering, browser console cleanliness, and asset/API request cleanliness.
- For localization, Phase 35 must prove that Polish and English are complete in rendered UI, backend-provided props, validation messages, flash/toast messages, notification text, breadcrumbs, forms, tables, and operational helper copy. It must include negative assertions against accidental English user-facing copy in Polish mode except for allowed technical diagnostic values.
- For messaging, Phase 35 must prove ownership and noise limits for user feedback. Workflows such as exports, imports, retries, scans, rebuilds, managed processes, and integrations must not create duplicate flashes, toast storms, or competing terminal notifications.
- The E2E suite must be treated as an application walkthrough, not just a smoke test. It should cover the real login path, active-team selection, Admin mode, navigation, permissions, module activation, core operational screens, and representative successful/failing workflows.
- The review must produce either implemented test hardening in this phase or explicit follow-up phases for any remaining gaps that are too large to close safely before final release.
- Cross-check every accepted decision against `AGENTS.md`, this file, documentation, ADRs, and tests.
- No accepted behavior may exist only in historical chat.
- Verify module activation, dependency blocking, ineffective permissions, role template behavior, admin mode, impersonation, manager hierarchy, TimeTracking isolation, Chat, reports, exports, imports, files, search, notifications, managed processes, light/dark themes, translations, backup/restore, deploy/rollback, liveness/readiness, and security controls.
- Reverify the repaired Teams and Authorization foundation: Team Edit read-only authorization, User Edit role-derived permission behavior, current Source semantics, the Employee / Manager / Head Manager Team Structure role model, Head Manager whole-Team scope, multi-manager relationships, Team Structure drag and drop plus its mobile/keyboard alternative, visible mutation errors, flash-message delivery and non-duplication, and the unresolved-interpolation guard.
- Phase 31 communication verification must preserve the complete messaging audit and additionally cover the shared Core Calendar, private personal events, Europe/Warsaw recurrence, Month/Week/Day/Agenda views, Free/Busy privacy, direct/group/Team Calls, device setup/preferences, Atlas-authorized LiveKit access, Calls that cannot be recorded, one active RTC session per user, Meetings, invitations/RSVP, recurring Meetings, persistent Meeting chat, moderation, lock/kick semantics, attendance, screen sharing, empty-room cleanup, minimize/rejoin, Meeting-only recording, pause/resume/finalization, Files-owned recordings, controlled recording sharing, separate recording retention, provider-disabled transcription UI, the provider-neutral queued transcription boundary, transcript versioning/sharing/Search, and the Admin privacy boundary.
- Diagnostics verification must cover automatic backend and frontend Technical Issues; queue, scheduler, and Managed Process failure capture; expected-error exclusions; fingerprinting and deduplication; exact aggregate counting under flood sampling; regression reopen; automatic severity and manual override; User Bug Reports; Files-owned screenshots and attachments; My Reports; Admin Errors & Reports; issue/report linking and Primary relation; issue merge aliases; sanitized text/JSON diagnostic copy; related-log correlation; sanitizer and privacy boundaries; private source maps; Technical Issue occurrence and User Bug Report retention; Health integration with liveness/readiness independence; Critical/regression Notifications and cooldown; backup/restore of Diagnostics and User Report Files; exact-release source-map association; and absence of any newly introduced Sentry or external error-monitoring dependency.
- Deployment verification must cover Atlas-managed self-hosted LiveKit, trusted-network RTC/TURN connectivity, separate Egress readiness/failure isolation, protected recording staging, compatible pinned runtime versions, and backup/restore of Files-owned recordings and PostgreSQL-owned transcript state without exposing private Meeting content in operational surfaces.
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
- [ ] Reverify Team Edit read-only authorization, User Edit role-derived permissions, current Source semantics, structural roles, Head Manager whole-Team scope, multi-manager relationships, drag/drop plus keyboard/mobile assignment, visible mutation errors, flash delivery/non-duplication, and unresolved-interpolation protection.
- [ ] Verify Chat module activation and permission behavior.
- [ ] Verify canonical Chat direct-conversation uniqueness and Team Chat membership synchronization.
- [ ] Verify that Admin cannot bypass private Chat content authorization.
- [ ] Verify Chat Search authorization and delete-for-me privacy.
- [ ] Verify Chat Files/ClamAV attachments and voice messages.
- [ ] Verify Chat Reverb delivery, authorization, reconnect reconciliation, presence, typing, unread, and read state.
- [ ] Verify Chat unread dropdown, modal, browser-native notifications, retention, localization, and mobile workflows.
- [ ] Verify Chat browser console and network cleanliness.
- [ ] Verify shared Core Calendar Month/Week/Day/Agenda views, private personal events, Europe/Warsaw recurrence, reminders, and privacy-preserving Free/Busy.
- [ ] Verify direct, group, and Team audio/video Calls, device setup/preferences and switching, screen sharing, missed-call behavior, one active RTC session per user, rejoin, and the prohibition on ad-hoc Call recording.
- [ ] Verify LiveKit room/token authorization cannot be bypassed and Laravel Reverb remains the application-event and text-message realtime transport.
- [ ] Verify immediate, scheduled, and recurring Meetings, invitation/RSVP/invite-more/remove behavior, one persistent Meeting chat per Meeting series, Calendar composition, and operation without the organizer present.
- [ ] Verify Meeting moderation, one active screen share, occurrence-ban kick semantics, Meeting lock, attendance visibility, minimize/rejoin, and 15-minute empty-room cleanup.
- [ ] Verify Meeting-only recording authorization, visible REC/Paused states, pause/resume/finalization, one composed Files artifact, participant access, controlled sharing to active Atlas users, and the no-reshare recipient boundary.
- [ ] Verify recording retention is separate, defaults to indefinite, deletes dependent transcript state, and leaves no orphan Files or stale Search projections.
- [ ] Verify transcription UI is hidden when no provider is configured, all transcription work is queued, the provider-neutral contract works with the deterministic fake adapter, and historical recordings can be requested after later provider enablement.
- [ ] Verify transcript editing/version history, participant and controlled external-user sharing, current-version-only recipient visibility, recording dependency, and authorization-safe Meilisearch behavior.
- [ ] Verify Admin cannot bypass private Chat, Meeting, recording, transcript, Search, download, or export authorization and sees only safe operational aggregates.
- [ ] Verify Atlas-managed self-hosted LiveKit, RTC/TURN connectivity, separate Egress readiness and failure isolation, protected recording staging, and compatible pinned runtime versions.
- [ ] Verify automatic backend and frontend Technical Issue capture.
- [ ] Verify queue, scheduler, and Managed Process technical failure capture.
- [ ] Verify expected 404/403/422/domain/offline exclusions.
- [ ] Verify Technical Issue fingerprinting, deduplication, dynamic-route normalization, and cross-release identity.
- [ ] Verify flood sampling preserves exact aggregates and affected-user impact.
- [ ] Verify regression reopen plus automatic severity and authoritative manual override.
- [ ] Verify global User Bug Report, Files-owned screenshots/attachments, safe screenshot fallback, and My Reports.
- [ ] Verify Admin Errors & Reports tables, filters, details, privacy, linking, Primary relation, and report lifecycle.
- [ ] Verify Technical Issue merge aliases and the absence of Undo Merge/manual Split.
- [ ] Verify sanitized Copy diagnostics as text and JSON, related-log correlation, and on-demand source snippets.
- [ ] Verify sanitizer/privacy boundaries, raw-session exclusion, bounded breadcrumbs, and private source-map serving restrictions.
- [ ] Verify detailed occurrence retention, indefinite aggregate retention, User Bug Report retention, Files cleanup, and Managed Processes purge.
- [ ] Verify Diagnostics Health/System Status integration while liveness/readiness remain independent.
- [ ] Verify Critical/regression/impact Notifications and cooldown behavior.
- [ ] Verify production backup and restore of Diagnostics PostgreSQL data and User Bug Report Files.
- [ ] Verify exact-release private source-map and browser-asset association through deploy and rollback.
- [ ] Verify no Sentry or external error-monitoring dependency was introduced by Phases 32 or 33.
- [ ] Verify backup and restore of Files-owned Meeting recordings and PostgreSQL-owned transcript state.
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
- [ ] Backup, restore, deploy, rollback, readiness, security controls, module activation, Admin mode, impersonation, manager hierarchy, TimeTracking isolation, Chat, Core Calendar, Calls, Meetings, LiveKit/TURN/Egress, recordings, provider-neutral transcription behavior, Diagnostics, User Bug Reports, reports, translations, and themes are verified.
- [ ] No accepted behavior exists only in chat history or working-only files.
- [ ] Atlas is ready for debt collection business-module phases.
