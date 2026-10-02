# Phase 42 — Optional Work Management and Tasks

**Status:** `not started`

## Objective

Add a generic Optional Work Management capability for assigning and organizing work without embedding any concrete business domain into Core or Optional foundation code.

Work Management answers:

- what must be done;
- who/which Team owns the work;
- what priority it has;
- when it is planned/due;
- where the user must go to perform the real business action;
- what remains in personal or Team queues.

It does not estimate how long a worker should take.

It does not implement BPMN, a generic workflow engine, rules automation, employee productivity scoring, or a business-domain process model.

## Dependencies

Required foundations:

- Phase 37 safe typed resource references and action targets;
- Users;
- Teams;
- Authorization;
- manager hierarchy;
- ModuleGate;
- Audit;
- Calendar;
- Notifications;
- Files;
- localization.

Optional integrations:

- Phase 40 Comments;
- Phase 40 Tags/Custom Fields where registered;
- Phase 41 Work Schedule;
- Search.

Optional integrations must have explicit module metadata and tested reduced modes.

If an optional integration is configured as required for a specific deployment/profile, its module must be active.

## Ownership

Create an Optional `WorkManagement` module.

It owns Task state.

It never owns the business resource on which work is performed.

A Task may exist with or without a source resource.

## Task model

A Task contains at minimum:

- public ID;
- title;
- optional description;
- status;
- priority;
- assignment state;
- optional assignee User;
- owning/assigned Team;
- assigned-by User/system actor where available;
- created timestamp;
- optional planned-start instant/date semantics as explicitly defined;
- optional due instant/date semantics as explicitly defined;
- optional source resource reference;
- optional typed action target;
- completion/cancellation timestamps;
- Audit/provenance context.

Do not store an estimated duration as a required or baseline planning field.

Do not calculate worker performance against an expected duration.

## Status

Use a small explicit Task lifecycle.

Baseline states:

- `open`;
- `in_progress`;
- `completed`;
- `cancelled`.

Assignment/claim state remains distinct from business status where needed.

Do not allow arbitrary Admin-created statuses in the baseline.

Do not turn tags into statuses.

## Priority

Use a small code-owned priority set with localized presentation.

Do not build a configurable rules engine around priority.

## Assignment

Support:

- direct User assignment within the allowed Team scope;
- Team assignment;
- unassigned Team queue;
- claim by an eligible User;
- release/reassignment where authorized;
- assigned-by provenance.

Backend authorization must validate Team membership/scope and manager permissions.

## Team queues

Tasks may wait in a Team queue without a concrete User.

Eligible Users may claim tasks according to permission/scope.

Claiming must be concurrency-safe.

Two Users must not successfully claim the same exclusive Task simultaneously.

## Source reference

Use Phase 37 typed references:

- owner module;
- resource type;
- public ID.

Do not use foreign models/tables.

Do not use an arbitrary persisted URL as the canonical source model.

## Typed action target

Where a Task directs the worker to a business action, store a typed action target:

- source owner;
- source resource type/public ID;
- stable action key.

The owning module resolves that action key to the authorized route/action.

Examples of action semantics include:

- open resource;
- review resource;
- verify data;
- prepare document;
- perform owner-defined operation.

The action key is code-owned by the source owner.

Work Management does not execute foreign business mutations directly.

## Task templates

Support Admin-managed Task templates for repetitive task creation.

Templates may provide defaults for:

- localized title/description;
- priority;
- Team where appropriate;
- relative due/planned offsets;
- registered source/action-target class where safely parameterized.

Templates do not:

- execute workflow;
- automatically discover domain data;
- create arbitrary rules;
- contain executable expressions;
- replace source-module business logic.

Source modules decide when to request Task creation.

## Public contracts

Expose narrow framework-independent contracts for:

- create Task;
- assign/reassign;
- claim/release;
- start;
- complete;
- cancel;
- retrieve authorized Task summary;
- list My Work;
- list Team queue under proper manager/User scope;
- resolve task/source references where authorized.

Use idempotency for producer-driven Task creation where repeated delivery is possible.

## Calendar

Tasks may contribute planned/due items to the shared Calendar through explicit Calendar contribution contracts.

Do not duplicate Calendar persistence.

Calendar contribution does not make Calendar the Task source of truth.

## Notifications

Notify meaningful events such as:

- direct assignment;
- relevant reassignment;
- selected due/overdue conditions according to accepted notification policy;
- producer-specific review request where configured.

Respect the canonical notification type catalog and user preferences.

Avoid duplicate notification storms.

## Work Schedule integration

Work Management may use Phase 41 to show Team/User schedule context.

Do not calculate fake hour-based remaining capacity from Task estimates.

Manager views may show:

- open Task count;
- due/overdue count;
- priority distribution;
- Team queue size;
- completed counts/trends;
- scheduled working-time context.

