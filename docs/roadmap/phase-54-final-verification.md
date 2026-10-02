# Phase 54 — Final test audit, full-app E2E review, and foundation verification

**Status:** `not started`

## Objective

Verify the complete technical foundation after every prerequisite phase is finished and before future Application-domain development begins, with a full audit of the test suite and a browser-level E2E review of the whole application.

## Dependencies

- [Phase 28 — Foundation repair and consolidation](phase-28-foundation-repair-and-consolidation.md)
- [Phase 29 — Foundation acceptance repair and rendered workflow closure](phase-29-foundation-acceptance-repair.md)
- [Phase 30 — Authorization, Team Structure, and mutation feedback repair](phase-30-authorization-team-structure-feedback-repair.md)
- [Phase 31 — Optional internal company chat, calendar, calls, meetings, and realtime communication](phase-31-chat.md)
- [Phase 32 — Product neutralization and domain-assumption removal](phase-32-product-neutralization.md)
- [Phase 33 — Error reporting, user bug reports, and application diagnostics](phase-33-error-reporting-and-diagnostics.md)
- [Phase 34 — Foundation extension-point, duplication, and consumer audit](phase-34-foundation-extension-points-and-duplication-audit.md)
- [Phase 35 — Module-owned routing, navigation, breadcrumbs, and application surfaces](phase-35-module-owned-routing-navigation-breadcrumbs.md)
- [Phase 36 — Runtime Settings, localized reference data, Team timezones, and Business Calendars](phase-36-runtime-settings-localized-reference-data-team-timezones-business-calendars.md)
- [Phase 37 — Reusable data integrity and business-support primitives](phase-37-reusable-data-integrity-business-primitives.md)
- [Phase 38 — Enterprise OIDC identities and authentication-method separation](phase-38-enterprise-oidc-external-identities.md)
- [Phase 39 — Atlas API, Service Accounts, Integration Events, OpenAPI, and webhooks](phase-39-api-service-accounts-integration-events-webhooks.md)
- All Optional capability phases from [Phase 40](phase-40-resource-collaboration-extensibility.md) through [Phase 50](phase-50-business-activity-timeline.md).
- [Phase 51 — Developer extension contract, module documentation, and MDK](phase-51-developer-extension-contract-and-mdk.md)
- [Phase 52 — Private production deployment, installer, backup, restore, and rollback](phase-52-deployment-backup-rollback.md)
- [Phase 53 — Database Query Efficiency and Route Performance Audit](phase-53-database-query-efficiency-and-route-performance-audit.md) must be complete before this phase begins.
- [Quality gates and git](../operations/quality-gates-and-git.md)
- [Testing environment](../operations/testing-environment.md)
- [Production deployment, backup, and recovery](../operations/production-deployment-backup-and-recovery.md)

## Implementation contract

