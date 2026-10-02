# Phase 48 — Optional minimal approvals

**Status:** `not started`

## Objective

Add a deliberately small Optional Approval capability for business modules that need an explicit approve/reject decision before or around a business action.

The business module decides:

- when approval is required;
- which concrete Users are approvers;
- what business action/result depends on the approval.

Approvals does not own or orchestrate the business process.

## Dependencies

- Phase 37 safe typed resource references.
- Users.
- Authorization.
- Audit.
- Notifications.
- ModuleGate.
- localization.
- privacy/retention.

## Ownership

Create an Optional `Approvals` module.

It owns:

- approval request;
- approver decisions;
- approval status/history;
- requester;
- reason/context snapshot;
- Notifications;
- Audit.

It does not own the source business object or perform its business mutation.

## Request model

An approval request contains at least:

- public ID;
- requester;
- optional typed source resource reference;
- stable owner/action key;
- safe bounded description/context;
- one or more concrete approver Users;
- approval mode;
- created timestamp;
- terminal timestamp;
- status;
- cancellation metadata where applicable.

Do not persist arbitrary executable actions.

## Approvers

Support:

- one approver;
- multiple approvers.

Baseline multi-approver modes:

### `all`

All approvers must approve.

Any rejection makes the request rejected.

### `any`

Any one approval makes the request approved.

The request becomes rejected only when every eligible approver has rejected.

Do not add:

- weighted votes;
- quorum percentages;
- sequential approval stages;
- conditional branches;
- dynamic graph routing.

## Decisions

An approver may approve or reject while the request is pending and the approver remains eligible.

Each decision is immutable history.

Store:

- approver;
- decision;
- timestamp;
- optional/required reason according to the owner request contract.

## Cancellation

Allow authorized cancellation only while pending.

Cancellation does not erase prior decision history.

## Business handoff

Approvals exposes typed result/query/event contracts.

The source business module decides what to do after approved/rejected/cancelled.

Approvals never directly changes a foreign business status or table.

## Notifications

Notify:

- approvers of new pending request;
- requester/appropriate participants of terminal outcome where configured.

Use canonical notification catalog/preferences.

Avoid duplicate notification storms.

## Audit

Audit:

- request creation;
- approver set/mode snapshot;
- approve/reject decisions;
- cancellation;
- administrative exceptional actions if any.

Approval history itself is immutable business evidence but remains distinct from security Audit.

## Authorization

Source owner/requester permissions are validated at request creation.

Only listed eligible approvers may decide.

Admin mode does not automatically confer approval rights.

## Privacy

Approval context contains only enough information to decide.

Use safe source references rather than copying entire business objects.

Privacy lifecycle follows source/legal requirements.

## Search

Do not expose all approval requests through global Search by default.

Dedicated Inbox/history surfaces are preferred.

## Files

No baseline Files ownership is required.

If a business module needs approval of a File/document, use the source resource/File authorization contracts rather than copying the file.

## Managed Processes

Approval decisions are ordinary synchronous business actions.

Do not use Managed Processes as a workflow engine.

## Module activation

Approvals is Optional.

Consumers that require approval cannot activate/configure that path while Approvals is inactive.

## UI

Provide:

- My pending approvals;
- requested-by-me/history where useful;
- approval detail;
- approve/reject;
- cancellation where authorized.

PL/EN, responsive, light/dark required.

## Explicit non-goals

- BPMN;
- workflow engine;
- sequential approval stages;
- weighted voting;
- quorum engine;
- rules designer;
- business-action execution;
- dynamic process builder.

## Tasks

### P48-W01 — Module/request/decision model

- [ ] Create Optional Approvals module, schema, permissions, module metadata, public contracts, and docs.
- [ ] Implement immutable request/decision history.

### P48-W02 — Single and multi-approver policies

- [ ] Implement single approver.
- [ ] Implement `all`.
- [ ] Implement `any`.
- [ ] Add concurrency tests for simultaneous decisions.

### P48-W03 — Business source/handoff

- [ ] Integrate safe source references.
- [ ] Implement typed result/event/public query contracts.
- [ ] Prohibit direct foreign business mutation.

### P48-W04 — Notifications, Audit, cancellation

- [ ] Implement notification lifecycle.
- [ ] Add Audit.
- [ ] Implement pending-only authorized cancellation preserving history.

### P48-W05 — UI, activation, privacy, docs

- [ ] Implement pending/history/detail UI.
- [ ] Add PL/EN/light/dark/browser coverage.
- [ ] Test activation/dependency, authorization, privacy, and negative cases.
- [ ] Update canonical docs.

## Completion criteria

- [ ] One and multiple approvers are supported.
- [ ] `all` and `any` have deterministic semantics.
- [ ] Decision history is immutable.
- [ ] Business modules own consequences.
- [ ] No workflow/BPMN/rules-engine behavior exists.
- [ ] Notifications/Audit/authorization/privacy are correct.
- [ ] `WORKROAD.md` status is `complete`.