The system does not tell the employee how many minutes a Task "should" take.

## Comments/Files

If Comments is active and registered for Task resources, Task comments use Phase 40 Comments.

Do not create a second Task-specific comment system.

Task attachments use Files.

## Search

If Search is active, Tasks may contribute authorization-safe Search projections.

Search results must respect assignment/Team/source visibility.

## Audit

Audit meaningful Task lifecycle changes:

- creation;
- assignment/reassignment;
- claim/release;
- status changes;
- source/action-target changes where allowed;
- template changes;
- cancellation/completion corrections.

## Privacy/retention

Tasks may reveal business context.

Retain only required source labels/snapshots.

Do not copy entire foreign objects into Task persistence.

Deleted/unavailable source resources render safely without leaking prior unauthorized data.

Task retention/deletion participates in the privacy lifecycle.

## Manager scope

Manager views are constrained by accepted manager hierarchy and Team scope.

A manager does not gain global Task access merely because the manager UI exists.

## Module activation

Work Management is Optional.

Required dependencies block activation/deactivation.

Optional Comments, Search, Work Schedule, Tags, or Custom Fields integrations disappear safely when unavailable.

A configuration that explicitly requires an optional capability cannot be activated while that capability is inactive.

## UI

Provide:

- `My Work`;
- Team queue;
- manager work view;
- Task create/edit/detail;
- claim/release;
- source/action navigation;
- clear due/priority/status presentation;
- mobile/responsive behavior.

PL/EN and light/dark are required.

## Managed Processes

Task lifecycle itself is normal application work.

Do not use Managed Processes for every Task mutation.

Producer operations creating large Task sets may use existing Managed Processes where justified.

Do not create a second bulk/job system.

## Reports/exports

Authorized Task reports/exports may use existing Reports/Core Exports.

Do not expose foreign source data unless the source owner explicitly contributes it.

## Explicit non-goals

- BPMN;
- generic workflow engine;
- rule designer;
- automatic employee scheduling engine;
- employee productivity score;
- expected/estimated Task duration;
- keyboard/mouse monitoring;
- HR/payroll;
- generic business-state machine;
- arbitrary URL-only source integration;
- cross-module Eloquent/persistence access.

## Tasks

### P42-W01 — Module, persistence, permissions, and public contracts

- [ ] Create Optional WorkManagement module boundary, schema, permissions, module metadata, public DTOs/contracts, and docs.
- [ ] Implement Task lifecycle and public IDs.
- [ ] Add architecture guards.

### P42-W02 — Assignment and Team queues

- [ ] Implement User assignment, Team assignment, unassigned Team queue, claim/release, reassignment, and concurrency.
- [ ] Enforce Team membership, manager scope, and backend permissions.

### P42-W03 — Source references and typed action targets

- [ ] Integrate Phase 37 safe resource references.
- [ ] Implement owner-registered typed action targets.
- [ ] Prohibit arbitrary canonical URL targets.
- [ ] Add missing/deleted/disabled/inaccessible resource tests.

### P42-W04 — Task templates

- [ ] Implement bounded Admin-managed templates.
- [ ] Add localization, permissions, Audit, and safe source/action parameter rules.
- [ ] Prohibit executable workflow/rules behavior.

### P42-W05 — Calendar, Notifications, Files, Search, Comments

- [ ] Add Calendar contribution.
- [ ] Add canonical Notifications.
- [ ] Add Files attachments.
- [ ] Add optional Search projection.
- [ ] Reuse Phase 40 Comments when active instead of building Task comments.

### P42-W06 — My Work, Team queue, manager surfaces

- [ ] Implement user and manager UI.
- [ ] Show queue/count/priority/due context without duration targets.
- [ ] Add claim/release/reassign workflows.
- [ ] Add PL/EN, responsive, light/dark browser coverage.

### P42-W07 — Work Schedule integration

- [ ] Integrate optional Phase 41 expected schedule context.
- [ ] Prove no Task estimate is required.
- [ ] Do not derive productivity or expected completion duration.

### P42-W08 — Privacy, reports, activation, tests, docs

- [ ] Add Audit, privacy/lifecycle, reports/exports, module activation, reduced-mode, authorization, concurrency, Search, Files, Calendar, Notification, and E2E coverage.
- [ ] Update canonical architecture/module/manager/testing docs.

## Completion criteria

- [ ] Tasks are product-neutral.
- [ ] Team/User assignment and queues are concurrency-safe and authorized.
- [ ] Business actions use typed owner-controlled targets.
- [ ] No estimated Task duration or productivity target exists.
- [ ] Calendar/Notifications/Files/Search/Comments integrations reuse existing foundations.
- [ ] Work Schedule integration is optional and does not turn Task counts into fake time estimates.
- [ ] Module activation/dependency behavior is correct.
- [ ] Documentation/tests are current.
- [ ] `WORKROAD.md` status is `complete`.