- Final verification is not a superficial test pass. It must prove that the Atlas can be cloned as a stable corporate base and that its important behavior is protected by meaningful automated tests.
- Phase 54 owns the full test-suite review. It must identify weak, missing, duplicated, overly implementation-focused, or misleading tests across PHPUnit, Vitest, and Playwright.
- Phase 54 owns a full browser-level application review through E2E coverage. Every shipped shell, major Admin area, operational workflow, localization surface, theme surface, permission/module gate, export/import/file/search/notification workflow, and critical error/empty/loading state must be exercised either by Playwright or by a documented lower-level test with a clear rationale.
- Existing tests must be evaluated for product value, not only pass/fail status. Tests that only prove that an implementation detail exists must be strengthened, replaced, or documented as structural guardrails.
- Rendered UI behavior must be verified where backend tests cannot prove the user experience. This includes visible copy, language switching, toast/notification behavior, table interactions, dialogs, destructive confirmations, empty states, dark/light theme rendering, browser console cleanliness, and asset/API request cleanliness.
- For localization, Phase 54 must prove that Polish and English are complete in rendered UI, backend-provided props, validation messages, flash/toast messages, notification text, breadcrumbs, forms, tables, and operational helper copy. It must include negative assertions against accidental English user-facing copy in Polish mode except for allowed technical diagnostic values.
- For messaging, Phase 54 must prove ownership and noise limits for user feedback. Workflows such as exports, imports, retries, scans, rebuilds, managed processes, and integrations must not create duplicate flashes, toast storms, or competing terminal notifications.
- The E2E suite must be treated as an application walkthrough, not just a smoke test. It should cover the real login path, active-team selection, Admin mode, navigation, permissions, module activation, core operational screens, and representative successful/failing workflows.
- The review must produce either implemented test hardening in this phase or explicit follow-up phases for any remaining gaps that are too large to close safely before final release.
- Cross-check every accepted decision against `AGENTS.md`, this file, documentation, ADRs, and tests.
- No accepted behavior may exist only in historical chat.
- Verify module activation, dependency blocking, ineffective permissions, role template behavior, admin mode, impersonation, manager hierarchy, TimeTracking isolation, Chat, reports, exports, imports, files, search, notifications, managed processes, light/dark themes, translations, backup/restore, deploy/rollback, liveness/readiness, and security controls.
- Reverify the repaired Teams and Authorization foundation: Team Edit read-only authorization, User Edit role-derived permission behavior, current Source semantics, the Employee / Manager / Head Manager Team Structure role model, Head Manager whole-Team scope, multi-manager relationships, Team Structure drag and drop plus its mobile/keyboard alternative, visible mutation errors, flash-message delivery and non-duplication, and the unresolved-interpolation guard.
- Phase 31 communication verification must preserve the complete messaging audit and additionally cover the shared Core Calendar, private personal events, Team-timezone-pinned recurrence, Month/Week/Day/Agenda views, Free/Busy privacy, direct/group/Team Calls, device setup/preferences, Atlas-authorized LiveKit access, Calls that cannot be recorded, one active RTC session per user, Meetings, invitations/RSVP, recurring Meetings, persistent Meeting chat, moderation, lock/kick semantics, attendance, screen sharing, empty-room cleanup, minimize/rejoin, Meeting-only recording, pause/resume/finalization, Files-owned recordings, controlled recording sharing, separate recording retention, provider-disabled transcription UI, the provider-neutral queued transcription boundary, transcript versioning/sharing/Search, and the Admin privacy boundary.
- Final temporal verification must prove that each Team has a canonical IANA timezone; `APP_TIMEZONE` is fallback only where no Team/business context exists; no per-user timezone selection exists; scheduled recurrence persists its resolved wall-clock timezone; switching active Team or changing Team timezone does not reinterpret existing recurrence or rewrite historical instants; date-only values remain dates; real instants remain `timestamptz`; and representative supported Team timezones behave correctly through DST and browser rendering. `Europe/Warsaw` may remain one supported Polish example, not the universal timezone.
- Diagnostics verification must cover automatic backend and frontend Technical Issues; queue, scheduler, and Managed Process failure capture; expected-error exclusions; fingerprinting and deduplication; exact aggregate counting under flood sampling; regression reopen; automatic severity and manual override; User Bug Reports; Files-owned screenshots and attachments; My Reports; Admin Errors & Reports; issue/report linking and Primary relation; issue merge aliases; sanitized text/JSON diagnostic copy; related-log correlation; sanitizer and privacy boundaries; private source maps; Technical Issue occurrence and User Bug Report retention; Health integration with liveness/readiness independence; Critical/regression Notifications and cooldown; backup/restore of Diagnostics and User Report Files; exact-release source-map association; removal of every Atlas Sentry package/integration/configuration/environment/runtime/browser/deployment hook; absence of a replacement external monitor; and continued generic secret redaction.
- Phase 34 verification must prove the finite extension/duplication audit closed, froze the first-release backlog, assigned every actionable finding, and aligned module public boundaries with canonical documentation.
- Phase 35 verification must prove module-owned routes, breadcrumbs, and navigation contributions across App/User, Manager, and Admin surfaces; natural URLs; global route uniqueness; ModuleGate behavior; permission-driven authorization without route-surface role checks; private dynamic breadcrumbs; and removal of central god-route/god-breadcrumb ownership.
- Phase 36 verification must prove Settings/environment classification; no Admin `.env` editing; declared safe runtime Settings; write-only secrets; required Polish/English labels in future-locale-compatible storage; immutable technical codes; non-editable system values; global/Team Reference Dictionaries with no hard delete and historical rendering; localized custom roles; Team timezones; named Business Calendars; Team defaults; and business-day calculations.
- Phase 37 verification must prove justified opt-in locking with canonical conflict UX and no silent overwrite; Change Reason propagation; half-open/open-ended date and instant ranges including DST; opt-in provenance distinct from Audit; privacy-safe cross-module references without persistence leakage or arbitrary field access; and atomic immutable global/Team sequence issuance without manual counter reset or universal gapless promises.
- Phase 38 verification must prove in Chromium and Firefox where relevant: pre-existing User requirement, no JIT creation, verified first linking, persistent issuer/subject identity, no unsafe email relinking, one active provider, Entra/Keycloak/generic OIDC, both login modes, emergency local Admin, correct MFA ownership, fresh OIDC high-risk reauthentication, Admin-only unlink/relink, outage/disabled-account behavior, write-only client secret, safe Audit, and no accidental SAML/SCIM baseline.
- Phase 39 verification must prove the Integration Event catalog and compatibility, Outbox atomicity/idempotency, module-owned `/api/v1`, OpenAPI and API conventions, Service Accounts distinct from Users with no Team/browser context and only `api.*` permissions, code-owned permissions, multiple expiring tokens without scopes or expiry extension, replacement rotation and one-time plaintext, revocation/last-use, owner-module business scope, and signed Admin-managed retryable/replayable webhooks without public self-registration or a rules engine.
- Phase 51 verification must prove docs match code, architecture guards reject forbidden dependencies, `Creating an Atlas module` is complete, the generator supports Application and Optional but not normal Core generation, collisions/partial output are safe, generated output follows schema/provider/routes/public-contract conventions, no speculative CRUD is generated, and generated fixtures pass relevant checks.
- Deployment verification must cover Atlas-managed self-hosted LiveKit, trusted-network RTC/TURN connectivity, separate Egress readiness/failure isolation, protected recording staging, compatible pinned runtime versions, and backup/restore of Files-owned recordings and PostgreSQL-owned transcript state without exposing private Meeting content in operational surfaces.
- Review starter cloning and namespace/application identity replacement.
- Tag a stable release only after complete verification.
- `PRODUCTION_DEPLOYED=true` is set only in a Atlas after its first actual production deployment, not merely when the Atlas is released.

