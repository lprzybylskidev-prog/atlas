# Phase 32 — Error reporting, user bug reports, and application diagnostics

**Status:** `not started`

## Objective

Add a first-party Atlas diagnostics and bug-reporting capability that makes application failures and incorrect product behavior understandable without replacing the existing structured logging, health, Pulse, or Telescope foundations.

Phase 32 provides two complementary inputs:

1. automatic Technical Issues created from genuine application/runtime failures;
2. manual User Bug Reports created by employees when Atlas behaves incorrectly even though no technical exception necessarily occurred.

Examples of the second category include:

- expected data missing from a table;
- an incorrect workflow result;
- unexpected UI behavior;
- business behavior that returned a technically successful HTTP response but is functionally wrong.

The system must make it possible for an authorized Administrator to answer:

- what failed;
- where it failed;
- when it failed;
- how often it failed;
- who was affected;
- which release was affected;
- which route/module was involved;
- what safe actions occurred immediately before the failure;
- which logs/correlation IDs relate to it;
- whether users independently reported the same behavior;
- whether a previously resolved issue returned.

This is not a replacement for logs.

Structured logs remain the fundamental diagnostic fallback when normal application persistence is unavailable.

This phase must not build a Sentry clone, Jira clone, APM platform, distributed tracing product, profiler, or session-replay engine.

Do not add Sentry or another external error-monitoring dependency. Phase 32 explicitly replaces and removes Atlas's existing Sentry integration; first-party Diagnostics must not be built on top of the dependency it supersedes.

Atlas uses its own diagnostics capability.

Existing Pulse and Telescope remain separate technical tools and must not be replaced or duplicated.

---

## Dependencies

Phase 32 depends on the completed shared Atlas foundations including:

- Authorization;
- Audit;
- Files and ClamAV;
- Notifications;
- Settings;
- Sessions;
- Admin Mode and impersonation;
- Feature Flags;
- structured logging;
- request/correlation IDs;
- Admin operations and System Status;
- health/readiness/liveness;
- queues and scheduler;
- Managed Processes;
- frontend shared shell/UI;
- localization;
- release/build metadata;
- existing error and network handling.

Phase 32 is implemented only after Phase 31 is actually complete.

Planning Phase 32 while Phase 31 remains in progress does not authorize starting Phase 32 implementation.

---

## Fundamental boundary

Diagnostics is a shared Core operational capability.

It is not an optional business module.

Do not create a second logging subsystem.

Canonical responsibilities are:

```text
structured logs
    =
durable low-level diagnostic fallback

Diagnostics
    =
curated issues, occurrences, impact, user reports,
safe context, relationships, lifecycle, and health signal
```

Technical Issue persistence must remain useful even when many identical occurrences happen.

Manual User Bug Reports remain semantically distinct from automatically detected Technical Issues.

Do not automatically convert every User Bug Report into a Technical Issue.

Do not automatically merge User Bug Reports based only on similar text.

---

## Execution discipline

Workstreams are strictly sequential:

1. `P32-W01` — Core boundary, persistence, permissions, privacy contracts
2. `P32-W02` — Automatic capture, correlation, safe context, breadcrumbs, and source mapping
3. `P32-W03` — Fingerprinting, deduplication, occurrences, severity, regression, and flood control
4. `P32-W04` — User Bug Report workflow, screenshots, Files, and My Reports
5. `P32-W05` — Admin Errors & Reports UI, linking, merging, investigation workflow, and diagnostics bundle
6. `P32-W06` — Health/System Status integration and diagnostic Notifications
7. `P32-W07` — Retention, sampling configuration, purge, and lifecycle cleanup
8. `P32-W08` — Audit, operational failure safety, private source diagnostics, and development fixtures
9. `P32-W09` — Browser acceptance, backend coverage, documentation, and final closure

Only the earliest incomplete workstream is active.

Do not select later tasks because they are easier.

Do not split unfinished work into endless suffix packages merely to report progress.

A workstream is complete only when its implementation, tests, documentation, cleanup, browser acceptance where required, and permanent guardrails are complete.

Do not calculate completion percentages or readiness percentages.

---

## P32-W01 — Core boundary, persistence, permissions, privacy contracts

### Existing Sentry transition and removal

Before building the first-party capture path, audit the actual repository for all Sentry-specific runtime and development wiring and remove every application-owned integration still present when Phase 32 begins. This includes:

- the direct `sentry/sentry-laravel` dependency and normal lockfile updates;
- `config/sentry.php` and other Sentry-specific configuration;
- DSN and runtime/environment/example configuration;
- service-provider, bootstrap, backend, browser, and capture hooks;
- source-map upload, release, and deployment hooks;
- Sentry-specific tests and documentation, which must be removed or rewritten to the first-party Diagnostics contract;
- obsolete Sentry-specific sanitizer integration.

Preserve and harden any generic shared secret-redaction capability independently required by Atlas. Do not replace Sentry with another external error-monitoring system. The result must leave first-party Diagnostics as the owner of curated issue behavior while structured logs remain the durable fallback.

### Core domain

Create canonical persistence for:

- Technical Issues;
- detailed Technical Issue occurrences/samples;
- issue fingerprints;
- issue aggregate counters;
- affected-user aggregates;
- release history;
- route/module/source aggregates;
- issue lifecycle history;
- issue merge aliases;
- User Bug Reports;
- Technical Issue ↔ User Bug Report links;
- one optional primary Technical Issue relation for a User Bug Report;
- safe diagnostic context;
- diagnostics configuration required by this phase.

Follow existing PostgreSQL schema/module ownership conventions.

Do not put Diagnostics tables into an unrelated business-module schema.

### Technical Issue statuses

Use exactly:

- `Open`;
- `Investigating`;
- `Resolved`;
- `Ignored`.

A Technical Issue may be manually reopened.

`Ignored` means the issue is intentionally not being acted on at present.

It does not mean occurrences stop being counted.

### User Bug Report statuses

Use the same canonical lifecycle:

- `Open`;
- `Investigating`;
- `Resolved`;
- `Ignored`.

For the reporting user's UI, `Ignored` should use a friendlier localized presentation equivalent to:

`Closed without changes`

rather than exposing an unnecessarily hostile label.

### Severity

Support:

- `Info`;
- `Warning`;
- `Error`;
- `Critical`.

Technical severity is normally computed automatically from safe factors such as:

- failure type;
- interactive vs background source;
- occurrence rate;
- affected users;
- affected route/module;
- critical application path;
- regression state.

An authorized Administrator may override severity manually.

A manual severity override remains authoritative until explicitly removed.

Automatic evaluation must not silently overwrite an active manual override.

### Permissions

Every user-triggered protected action/use case must use canonical Atlas Authorization permissions.

Do not authorize Diagnostics by role-name checks.