- The final repository context uses `AGENTS.md`, `WORKROAD.md`, the project-owner `CHATGPT_PROMPT.md`, and the canonical linked documentation under `docs/`.
- Working-only files such as temporary discussion notes, continuation prompts, and review drafts are not part of the final package.
- Before final delivery, ensure every accepted rule, implementation contract, task, module description, architectural decision, and operational procedure exists in its canonical root or `docs/` location.
- A fresh session must be able to resume by reading the root entry files and only the relevant linked documentation.

### Product neutralization

Verify:

- current root metadata is product-neutral;
- current canonical docs describe Atlas as a modular business application foundation/platform;
- no hidden debt-collection-specific dependency remains in Core/Optional/shared foundation;
- remaining debt/collection terminology is intentionally historical or Application-owned;
- completed historical phase files and Phase 31 were not rewritten.

### Resource collaboration

Verify:

- Comments visibility exactly follows source-resource access;
- mention cannot grant source access;
- Comments edit history;
- Files attachments;
- global and Team Tags;
- tag assignment authorization;
- tags do not execute workflow;
- Custom Fields resource-type opt-in;
- no Team-specific Custom Field schema forks;
- all supported field types;
- validation;
- Search/export behavior;
- no loose cross-module polymorphic models.

### Work Schedule

Verify:

- schedule is User + Team;
- same User may have different schedules in different Teams;
- overnight shifts;
- multiple intervals;
- overrides;
- Business Calendar;
- Team timezone;
- DST;
- expected duration queries;
- no HR/payroll/leave ownership.

### Work Management

Verify:

- User assignment;
- Team assignment;
- unassigned Team queue;
- concurrency-safe claim;
- reassignment;
- My Work;
- manager views;
- typed source references;
- typed action targets;
- no arbitrary canonical URL integration;
- Calendar contribution;
- Notifications;
- Files;
- optional Comments;
- optional Search;
- optional Work Schedule reduced mode;
- no required estimated duration;
- no productivity target/score.

### TimeTracking evolution

Verify:

- historical Phase 27 remains historical;
- actual Task duration across interrupted segments;
- Other-work attribution corrections;
- expected schedule time;
- actual accepted time;
- neutral signed delta;
- automatic normal acceptance;
- no daily manager approval;
- manager corrections are exception-based and audited;
- no estimate-vs-actual productivity scoring;
- no HR/payroll.

### DocumentGeneration