Add granular capabilities according to existing permission naming conventions for at least:

- create User Bug Report;
- view own User Bug Reports;
- view Technical Issues;
- view Admin User Bug Reports;
- view Technical Issue detail;
- copy sanitized diagnostics;
- change Technical Issue status;
- reopen Technical Issue;
- change/override Technical Issue severity;
- remove severity override;
- update internal diagnostic note;
- merge Technical Issues;
- link/unlink User Bug Report and Technical Issue;
- select a primary Technical Issue link;
- change User Bug Report status;
- delete User Bug Report;
- manage diagnostics retention;
- run diagnostic occurrence purge;
- manage sampling/flood configuration;
- manage Diagnostics health thresholds.

Use the existing Admin-area authorization boundary in addition to appropriate permissions for Admin-only diagnostics surfaces.

Ordinary Managers do not gain access because of manager hierarchy.

Team membership does not grant Diagnostics access.

An ordinary user may see only their own User Bug Reports through the accepted own-report workflow.

Automatic internal error capture is a system operation and must not depend on an end-user `diagnostics.report` permission.

Starter user permissions should normally allow:

- reporting a bug;
- viewing one's own reports.

Admin diagnostic capabilities follow the existing administrative permission model.

### Privacy boundary

Diagnostics may contain sensitive operational context.

Create one canonical shared Diagnostics sanitization/redaction contract.

It must be used consistently by:

- backend capture;
- frontend capture;
- queue/scheduler capture;
- manual-report automatic context;
- diagnostics bundle generation.

Never persist diagnostic secrets merely because they existed in a request/runtime object.

At minimum redact:

- passwords;
- cookies;
- `Authorization`;
- session/CSRF tokens;
- API keys;
- SMTP credentials;
- integration secrets;
- bearer tokens;
- private signing keys;
- Chat message bodies;
- Meeting transcript contents from unrelated contexts;
- recording/media contents;
- raw Files contents;
- arbitrary form field values unless explicitly reviewed and whitelisted.

Also apply sensitive-key matching so keys containing patterns such as:

- `password`;
- `secret`;
- `token`;
- `authorization`;
- credential-like equivalents

are treated as sensitive by default.

Explicit User Bug Report description/expected/actual fields are intentional report content and are not treated as accidental captured request payload.

### Tasks

- [ ] Audit the repository for all Sentry package, configuration, environment, bootstrap, runtime, browser, source-map, release, deployment, test, documentation, and sanitizer wiring.
- [ ] Remove `sentry/sentry-laravel` if still present and update Composer lockfiles through the normal package workflow.
- [ ] Remove Sentry configuration, DSN/environment examples, providers/bootstrap hooks, capture hooks, and source-map/release/deployment integration.
- [ ] Remove or rewrite Sentry-specific tests and documentation to the first-party Diagnostics contract.
- [ ] Preserve generic shared secret redaction independently required by Atlas and add regression coverage.
- [ ] Verify first-party Diagnostics is not implemented on top of Sentry or another external monitor.
- [ ] Create the Core Diagnostics ownership boundary.
- [ ] Define Technical Issue persistence.
- [ ] Define occurrence and aggregate persistence.
- [ ] Define User Bug Report persistence.
- [ ] Define issue/report relation persistence.
- [ ] Define canonical lifecycle history.
- [ ] Define merge-alias persistence.
- [ ] Add Open / Investigating / Resolved / Ignored statuses.
- [ ] Add Info / Warning / Error / Critical severity.
- [ ] Add manual severity override semantics.
- [ ] Add granular canonical permissions for every protected use case.
- [ ] Prevent Manager/Team-derived diagnostics access.
- [ ] Add one shared sanitization/redaction contract.
- [ ] Add sensitive-key redaction patterns.
- [ ] Add architecture/privacy guardrails.
- [ ] Add domain and permission tests.

---

## P32-W02 — Automatic capture, correlation, safe context, breadcrumbs, and source mapping

### Automatic Technical Issue sources

Automatically capture genuine unexpected technical failures from at least:

- unhandled Laravel/backend exceptions;
- application-originated HTTP `5xx` failures;
- Vue global error handling;
- `window.onerror`;
- unhandled Promise rejections;
- frontend asset/chunk-load failures;
- failed queue jobs caused by unexpected technical failure;
- scheduler failures caused by unexpected technical failure;
- Managed Process unexpected technical failures.

Avoid creating two occurrences for the same underlying backend exception merely because it was observed both as an exception and its resulting HTTP 500.

Use correlation/idempotency to recognize the same failure path.

### Expected behavior that must not create Technical Issues

Do not automatically create Technical Issues merely for normal:

- `404`;
- `403`;
- `422`;
- validation rejection;
- expected domain rejection;
- ordinary authorization denial;
- user cancellation;
- browser offline state;
- temporary client Wi-Fi loss.

Normal Health signals from dependencies such as:

- PostgreSQL;
- Redis;
- Meilisearch;
- ClamAV;
- Reverb;
- LiveKit;
- Egress

remain owned by the existing Health/System Status foundation.

Do not manufacture a Technical Issue solely because a normal health check reports a dependency unavailable.

If a dependency failure causes a genuine unexpected application exception or failed operation, that application failure may be captured normally.

### Correlation

Treat correlation/request identity as a first-class Diagnostics contract.

Where applicable preserve the chain:

```text
browser action
→ frontend request
→ Laravel request
→ logs
→ Technical Issue occurrence
→ possible User Bug Report relation
```

Use existing Atlas correlation/request-ID infrastructure rather than creating a competing identifier system.

### Safe diagnostic context

Capture useful safe context where available:

- authenticated user identity;
- active Team;
- current module;
- release/version;
- Git commit/release identity;
- route;
- normalized URL;
- HTTP method;
- response status;
- timestamp;
- locale;
- theme;
- browser;
- browser version where safely detectable;
- OS/platform/user agent;
- viewport size;
- Admin Mode state;
- impersonation state;
- relevant evaluated Feature Flags;
- required permission/action and authorization outcome where diagnostically useful;
- safe request timing;
- correlation/request identifiers.

Do not persist a user's full permission catalog merely for diagnostics.

### Session reference

Do not store:

- raw session cookie;
- raw session ID.

Where correlation across events in the same authenticated session is useful, store only a safe non-reversible/internal session reference such as a keyed hash/HMAC derived server-side.

Never expose the raw session identifier to Admin UI.

### Request data

Do not persist raw request or response bodies by default.

Store only reviewed metadata such as:

- route template;
- normalized URL;
- method;
- status;
- timing;
- correlation ID;
- whitelisted safe route parameters.

Query strings must not be blindly stored.

Store only approved keys/values when they are safe and diagnostically useful.

For example, a search query containing a person's name must not be captured merely because it appeared in the URL.