Verify:

- Admin-managed templates;
- code-owned template/context definitions;
- immutable used versions;
- DOCX;
- PDF;
- email subject;
- text/HTML email body;
- merge fields;
- tables;
- lists;
- images;
- company identity;
- preview;
- queued/batch generation;
- Files-owned artifacts;
- numbering-before-final-render where required;
- no duplicate number on retry;
- authorization/privacy/retention.

### OCR

Verify:

- OCR runs only on explicit request;
- Files owns source;
- real built-in Tesseract provider;
- configured external providers;
- provider-normalized text/pages/language/confidence/evidence;
- confidence is not fabricated;
- queues/retries;
- Search source authorization;
- source-linked retention;
- module activation/provider failure;
- Tesseract runtime parity.

### DocumentExtraction

Verify:

- Atlas does not provide a universal extractor;
- regex/custom/local/AI/external adapters can implement the same contract;
- profiles/schemas are code-owned;
- provider mapping is configurable;
- File input;
- optional OCR input;
- OCR is not universally required;
- automatic-after-validation;
- confidence-gated;
- always-human-review;
- per-field/critical thresholds;
- missing required confidence routes to review;
- Work Management review Tasks;
- accept/correct/reject;
- original provider result remains preserved;
- full human correction Audit;
- explicit business result consumer;
- no direct AI/provider foreign-table writes.

### DuplicateDetection

Verify:

- profile-specific criteria;
- pluggable providers/strategies;
- normalization/fingerprints;
- score/evidence;
- configurable exact-match/threshold policy;
- warn/block/automatic/review outcomes;
- no global threshold;
- human review through Work Management;
- provider failure is explicit;
- no direct cross-domain merge/delete.

### Contact/address primitives

Verify:

- EmailAddress;
- PhoneNumber;
- StructuredAddress;
- international address shape;
- normalization/formatting/structural validation;
- no global Person/Customer/Company;
- optional Address Validation provider;
- entire system works with no provider;
- provider may be external service or central company address database;
- provider does not become owner of business contact data.

### Minimal Approvals

Verify:

- one approver;
- multiple approvers;
- `all`;
- `any`;
- deterministic rejection semantics;
- immutable decisions/history;
- cancellation;
- Notifications;
- Audit;
- business module owns consequences;
- no BPMN/workflow engine/stages/weighted voting.

### Business Correspondence

Verify:

- distinct from Notifications;
- email and postal/provider paths;
- DocumentGeneration templates;
- email subject/body templates;
- Files attachments;
- immutable Atlas correspondence number;
- separate provider/tracking references;
- number available before render when included in content;
- no duplicate number on retry;
- provider-neutral submission;
- polling;
- callbacks/webhooks;
- signature/idempotency/replay safety;
- canonical status mapping;
- unknown provider status handling;
- uncertain-submit behavior;
- retries;
- Search;
- Audit;
- retention;
- provider Health;
- contact ownership remains outside Correspondence.

### Business Activity Timeline

Verify:

- explicit module contributions;
- no Audit scraping;
- immutable normal-user history;
- Comments remain the editable human-content mechanism;
- source-resource authorization;
- idempotency under at-least-once delivery;
- stable occurrence ordering;
- PL/EN rendering from structured keys/parameters;
- privacy/anonymization/deletion handling;
- producer modules work while Timeline is disabled;
- Timeline is not event sourcing or a global event store.

### Module activation/dependencies

For every new Optional module verify:

- required dependencies are declared and enforced;
- activation is blocked when a required active dependency is absent;
- deactivation is blocked when active dependents require the module;
- optional dependencies have safe tested reduced modes;
- configuration/profile paths that require an optional dependency cannot become operational when that dependency is inactive;
- ModuleGate removes inactive UI/actions;
- architecture metadata matches real imports/config/provider registrations.

### Provider-neutral extensions

Verify provider/fake/runtime contracts for:

- Address Validation;
- OCR;
- DocumentExtraction;
- DuplicateDetection;
- Correspondence delivery/tracking.

Verify:

- secrets;
- timeouts;
- malformed responses;
- retry/idempotency;
- Health;
- privacy;
- deterministic test adapters.

## Tasks