### Breadcrumbs-lite

Implement bounded breadcrumbs-lite for recent safe user/application actions.

Useful breadcrumb types include:

- route navigation;
- safe action/button identifier;
- modal opened;
- request method + normalized route + status;
- relevant system action.

Do not include:

- text field values;
- message bodies;
- transcript contents;
- form payloads;
- request bodies;
- response bodies;
- secret values.

Frontend breadcrumbs should remain in a short-lived bounded client-side buffer during normal browsing.

Do not continuously stream every user action to PostgreSQL.

Send the bounded recent breadcrumbs only when:

- an automatic frontend Technical Issue is captured;
- a manual User Bug Report is submitted.

Keep the buffer small and bounded, approximately the last 20–30 useful entries.

### Frontend source maps

Production frontend errors must be diagnosable beyond minified bundle positions.

Support private source-map based stack resolution for the deployed Atlas release.

Source maps:

- must not be publicly served to normal browser users;
- must not become a public reverse-proxy asset;
- may be retained as private release/build diagnostic artifacts;
- must be associated with the exact release/build.

Resolve useful original source file/line information where practical.

### Capture ordering

Automatic capture should happen best-effort before the application discards the relevant error context.

For frontend errors, attempt safe capture before final fallback/error presentation.

For backend errors, capture must integrate with the canonical exception/failure path without masking the original exception.

### No user-visible error reference requirement

Do not add an `ERR-ABC123` style reference code merely for this feature.

A genuine application error is captured automatically.

The user does not need to copy a diagnostics identifier.

Manual User Bug Reports remain available globally if the user wants to describe incorrect behavior.

### Failure-safe capture

Diagnostics capture must never recursively capture its own failure forever.

If Diagnostics persistence fails:

- preserve the original structured-log failure path;
- fail safely;
- do not hide the original application error;
- do not recurse through the Diagnostics capture pipeline.

### Tasks

- [ ] Capture backend unhandled exceptions.
- [ ] Capture application technical 5xx failures without double-reporting.
- [ ] Capture Vue/global frontend errors.
- [ ] Capture unhandled Promise rejections.
- [ ] Capture asset/chunk-load failures.
- [ ] Capture unexpected queue failures.
- [ ] Capture unexpected scheduler failures.
- [ ] Capture unexpected Managed Process failures.
- [ ] Exclude expected 404/403/422/domain behavior.
- [ ] Exclude ordinary offline/network loss.
- [ ] Reuse correlation/request IDs.
- [ ] Add safe runtime/user/release context.
- [ ] Add safe hashed session reference.
- [ ] Prevent raw request/response body capture.
- [ ] Add query/route-parameter whitelist behavior.
- [ ] Add bounded breadcrumbs-lite.
- [ ] Keep normal breadcrumb collection client-side only.
- [ ] Attach breadcrumbs to frontend errors and User Bug Reports.
- [ ] Add private release source-map diagnostics.
- [ ] Keep source maps unavailable as public assets.
- [ ] Add recursive-failure protection.
- [ ] Preserve structured logs as fallback.
- [ ] Add sanitization and negative leakage tests.

---

## P32-W03 — Fingerprinting, deduplication, occurrences, severity, regression, and flood control

### Technical Issue fingerprinting

One Technical Issue represents one normalized technical problem.

Many occurrences belong to the same Technical Issue.

Fingerprinting must consider stable diagnostic identity such as:

- exception/error class;
- normalized error message;
- useful stable stack/root frame information;
- normalized route/action;
- source category where needed.

Do not fingerprint on volatile values such as:

- concrete user ID;
- UUID;
- timestamp;
- exact dynamic URL ID;
- request ID.

For example:

```text
/users/123
/users/456
```

should normally map to the same route template when they represent the same failure.

### Cross-release identity

The same fingerprint across releases remains the same canonical Technical Issue.

Preserve per-release occurrence history.

Do not create a completely new issue merely because Atlas was deployed again.

### Occurrences and aggregates

Always maintain accurate aggregate information including:

- total occurrence count;
- first seen;
- last seen;
- affected-user information;
- affected-user count;
- release history;
- route/module/source distribution;
- recent occurrence-rate windows;
- interactive/background source distinction where useful.

Maintain bounded per-issue/per-user aggregates sufficient to support the accepted `Affected user` filtering without retaining every heavy historical occurrence forever.

### Detailed occurrence samples

Detailed occurrences may contain sanitized:

- stack;
- correlation IDs;
- browser/runtime metadata;
- breadcrumbs;
- normalized request context;
- release;
- user/session references;
- route/module;
- timestamps.

Detailed occurrences are retention-controlled separately from the long-lived Technical Issue aggregate.

### Flood protection

A runaway exception must not create millions of heavy detailed rows.

When occurrence volume exceeds configured sampling/flood thresholds:

- the total occurrence counter must remain accurate;
- rate/time-window aggregates must remain accurate;
- affected-user/release aggregates must continue updating;
- detailed occurrence storage may become sampled/bounded.

Sampling must retain diagnostically useful representative occurrences.

Do not silently lose the fact that the issue happened.

Sampling/flood thresholds are configurable in Admin settings.

Do not build an elaborate event-streaming platform merely for this requirement.

### Regression

If a `Resolved` Technical Issue occurs again:

- reopen it automatically as `Open`;
- mark the return as a regression;
- record when it was previously resolved;
- record the release in which it returned;
- preserve regression history.

A single valid new occurrence is enough to mark the issue as a regression.

Health/severity impact may still consider the number of occurrences separately.

When the issue is resolved again:

- the active Regression marker may clear;
- historical regression entries remain.

### Ignored issues

An `Ignored` issue continues collecting:

- occurrence counts;
- impact;
- affected-user metrics;
- release history.

A sudden significant increase in an ignored issue may:

- produce an impact-growth diagnostic signal;
- produce an authorized Admin notification;
- contribute to Diagnostics health evaluation.

Do not automatically change its lifecycle status from `Ignored`.

### Manual merge

Authorized Admin may merge Technical Issues when automatic fingerprinting split one logical problem.

Merge must:

- select one canonical issue;
- preserve old issue IDs as historical aliases;
- preserve old links/bookmarks where possible;
- combine counters;
- combine affected-user aggregates;
- combine release history;
- combine linked User Bug Reports;
- preserve lifecycle/regression evidence;
- route future known alias fingerprints to the canonical issue.

Merge is audited and requires confirmation.

Do not implement Undo Merge.

Do not implement manual Split.

Do not build an Admin regex/fingerprint-rule editor.

Fingerprinting rules remain canonical application code.

### Issue timeline

Technical Issue detail exposes a compact structural timeline containing meaningful events such as:

- first seen;
- status changed;
- severity changed/override applied;
- resolved;
- reopened;
- regression detected;
- merge;
- important impact-growth event;
- latest occurrence summary.