- [ ] Verify Product Neutralization, current product-neutral metadata/documentation, foundation boundaries, and preservation of historical phase files.
- [ ] Verify resource collaboration across Comments, Tags, Custom Fields, typed references, authorization, Files, Search, exports, history, and the prohibition on loose polymorphism.
- [ ] Verify Work Schedule User + Team ownership, intervals, overnight work, overrides, calendars, timezones, DST, expected-duration queries, and HR/payroll boundaries.
- [ ] Verify Work Management assignment, Team queues, concurrency-safe claiming, typed source/action targets, integrations, reduced modes, and absence of duration/productivity targets.
- [ ] Verify TimeTracking Task attribution, interrupted segments, expected/actual/delta semantics, automatic acceptance, exception corrections, and scoring/payroll boundaries.
- [ ] Verify DocumentGeneration templates, immutable versions, DOCX/PDF/email rendering, preview, batching, Files ownership, numbering, retries, authorization, privacy, and retention.
- [ ] Verify explicit-request OCR, real Tesseract and external providers, normalized results, queues, Search authorization, retention, activation, failures, and runtime parity.
- [ ] Verify DocumentExtraction profiles/providers/input modes/policies/review/history/business handoff and the absence of direct provider writes to foreign persistence.
- [ ] Verify DuplicateDetection profile-specific providers, evidence, policies, Work Management review, explicit failures, and owner-controlled business handling.
- [ ] Verify contact/address primitives and optional Address Validation with international data, provider-free operation, privacy, and business-owned contact data.
- [ ] Verify minimal Approvals for one/many approvers, `all`/`any`, immutable history, cancellation, Notifications, Audit, owner consequences, and workflow-engine boundaries.
- [ ] Verify Business Correspondence channels, templates, Files, numbering, provider references, polling/callback security, retries, Search, Audit, retention, and Health.
- [ ] Verify Business Activity Timeline explicit contributions, source authorization, localization, idempotency, ordering, privacy, inactive reduced mode, and Audit/event-store boundaries.
- [ ] Verify required and optional dependencies, activation/deactivation guards, configuration-dependent availability, ModuleGate behavior, and metadata/import consistency for every new Optional module.
- [ ] Verify provider-neutral extension secrets, timeouts, malformed responses, retry/idempotency, Health, privacy, deterministic fakes, and required real-runtime smoke coverage.

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
- [ ] Verify shared Core Calendar Month/Week/Day/Agenda views, private personal events, Team-timezone-pinned recurrence, and privacy-preserving Free/Busy.
- [ ] Verify Team IANA timezone, fallback-only `APP_TIMEZONE`, no per-user timezone, pinned recurrence across active-Team and Team-timezone changes, `date`/`timestamptz` semantics, and representative DST/browser behavior.
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
- [ ] Verify no Atlas Sentry package/integration/configuration/environment/runtime/browser/source-map/release/deployment hook remains after Phase 33, no replacement external monitor exists, and generic shared secret redaction works.
- [ ] Verify Phase 34 audit closure, backlog freeze, assigned findings, and documented module public boundaries.
- [ ] Verify Phase 35 module-owned routes/navigation/breadcrumbs, surface semantics, natural URLs, uniqueness, ModuleGate, privacy, and legacy central-file cleanup.
- [ ] Verify Phase 36 Settings/env ownership, write-only secrets, localized values/dictionaries, immutable codes, soft-delete history, Team timezones, and named Business Calendars.
- [ ] Verify Phase 37 locking/conflicts, Change Reason, effective ranges, provenance, safe references, and concurrent global/Team sequences.
- [ ] Verify Phase 38 OIDC linking, provider matrix, modes, recovery, authentication-method separation, secrets, Audit, and failure behavior in the required browsers.
- [ ] Verify Phase 39 Integration Events/Outbox, `/api/v1`, OpenAPI, Service Accounts, API-only permissions, mandatory token expiry/replacement, idempotency, and signed webhooks.
- [ ] Verify Phase 51 extension documentation, architecture guards, minimal Application/Optional generator, collision safety, and generated-module checks.
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
- [ ] Product-neutral identity and architecture are verified.
- [ ] Every Optional capability introduced through Phase 50 is covered by activation/dependency, authorization, privacy, backend, and rendered-browser verification appropriate to its surface.
- [ ] Provider-neutral integrations have deterministic fake coverage plus required real-runtime/provider-boundary smoke coverage.
- [ ] No new capability depends on chat history for its accepted contract.
- [ ] Atlas is ready to serve as a stable product-neutral modular business application foundation for future Application-domain development.