Do not create a timeline entry for every occurrence.

### Tasks

- [ ] Implement normalized fingerprinting.
- [ ] Normalize dynamic routes/volatile values.
- [ ] Preserve identity across releases.
- [ ] Add issue aggregate counters.
- [ ] Add affected-user aggregates/filter support.
- [ ] Add release/source/route/module aggregates.
- [ ] Add detailed occurrence samples.
- [ ] Add configurable flood sampling.
- [ ] Keep exact aggregate occurrence counts during sampling.
- [ ] Add automatic severity evaluation.
- [ ] Respect manual severity override.
- [ ] Add automatic regression reopen.
- [ ] Preserve regression history.
- [ ] Keep ignored issue impact tracking.
- [ ] Add impact-growth signaling.
- [ ] Add manual issue merge.
- [ ] Preserve canonical aliases after merge.
- [ ] Prevent Undo Merge and manual Split.
- [ ] Add structural issue timeline.
- [ ] Add concurrency/idempotency tests for occurrence aggregation.

---

## P32-W04 — User Bug Report workflow, screenshots, Files, and My Reports

### Global Report Bug action

Every active user with the reporting permission sees a global:

`Report bug`

action in the top application shell near existing controls such as:

- language;
- theme.

Use appropriate Polish localization:

`Zgłoś błąd`

Do not hide this action deep inside Admin.

### Report modal

Open an Atlas modal containing:

- `What is wrong?` — required;
- `What should happen?` — optional;
- `What actually happened?` — optional;
- attachments/screenshots — optional.

Keep the workflow simple.

Do not add:

- comments;
- ticket assignment;
- sprint;
- priority planning;
- due dates;
- task-management fields.

### Attachments

Allow multiple attachments.

Support canonical Files UX:

- picker;
- drag and drop;
- clipboard paste.

All User Bug Report attachments use the existing Files public contracts.

They must follow:

- validation;
- private storage;
- ClamAV;
- authorization;
- cleanup.

Do not create a Diagnostics-specific file storage subsystem.

### Capture current Atlas view

Provide an explicit action to capture the current Atlas application viewport when safely supported.

Requirements:

- capture only the Atlas application view;
- do not capture the user's entire desktop;
- do not capture unrelated applications;
- temporarily exclude/hide the Report Bug modal itself from the captured image where practical;
- show preview;
- require user confirmation before attaching the captured image.

Do not automatically capture screenshots in the background.

Do not automatically capture on every error.

If safe current-Atlas-view capture is unsupported or unavailable in the current browser:

- do not fail the report;
- keep upload;
- keep drag/drop;
- keep clipboard paste.

Do not add a large/heavy screenshot subsystem solely to guarantee capture in every browser.

### Automatic report context

A manual User Bug Report automatically includes the safe Diagnostics context defined in P32-W02, including where available:

- page/route;
- timestamp;
- user;
- active Team;
- module;
- release/commit;
- locale;
- theme;
- browser/platform;
- viewport;
- safe session reference;
- Admin Mode/impersonation state;
- relevant Feature Flags;
- safe breadcrumbs.

The employee cannot disable this safe internal diagnostic context.

Do not add an unnecessary consent/disclosure banner in the internal company UI for this specific form.

The sanitizer remains mandatory regardless.

### Idempotency

Report submission must be idempotent against:

- double-click;
- frontend retry;
- network retry.

Do not introduce a new special configurable Diagnostics report-rate-limit subsystem.

Use the project's normal authenticated request protections and canonical idempotency where applicable.

### My Reports

Provide a normal user-facing:

`My reports`

surface accessible from the user/profile area.

It shows only that user's reports.

Expose at least:

- submitted date/time;
- short description;
- current status.

Do not expose:

- Technical Issue stack traces;
- severity;
- internal Admin note;
- related logs;
- private issue metadata;
- other users' reports.

The reporting employee cannot:

- edit the report after submission;
- add comments;
- add attachments later;
- delete the report.

If a report is closed/resolved but the problem still exists, the employee creates a new report.

### Status notification

When the User Bug Report status changes, send the appropriate existing typed Atlas Notification to the reporting user.

Reuse existing Notifications channel/preferences behavior, including email where the user has configured that existing channel.

Do not build a second email-preference subsystem.

### Tasks

- [ ] Add global Report bug action near language/theme controls.
- [ ] Gate Report bug by canonical permission.
- [ ] Add Report Bug modal.
- [ ] Add required/optional report fields.
- [ ] Add multiple Files-owned attachments.
- [ ] Add picker.
- [ ] Add drag/drop.
- [ ] Add clipboard paste.
- [ ] Add explicit current-Atlas-view screenshot capture.
- [ ] Exclude Report modal from screenshot where practical.
- [ ] Add screenshot preview/confirmation.
- [ ] Add safe capture fallback behavior.
- [ ] Attach safe context and breadcrumbs.
- [ ] Add idempotent report submit.
- [ ] Add My Reports in user/profile area.
- [ ] Keep reports immutable from reporting-user side.
- [ ] Add status-change Notification.
- [ ] Add Files/ClamAV authorization tests.
- [ ] Add user privacy tests.

---

## P32-W05 — Admin Errors & Reports UI, linking, merging, investigation workflow, and diagnostics bundle

### Admin navigation

Add one Admin area localized as:

`Błędy i zgłoszenia`

English equivalent:

`Errors & Reports`

Do not create a redundant Overview page.

Existing System Status provides the operational summary.

The Admin diagnostics area contains the two primary data surfaces:

- Technical Issues;
- User Reports.

### Technical Issues table

Use the canonical shared table foundation.

Show useful columns including:

- severity;
- status;
- issue summary;
- occurrence count;
- affected-user count;
- first seen;
- last seen;
- latest release;
- regression marker.

Support filters for:

- status;
- severity;
- source;
- module;
- route;
- release;
- regression;
- date range;
- affected user.

Support search by useful technical identifiers including:

- exception/error class;
- normalized message;
- fingerprint;
- normalized route;
- correlation ID;
- issue ID.

### Technical Issue detail

Expose a readable investigation view with:

- normalized exception/error;
- sanitized stack;
- severity;
- manual severity override state;
- status;
- first seen;
- last seen;
- total occurrences;
- recent occurrence rate;
- affected users;
- releases;
- routes;
- modules;
- sources;
- representative detailed occurrences;
- safe breadcrumbs;
- related correlation/request IDs;
- issue timeline;
- linked User Bug Reports;
- internal Admin diagnostic note.

Do not turn this into a raw log viewer.

### Related logs

Provide:

`Open related logs`

or equivalent.

Deep-link to the existing Atlas log viewer with the appropriate correlation/request filter where possible.

Do not copy the entire log store into Diagnostics.

A separate embedded log viewer is not required.

### Source snippet

Where the exact source is available locally for the referenced release/file:

- show a small source-code snippet around `file:line` on demand.

Occurrence persistence stores only required source location metadata.

Do not copy source snippets into every occurrence row.

If historical release source is unavailable:

- show no snippet;
- retain stack/file/line metadata.

### User Reports table

Use the shared table foundation.

Show useful fields including:

- status;
- reporter;
- short description preview;
- route/page;
- submitted time;
- attachment count;
- linked Technical Issue count.

Support filters:

- status;
- reporter;
- module/route;
- has attachment;
- linked/unlinked;
- date range.

### User Report detail

Show authorized Admin:

- reporter;
- description;
- expected behavior;
- actual behavior;
- attachments;
- route/page;
- safe context;
- safe breadcrumbs;
- possible related Technical Issues;
- confirmed linked Technical Issues;
- primary Technical Issue;
- internal Admin note.

Admin access to attachments still goes through canonical Files authorization.

### User Report privacy

Only authorized Admin Diagnostics users may see another employee's report.

Manager hierarchy does not provide access.

Team membership does not provide access.

Reporting employee only sees the limited `My Reports` representation.

### Internal note

Allow one simple internal Admin diagnostic/resolution note where useful.

Do not build threaded comments.

Do not expose the internal note to the reporting employee.

Do not add a separate user-facing resolution message.

The employee needs only the status.

### Relation suggestions

For a User Bug Report, calculate a small set of likely related Technical Issues based on safe factors such as:

- correlation/request ID;
- user;
- session reference;
- route;
- module;
- close timestamp.

Show a small bounded candidate set, e.g. the best few candidates.

Do not automatically merge or link based solely on the suggestion.

Admin confirms relations.

### Linking

One User Bug Report may link to multiple Technical Issues.

One linked issue may be marked as the Primary relation.

One Technical Issue may have many linked User Bug Reports.

Resolving a Technical Issue does not automatically resolve linked User Bug Reports.

Admin closes reports deliberately.

### Merge

Provide authorized issue merge according to P32-W03.

Old issue IDs remain historical aliases.

No Undo Merge.

No Split.

### Manual User Report deletion

Authorized Admin may permanently delete a User Bug Report.

Deletion requires:

- high-risk confirmation;
- mandatory reason;
- Audit;
- coordinated Files attachment cleanup.

After hard deletion:

- report disappears from the reporter's My Reports;
- no user-facing tombstone is required;
- Technical Issues remain;
- removed report links disappear.

Do not provide a normal permanent-delete action for Technical Issue aggregates.

### Diagnostics copy bundle

Technical Issue detail provides two separately authorized actions:

- `Copy diagnostics as text`;
- `Copy diagnostics as JSON`.

The bundle is sanitized through the canonical Diagnostics sanitizer.

Include useful content such as:

- issue identity;
- normalized error;
- stack;
- release;
- route/module;
- representative occurrence;
- correlation IDs;
- safe context;
- safe breadcrumbs;
- source snippet where available and appropriate.

Never include:

- screenshot binary;
- raw private Files;
- secrets;
- passwords;
- cookies;
- tokens;
- raw session IDs;
- raw unrelated private business content.

A safe file ID/name may be included only where the current Admin is authorized and it is useful.

Do not add:

- diagnostic ZIP export;
- CSV dump of the Diagnostics store;
- PDF export;
- downloadable diagnostics bundle.

Copy text/JSON is sufficient.

### Tasks

- [ ] Add Admin Errors & Reports navigation.
- [ ] Do not add redundant Overview.
- [ ] Add Technical Issues DataTable.
- [ ] Add all accepted Technical Issue filters.
- [ ] Add affected-user filtering.
- [ ] Add technical search.
- [ ] Add Technical Issue detail.
- [ ] Add internal Admin note.
- [ ] Add Open related logs deep link.
- [ ] Add on-demand source snippet.
- [ ] Add User Reports DataTable.
- [ ] Add accepted User Report filters.
- [ ] Add User Report detail.
- [ ] Enforce Admin-only cross-user report access.
- [ ] Add bounded related-issue suggestions.
- [ ] Add many-to-many issue/report links.
- [ ] Add Primary issue relation.
- [ ] Prevent automatic User Report resolution from issue resolution.
- [ ] Add authorized issue merge.
- [ ] Add high-risk User Report delete.
- [ ] Coordinate User Report Files cleanup.
- [ ] Add Copy diagnostics as text.
- [ ] Add Copy diagnostics as JSON.
- [ ] Do not add Diagnostics CSV/PDF export.
- [ ] Add Admin authorization tests.

---

## P32-W06 — Health/System Status integration and diagnostic Notifications

### Health integration principle

Diagnostics contributes a meaningful operational quality signal to Atlas Health/System Status.

One isolated unhandled exception must not automatically mean:

`the application is dead`.

Do not implement:

```text
one exception
→ application unavailable
```

Health consumes Diagnostics aggregates and thresholds.

### User Reports and Health

Manual User Bug Reports do not automatically affect Health.

A user saying:

`This table is missing data`

is important but does not by itself prove infrastructure/application health degradation.

If that report is linked to a genuine Technical Issue, the Technical Issue's metrics may influence Health normally.

### Diagnostics health signals

Health evaluation may consider safe aggregate signals such as:

- new Critical Technical Issue;
- regression;
- recent technical occurrence rate;
- affected-user rate;
- repeated frontend error rate;
- repeated backend error rate;
- repeated queue/scheduler technical failure rate;
- significant ignored-issue impact growth;
- Diagnostics capture/evaluation pipeline unavailable.

Do not evaluate Health by loading huge raw occurrence result sets.

Maintain/use bounded aggregate counters suitable for fast Health evaluation.

### Operational semantics

Support a distinction such as:

```text
Application operational status: Healthy
Diagnostics signal: Warning
```

for isolated/recent issues.

For higher impact:

```text
Application operational status: Degraded
Diagnostics: Elevated technical error impact
```

For a genuinely severe aggregate pattern:

```text
Application operational status: Unhealthy
Diagnostics: Critical application failure pattern
```

Exact health thresholds must be configurable with safe documented defaults.

### Readiness and liveness boundary

Diagnostics operational severity alone must NOT make canonical liveness/readiness endpoints fail.

A deployment must not become technically `not ready` solely because a recent application error/regression exists.

`/live` and `/ready` continue answering the infrastructure/runtime question they already own.

System Status may still show:

- Degraded;
- Unhealthy due to Diagnostics

while the application is technically live/ready.

Do not conflate operational quality with process liveness/readiness.

### Diagnostics pipeline unavailable

If Diagnostics evaluation/storage itself is unavailable:

- expose `Diagnostics signal unavailable` or equivalent in System Status;
- preserve Health endpoint operation where possible;
- do not recursively trigger Diagnostics while evaluating Diagnostics failure;
- structured logging remains fallback.

### Health settings

Expose a small, understandable set of Admin-controlled thresholds rather than dozens of obscure tuning knobs.

At minimum support configuration for concepts such as:

- recent technical error occurrence rate;
- affected-user impact;
- critical regression handling;
- Diagnostics pipeline unavailable behavior.

Provide:

`Reset to defaults`

or equivalent.

Use documented sensible defaults.

Do not require Admin to design the scoring algorithm manually.

### System Status

Add an `Application diagnostics` section to existing System Status.

Expose safe aggregates such as:

- Diagnostics status;
- open Technical Issue count;
- Critical issue count;
- active regression count;
- recent occurrences;
- recently affected users;
- link to Errors & Reports.

Do not expose private User Bug Report descriptions or detailed sensitive context on System Status.

### Notifications

Generate existing Atlas Notifications for authorized Admin recipients when appropriate, including:

- new Critical Technical Issue;
- regression;
- significant impact increase after cooldown.

Do not generate a Notification for every occurrence.

Recipients are users who possess the required canonical Diagnostics notification/view permission according to the existing authorization model.

Reuse existing Notifications channel preferences.

If those users enabled email for the relevant Notification type, the existing Notifications infrastructure handles email.

Do not create a separate Diagnostics email preference system.

### Cooldown/aggression control

A continuing Critical Issue may produce another alert only when:

- impact grows materially;
- or another meaningful configured cooldown/escalation condition occurs.

Do not create notification storms.

An `Investigating` issue may still generate a meaningful impact-growth alert.

An `Ignored` issue may still generate a meaningful impact-growth alert without changing its lifecycle status.

### Tasks

- [ ] Add Diagnostics aggregate Health signal.
- [ ] Keep User Bug Reports out of automatic Health evaluation.
- [ ] Add warning/degraded/unhealthy operational semantics.
- [ ] Keep liveness/readiness independent from Diagnostics severity.
- [ ] Add fast aggregate Health evaluation.
- [ ] Add Diagnostics signal unavailable state.
- [ ] Prevent recursive Health/Diagnostics failure.
- [ ] Add configurable Health thresholds.
- [ ] Add safe defaults and Reset to defaults.
- [ ] Add Application diagnostics to System Status.
- [ ] Add Critical issue Notification.
- [ ] Add regression Notification.
- [ ] Add impact-growth Notification with cooldown.
- [ ] Notify permission-authorized Admin recipients.
- [ ] Reuse existing Notifications/email preference behavior.
- [ ] Add Health/notification tests.

---

## P32-W07 — Retention, sampling configuration, purge, and lifecycle cleanup

### Detailed occurrence retention

Detailed Technical Issue occurrences contain comparatively heavy diagnostic context.

Canonical default retention:

`90 days`

This value is configurable by authorized Admin.

Retention may remove old detailed data such as:

- sanitized stacks;
- old breadcrumbs;
- old browser context;
- old detailed request context;
- detailed occurrence rows.

### Technical Issue aggregate retention

Technical Issue aggregates default to indefinite retention.

Do not provide a normal `Delete Technical Issue permanently` action.

After detailed occurrences are removed, preserve useful long-lived aggregate history including:

- total occurrence count;
- first seen;
- last seen;
- release history;
- affected-user aggregates;
- route/module/source aggregates;
- lifecycle;
- severity history;
- regression history;
- merge aliases;
- linked surviving User Bug Reports.

### Manual occurrence purge

Authorized Admin may run a controlled:

`Purge old diagnostic occurrences`

operation.

The operation:

- uses the existing Managed Processes/queue foundation;
- operates by explicit cutoff/retention rules;
- does not block a normal HTTP request for a large deletion;
- preserves Technical Issue aggregates;
- is auditable;
- reports completion/failure safely.

### User Bug Report retention

Canonical default:

`null`

meaning retain indefinitely.

Admin may configure a finite retention period.

Retention cleanup of a User Bug Report must also coordinate:

- Files attachments;
- issue/report relations;
- dependent report-specific records.

Do not leave orphaned Files.

### Manual User Bug Report deletion

Manual deletion remains available according to P32-W05 independently from automatic retention.

### Flood/sampling settings

Expose only the sampling/flood configuration required to protect persistence.

Do not make fingerprint regexes configurable.

Do not create a generalized event-processing rules engine.

### Tasks

- [ ] Add 90-day default detailed occurrence retention.
- [ ] Make detailed occurrence retention configurable.
- [ ] Keep Technical Issue aggregate indefinite.
- [ ] Preserve aggregate history after occurrence cleanup.
- [ ] Add scheduled detailed occurrence retention.
- [ ] Add managed manual occurrence purge.
- [ ] Preserve issue aliases/links/history during purge.
- [ ] Add null/indefinite User Bug Report default retention.
- [ ] Add configurable finite User Bug Report retention.
- [ ] Delete User Bug Report Files dependents safely.
- [ ] Add flood/sampling settings.
- [ ] Add retention preview/status where consistent with existing Admin patterns.
- [ ] Add retention/purge lifecycle tests.

---

## P32-W08 — Audit, operational failure safety, private source diagnostics, and development fixtures

### Audit

Audit structural/Admin Diagnostics actions such as:

- Technical Issue status change;
- manual reopen;
- severity override;
- severity override removal;
- issue merge;
- User Bug Report ↔ Technical Issue link/unlink;
- Primary issue change;
- User Bug Report status change;
- User Bug Report hard delete;
- internal note mutation where appropriate;
- retention configuration change;
- sampling configuration change;
- Health threshold change/reset;
- manual purge start/completion/failure.

Audit must not become a second error store.

Do not copy into Audit:

- full stack traces;
- every occurrence;
- breadcrumbs;
- User Bug Report free-text description;
- screenshot contents;
- private attachment contents;
- arbitrary request context.

Use safe identifiers/metadata only.

Automatic occurrence creation itself does not require one Audit record per occurrence.

### Logging fallback

Structured logging remains available independently of Diagnostics persistence.

A Diagnostics database failure must not erase the original error.

Where possible emit safe information that Diagnostics capture itself failed without recursively re-entering Diagnostics.

### Source diagnostics

Frontend source mapping uses private release artifacts.

Backend/source snippets are generated on demand from available release source.

Do not permanently duplicate application source inside every occurrence.

If source for an old release no longer exists locally:

- source snippet is unavailable;
- historical issue still remains diagnostically useful.

### Development/testing generators

Provide deterministic development/testing-only mechanisms to create representative Diagnostics conditions.

At minimum support controlled fixtures/scenarios for:

- backend exception;
- frontend exception;
- queue technical failure;
- duplicate/fingerprint flood;
- regression of a previously resolved issue;
- sanitized sensitive-payload fixture.

These tools exist to test Diagnostics reliably.

They must be:

- unavailable in production;
- impossible to invoke through normal production routes;
- protected by durable environment/architecture guardrails.

Do not require a developer to manually corrupt production-like application code merely to test the panel.

### No external error-monitoring service

Do not add:

- Sentry;
- Bugsnag;
- Rollbar;
- hosted APM/error monitoring;
- self-hosted third-party error-monitoring platform.

Do not add external infrastructure for this phase.

### Tasks

- [ ] Add structural Diagnostics Audit events.
- [ ] Keep occurrence bodies/stacks out of Audit.
- [ ] Preserve structured-log fallback.
- [ ] Add Diagnostics self-failure safety.
- [ ] Add private frontend source-map handling.
- [ ] Add on-demand backend/source snippets.
- [ ] Add dev/test backend exception generator.
- [ ] Add dev/test frontend exception generator.
- [ ] Add dev/test queue failure generator.
- [ ] Add dev/test fingerprint flood generator.
- [ ] Add dev/test regression generator.
- [ ] Add dev/test sensitive-data sanitizer fixture.
- [ ] Make all generators impossible in production.
- [ ] Add architecture tests proving production exclusion.
- [ ] Confirm no Atlas-owned Sentry package/config/env/runtime/browser/release/deployment hook remains and no replacement external monitor was introduced.
- [ ] Confirm generic shared secret redaction remains functional after Sentry-specific sanitizer removal.

---

## P32-W09 — Browser acceptance, backend coverage, documentation, and final closure

### Browser acceptance

Playwright must cover real rendered workflows rather than only component tests.

At minimum cover:

#### User Bug Report

- global Report bug button;
- permission visibility;
- modal;
- required description;
- expected/actual optional fields;
- multiple attachments;
- picker;
- drag/drop where automation permits;
- clipboard paste where automation permits;
- screenshot capture where supported;
- safe screenshot fallback where unsupported;
- screenshot preview;
- submit idempotency;
- My Reports;
- user cannot edit/comment/delete;
- status-change Notification;
- `Ignored` user-facing friendly label.

#### Admin User Reports

- Admin Errors & Reports;
- no redundant Overview page;
- User Reports table;
- filters;
- report detail;
- Files attachment access;
- possible related issue suggestion;
- manual links to multiple Technical Issues;
- Primary issue;
- issue resolution does not auto-resolve report;
- manual report status change;
- high-risk report delete;
- deleted report disappears from My Reports.

#### Technical Issues

- backend automatic error capture;
- frontend automatic error capture;
- deduplication;
- occurrence aggregation;
- affected-user aggregation;
- release history;
- filters/search;
- status lifecycle;
- manual reopen;
- severity override;
- regression reopen;
- ignored impact growth;
- issue merge;
- old alias resolves to canonical issue;
- no Undo/Split;
- issue timeline;
- Open related logs;
- source snippet available/unavailable states;
- Copy diagnostics as text;
- Copy diagnostics as JSON.

#### Privacy

Prove Diagnostics does not expose representative:

- password;
- cookie;
- Authorization header;
- token;
- session ID;
- integration secret;
- raw form values;
- private Chat body;
- unrelated transcript content;
- raw Files contents.

Prove safe session reference is not the raw session identifier.

#### Health

Cover:

- isolated Technical Issue leaves application live/ready;
- Diagnostics warning state;
- degraded aggregate state;
- unhealthy operational aggregate state;
- readiness/liveness remain semantically independent;
- User Bug Report alone does not alter Health;
- Diagnostics pipeline unavailable state;
- System Status Application diagnostics section;
- Critical/regression Notification;
- cooldown prevents notification storm.

### Backend/integration coverage

Add meaningful tests for:

- Laravel exception capture;
- application 5xx capture;
- no duplicate capture of same backend failure;
- queue failure;
- scheduler failure;
- Managed Process failure;
- frontend ingest validation;
- chunk-load failure ingest;
- expected 404/403/422 exclusion;
- offline/network exclusion;
- fingerprint normalization;
- dynamic route normalization;
- cross-release identity;
- exact aggregate counting under flood sampling;
- sampling threshold;
- affected-user aggregates;
- regression;
- merge aliases;
- concurrent occurrences;
- correlation IDs;
- session reference hashing;
- sanitizer;
- retention;
- aggregate survival after occurrence purge;
- User Report Files cleanup;
- permissions;
- notification cooldown;
- recursive failure protection;
- production exclusion of dev fixtures.

### Browser matrix

Cover critical workflows in:

- Chromium;
- Firefox;
- Polish;
- English;
- light theme;
- dark theme;
- responsive/mobile where relevant.

Maintain existing Atlas quality expectations:

- no unexpected console errors other than intentional deterministic error-fixture assertions;
- no uncaught runtime errors outside controlled error-capture test cases;
- no unexpected failed asset/API requests;
- no raw translation keys;
- no unresolved interpolation placeholders.

Intentional deterministic error-generator tests must explicitly scope/consume the expected error so they do not weaken the normal global console/runtime cleanliness guard.

### Documentation

At completion update canonical current-state documentation for:

- Diagnostics;
- structured logging relation;
- Health/System Status relation;
- Notifications;
- Files/report attachments;
- Authorization;
- frontend error handling;
- release/source-map diagnostics;
- retention;
- Admin operations;
- testing.

Do not document Sentry as a dependency. Rewrite current-state Sentry documentation to the first-party Diagnostics and generic redaction contracts after removal.

### Tasks

- [ ] Add User Bug Report browser E2E.
- [ ] Add attachment/screenshot browser E2E.
- [ ] Add My Reports browser E2E.
- [ ] Add Admin User Reports browser E2E.
- [ ] Add automatic backend Technical Issue E2E/integration coverage.
- [ ] Add automatic frontend Technical Issue browser coverage.
- [ ] Add deduplication/fingerprint coverage.
- [ ] Add regression coverage.
- [ ] Add merge/link coverage.
- [ ] Add Copy diagnostics coverage.
- [ ] Add sanitizer/privacy negative coverage.
- [ ] Add Health integration coverage.
- [ ] Add status/Critical/regression notification coverage.
- [ ] Add flood/sampling backend tests.
- [ ] Add retention/purge backend tests.
- [ ] Add recursive failure tests.
- [ ] Add dev-fixture production guard tests.
- [ ] Cover Chromium and Firefox.
- [ ] Cover PL and EN.
- [ ] Cover light and dark.
- [ ] Preserve console/runtime/request cleanliness.
- [ ] Run targeted backend tests.
- [ ] Run targeted frontend tests.
- [ ] Run required full Atlas quality gates.
- [ ] Update all affected canonical documentation.
- [ ] Run a repository-wide verification that no application-owned Sentry package, configuration, environment, runtime, browser, source-map, release, deployment, test, or documentation hook remains.
- [ ] Verify generic secret redaction remains functional and first-party Diagnostics owns the resulting issue behavior.

---

## Explicit out of scope

Phase 32 does not implement:

- Sentry;
- Bugsnag;
- Rollbar;
- external error-monitoring SaaS;
- self-hosted third-party error-monitoring stack;
- distributed tracing platform;
- application performance monitoring suite;
- profiler;
- browser Session Replay;
- continuous screen recording;
- automatic user screenshots;
- desktop capture;
- Jira-like issue management;
- assignees;
- sprint planning;
- story points;
- due dates;
- project boards;
- team discussion threads;
- threaded issue comments;
- manual fingerprint regex/rule editor;
- manual issue split;
- Undo Merge;
- normal hard deletion of Technical Issue aggregates;
- raw request-body collection;
- raw response-body collection;
- blanket query-string capture;
- public production source maps;
- diagnostic CSV/PDF store export;
- downloadable diagnostics ZIP/bundle;
- separate Diagnostics email system;
- diagnostics-specific configurable report rate-limiting subsystem;
- replacement of Pulse;
- replacement of Telescope;
- replacement of structured logs;
- replacement of existing Health dependency checks.

---

## Permanent guardrails

- [ ] Automatic Technical Issues and manual User Bug Reports remain separate concepts.
- [ ] Structured logs remain a fallback when Diagnostics persistence fails.
- [ ] Diagnostics capture cannot recursively capture itself without bound.
- [ ] One repeated technical failure aggregates into one canonical issue where fingerprint rules match.
- [ ] Dynamic user IDs/request IDs do not fragment fingerprints.
- [ ] Aggregate occurrence counts remain accurate during detailed sampling.
- [ ] Resolved issues reopen immediately as regressions when they recur.
- [ ] Ignored issues continue measuring impact.
- [ ] Manual severity override is never silently overwritten.
- [ ] Issue merge preserves aliases/history.
- [ ] Merge has no Undo and there is no manual Split.
- [ ] 404/403/422 and normal validation/domain rejection do not create noise.
- [ ] Ordinary browser offline/Wi-Fi loss does not create Technical Issues.
- [ ] Raw request/response bodies are not captured by default.
- [ ] Full query strings are not captured by default.
- [ ] Passwords/tokens/cookies/secrets are redacted.
- [ ] Raw session IDs are never persisted/exposed.
- [ ] Safe breadcrumbs do not contain entered private content.
- [ ] Normal breadcrumb collection is not continuously streamed to the server.
- [ ] Source maps are not publicly served.
- [ ] User screenshots are explicit and previewed.
- [ ] Diagnostics never captures the user's full desktop.
- [ ] User Bug Report attachments use Files/ClamAV.
- [ ] Reporting users cannot read other users' reports.
- [ ] Managers do not inherit subordinate-report access.
- [ ] Team membership does not grant report access.
- [ ] User Reports may link to multiple Technical Issues.
- [ ] Technical Issue resolution does not auto-resolve User Reports.
- [ ] User Report hard delete cleans Files dependents.
- [ ] Technical Issue aggregates are not normally hard-deleted.
- [ ] Detailed occurrence retention defaults to 90 days.
- [ ] User Bug Report retention defaults to indefinite.
- [ ] User Bug Reports do not automatically affect Health.
- [ ] Diagnostics severity does not by itself fail liveness/readiness.
- [ ] Health evaluates bounded aggregates rather than scanning raw occurrence history.
- [ ] Critical/regression alerts do not become notification storms.
- [ ] Audit does not become a second Technical Issue store.
- [ ] Copy diagnostics is sanitized.
- [ ] Dev/test error generators cannot run in production.
- [ ] No external error-monitoring dependency is introduced.

---

## Completion criteria

Phase 32 is complete only when:

- [ ] backend technical failures are automatically captured;
- [ ] frontend technical failures are automatically captured;
- [ ] queue/scheduler/Managed Process technical failures are captured;
- [ ] expected validation/authorization/offline noise is excluded;
- [ ] correlation IDs connect diagnostics with existing logs;
- [ ] safe browser/user/release/session context exists;
- [ ] breadcrumbs-lite exists without continuous surveillance storage;
- [ ] frontend source maps remain private but useful for diagnostics;
- [ ] technical issues are fingerprinted and deduplicated;
- [ ] occurrence aggregates remain accurate under flood sampling;
- [ ] affected users/releases/routes/modules are understandable;
- [ ] severity and manual override work;
- [ ] regression reopen works;
- [ ] ignored impact growth remains visible;
- [ ] Technical Issue merge works with canonical aliases;
- [ ] global Report bug workflow works;
- [ ] multi-attachment Files/ClamAV workflow works;
- [ ] safe current-Atlas-view capture works where supported;
- [ ] screenshot fallback works where unsupported;
- [ ] My Reports works;
- [ ] reporting users cannot mutate reports after submission;
- [ ] Admin Errors & Reports works;
- [ ] Technical Issue filters/search/detail work;
- [ ] User Report filters/detail work;
- [ ] relation suggestions work without automatic linking;
- [ ] multiple links and Primary relation work;
- [ ] report deletion is high-risk and cleans Files;
- [ ] Copy diagnostics text/JSON is safe;
- [ ] Open related logs works;
- [ ] on-demand source snippets fail gracefully for unavailable releases;
- [ ] Diagnostics integrates with System Status;
- [ ] User Reports do not automatically degrade Health;
- [ ] liveness/readiness are not failed solely by Diagnostics severity;
- [ ] Critical/regression/impact alerts use existing Notifications;
- [ ] detailed occurrence retention defaults to 90 days;
- [ ] issue aggregates survive occurrence purge;
- [ ] User Report retention defaults to indefinite;
- [ ] manual purge uses Managed Processes;
- [ ] sanitizer negative tests prove representative secrets/private content do not leak;
- [ ] structured logging fallback survives Diagnostics failure;
- [ ] deterministic local/test error generators exist;
- [ ] deterministic generators cannot run in production;
- [ ] PL/EN and light/dark UI is complete;
- [ ] Chromium and Firefox critical workflows pass;
- [ ] normal console/runtime/request cleanliness remains protected;
- [ ] all required Atlas quality gates pass;
- [ ] canonical documentation is current;
- [ ] no Atlas Sentry package/integration/configuration/environment/runtime/browser/source-map/release/deployment hook remains;
- [ ] no replacement external error-monitoring dependency was added;
- [ ] generic shared secret redaction remains functional and first-party Diagnostics owns issue behavior;
- [ ] Phase 40 deployment implementation has not been started as part of this planning/implementation phase.
