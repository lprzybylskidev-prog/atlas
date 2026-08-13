# Phase 30 — Authorization, Team Structure, and mutation feedback repair

**Status:** `in progress`

## Objective

Repair the current Teams and Authorization administration workflows after manual product review exposed a coherent set of usability, state-model, hierarchy, and mutation-feedback problems that are too important to defer to final manual verification.

This phase is a later foundation evolution.

It does not reopen completed Phase 29 history.

The phase must:

- make user-team authorization presentation unambiguous;
- make User Edit the canonical authorization-mutation surface;
- make Team Edit authorization clearly read-only;
- simplify assignment source/provenance presentation to current state;
- rebuild Team Structure around explicit Employee / Manager / Head Manager semantics;
- make Head Manager scope represent the entire Team rather than a personal direct-report subtree;
- support multiple managers per subordinate;
- use drag-and-drop as the primary desktop relationship-assignment interaction;
- preserve accessible non-drag alternatives;
- move destructive reasons into focused confirmation modals;
- make backend/domain mutation failures visible to users;
- perform a repository-wide audit for silent mutation failures;
- perform a repository-wide audit for missing, duplicated, stale, or otherwise broken flash-message delivery;
- permanently protect the repaired workflows with browser and lower-level tests.

Chat remains a later phase and must not begin until this repair is complete.

## Dependencies

Phase 30 builds on the completed foundations, including:

- Authorization and Teams;
- Manager hierarchy;
- Audit;
- Settings and Localization;
- Sessions and active Team;
- shared frontend/UI foundations;
- Admin mode and high-risk confirmation;
- Phase 28 foundation repair;
- Phase 29 foundation acceptance repair.

Relevant canonical documentation includes:

- Authorization;
- Teams and manager hierarchy;
- frontend UI;
- UI glossary;
- Audit;
- network/browser behavior;
- quality gates;
- testing environment.

## Phase boundary

This is a targeted foundation repair.

It must not become a general full-application manual polish phase.

Small unrelated visual issues discovered elsewhere may remain for final manual verification unless they directly belong to:

- Authorization assignment UX;
- Team Edit;
- User Edit authorization;
- Team Structure;
- mutation error visibility;
- flash-message delivery;
- shared mutation-feedback infrastructure needed to prevent silent failures.

Do not start Chat, deployment, or final verification.

Do not add explicit permission-deny semantics.

Do not create a second Team Structure system.

Do not restore a separate Managers Admin area.

## Execution discipline

Workstreams are strictly sequential:

1. `P30-W01` — Authorization assignment semantics and current-source cleanup
2. `P30-W02` — Team Edit read-only authorization workflow
3. `P30-W03` — Team Structure structural-role and hierarchy semantics
4. `P30-W04` — Team Structure interaction and layout rebuild
5. `P30-W05` — Membership, relationship removal, and destructive-action UX
6. `P30-W06` — Global mutation error and flash-message audit
7. `P30-W07` — Localization, interaction polish, and permanent guardrails
8. `P30-W08` — Browser acceptance and final regression closure

Only the earliest incomplete workstream is active.

Do not start a later workstream while mandatory work remains in an earlier one.

Do not select work based on ease, speed, or file locality.

Do not split unfinished work into artificial micro-packages merely to report completion.

A workstream is complete only when its implementation, tests, documentation, legacy cleanup, and permanent guardrails are complete.

Do not calculate completion percentages or readiness percentages.

---

## P30-W01 — Authorization assignment semantics and current-source cleanup

### Authorization model

Keep the existing simple authorization model:

```text
role-granted permissions
+
directly granted permissions
=
effective permissions
```

There is no explicit deny layer.

Direct permissions only add permissions.

They never negate permissions granted by roles.

If a permission is granted by any assigned role, that role grant has effective priority over the direct-permission checkbox because removing a direct grant cannot remove the role grant.

Do not add:

- direct deny;
- negative permissions;
- permission precedence engines;
- deny-overrides;
- ACL-style allow/deny matrices.

### User Edit as the authorization mutation surface

User Edit remains the canonical Admin surface for changing a user's team-scoped:

- roles;
- direct permissions;
- supported user-team policy overrides.

Role selection is editable.

Direct-permission selection is editable only where the permission is not already effectively granted by a selected role.

### Role-derived direct-permission presentation

When a permission is granted by one or more selected roles, the direct-permission list must show that permission as:

- visually checked;
- disabled/non-toggleable;
- clearly marked as coming from the relevant role or roles.

Example product semantics:

```text
Permission A    checked + disabled    From role: Employee
Permission B    checked + editable    Granted directly
Permission C    unchecked + editable
```

The disabled checked state is presentation of effective role-derived access.

It must not automatically create a direct permission assignment.

If the permission already has a real direct grant in persistence and is also granted by a role:

- preserve the real direct grant;
- do not silently delete it;
- while the role remains selected, the permission stays checked and disabled because the effective permission cannot be removed from this control;
- if the role is later removed and the direct grant remains, the permission becomes checked and editable as a normal direct grant.

Changing selected roles must immediately recompute:

- role-derived permissions;
- disabled direct-permission states;
- source-role labels;
- effective permission preview.

### Assignment source means current source

The user-facing assignment source represents how the CURRENT assignment state was produced.

Use the simple product label:

`Source`

Do not show:

`Original source`

Do not show provenance-history wording such as:

- original snapshot;
- diverged from source;
- initial source;
- source snapshot divergence.

Accepted current-source examples:

```text
Source: Manual
Source: Preset — Manager
Source: Copy of permissions from Adam Kowalski
```

When an assignment is initially created manually:

```text
Source: Manual
```

When it is created from a preset and has not subsequently been manually changed:

```text
Source: Preset — <preset name>
```

When it is created from another user's assignment and has not subsequently been manually changed:

```text
Source: Copy of permissions from <user>
```

After a manual authorization edit, the CURRENT source becomes:

```text
Source: Manual
```

The main editing UI is about current state, not provenance history.

Historical facts such as:

- originally created from preset X;
- originally copied from user Y;
- later changed manually;
- who performed the change;
- when it changed;

belong in Audit where required for traceability.

Do not keep historical provenance wording in the normal authorization form merely because old persistence columns exist.

### Persisted provenance cleanup

Update the persisted authorization-assignment provenance contract to represent truthful current source.

Preserve optimistic concurrency/versioning.

Do not remove concurrency protection merely because source semantics are simplified.

Existing records that are already marked as having diverged from their original preset/copy snapshot must migrate to a truthful current source of `manual`.

Existing non-diverged preset/copy assignments may preserve their current preset/copy source.

Remove or retire obsolete divergence-specific fields/contracts if they have no remaining canonical purpose after migration.

Do not preserve dead pre-production persistence semantics solely for backward compatibility.

Audit remains the historical record.

### Unresolved source placeholder

The current rendered text must never expose unresolved interpolation such as:

```text
Original source: :source
```

or:

```text
Source: :source
```

Fix the interpolation contract and add permanent protection against this regression.

### Authorization labels

Interactive authorization UI must use human-readable localized labels in the active UI locale.

Technical permission keys may remain as secondary Admin metadata where useful, but they must not replace the primary human-readable label.

### Tasks

- [x] Preserve the role-grant plus direct-grant authorization model.
- [x] Explicitly document that no permission deny layer exists.
- [x] Keep User Edit as the canonical authorization-mutation surface.
- [x] Compute role-derived permission state for the direct-permission selector.
- [x] Render role-derived permissions as checked and disabled.
- [x] Show the role or roles granting each role-derived permission.
- [x] Preserve real overlapping direct grants without converting role grants into direct grants.
- [x] Recompute role-derived/direct/effective state immediately when roles change.
- [x] Replace user-facing `Original source` semantics with `Source`.
- [x] Make source describe the current assignment state.
- [x] Convert manually changed preset/copy assignments to current source `manual`.
- [x] Preserve optimistic assignment versioning.
- [x] Migrate existing diverged assignment provenance to truthful current-source semantics.
- [x] Remove obsolete divergence-specific persistence/contracts where no longer needed.
- [x] Keep provenance history in Audit rather than the main edit UI.
- [x] Remove the user-facing divergence/snapshot message.
- [x] Fix unresolved `:source` interpolation.
- [x] Add localized human-readable authorization labels.
- [x] Add backend, frontend, persistence, and migration tests for the new current-source contract.

Completion evidence (2026-08-13): User Edit now keeps role-derived, persisted direct, and effective permission state separate; role grants render as checked/disabled with localized granting-role labels and recompute reactively. Manual changes replace preset/copy current-source metadata with `manual` while optimistic versions and audit before/after source evidence remain. The pre-production canonical create migration removes `diverged_at`; existing development databases adopt the new schema through the standard fresh-migration workflow. The shared translator accepts Laravel colon placeholders, the shared DataTable formatter no longer emits `[status:...]`, and browser copy guards reject that diagnostic globally. Focused backend/frontend/persistence/schema tests, the full frontend check/build, and the affected schema-ownership test pass; the full PHPStan command remains affected by the documented native exit-139 tool failure.

---

## P30-W02 — Team Edit read-only authorization workflow

### Canonical ownership

Team Edit is not a second authorization-mutation surface.

Authorization mutation belongs to User Edit.

Team-context membership and hierarchy mutation belong to Team Structure.

Team Edit may show each current member's authorization state for context, but that state is read-only.

### Read-only behavior

For existing team members displayed in Team Edit:

- role checkboxes are visible but disabled;
- direct-permission checkboxes are visible but disabled;
- user-team policy override fields are disabled/non-editable;
- current assignment source is read-only;
- effective permission preview remains visible.

Do not render editable-looking controls that cannot be saved.

### Remove dead mutation controls

Team Edit must not show:

- authorization Save controls for member assignments;
- authorization-change reason input;
- membership-removal reason input;
- `Remove access`;
- any other member mutation control that is not actually supported by this surface.

A control must never emit an action that the parent surface does not handle.

### Collapsed assignment summary

Do not render ambiguous summary text such as:

```text
0 / 20
```

Use explicit localized semantics such as:

```text
0 roles · 20 direct permissions
```

Do not make users infer what each side of `/` means.

### Expanded assignment presentation

Do not render the same user/team assignment name twice immediately under itself after expansion.

The accordion/header already identifies the assignment.

Expanded content should contain the useful details only.

### Expand/collapse affordance

Every expandable authorization assignment row must have a visible expand/collapse affordance.

Use a canonical chevron or equivalent existing Atlas icon.

Requirements:

- collapsed state visibly points down;
- expanded state visibly points up or rotates consistently;
- the full header remains clickable;
- `aria-expanded` remains authoritative for accessibility;
- icon itself may be decorative for screen readers;
- focus behavior remains correct;
- no layout jump when toggling.

### Tasks

- [ ] Make Team Edit member authorization explicitly read-only.
- [ ] Disable role checkboxes.
- [ ] Disable direct-permission checkboxes.
- [ ] Disable user-team policy override fields.
- [ ] Render current Source as read-only state.
- [ ] Remove member authorization Save controls from Team Edit.
- [ ] Remove authorization mutation reason input from Team Edit.
- [ ] Remove membership-removal reason input from Team Edit.
- [ ] Remove the dead `Remove access` action from Team Edit.
- [ ] Ensure no child component emits an unsupported mutation action in this context.
- [ ] Replace ambiguous `roleCount / permissionCount` summary text.
- [ ] Remove duplicated assignment name in expanded content.
- [ ] Add a visible expand/collapse chevron.
- [ ] Preserve keyboard/focus/accessibility behavior.
- [ ] Add component and Playwright coverage for the read-only Team Edit contract.

---

## P30-W03 — Team Structure structural-role and hierarchy semantics

### Structural role is explicit

Each active Team membership has one canonical structural role:

- `employee`;
- `manager`;
- `head_manager`.

This structural role belongs to Teams.

It is not an Authorization role.

Do not infer company hierarchy role from Authorization role names.

A Manager must remain a Manager even when they temporarily have zero direct reports.

### Existing-state migration

Migrate current Teams-owned hierarchy state safely.

At minimum:

- every current head-manager assignment becomes `head_manager`;
- every non-head user currently acting as an active manager of another user becomes `manager`;
- remaining active members become `employee` unless an existing explicit Teams-owned structural fact proves otherwise.

Preserve relationship and membership history.

Do not rewrite historical Phase 17/28/29 records merely to make the new model look retroactive.

### Employee

An Employee:

- may report to zero, one, or multiple Managers;
- cannot have active direct reports;
- can be promoted to Manager;
- can be promoted to Head Manager.

### Manager

A Manager:

- may have zero or more direct reports;
- may directly manage Employees;
- may directly manage other Managers;
- may itself report to zero, one, or multiple Managers;
- may be promoted to Head Manager;
- may be changed back to Employee.

One subordinate may have multiple Managers.

The hierarchy therefore remains a directed acyclic graph rather than a strict tree.

### Head Manager

A Head Manager manages the Team as a whole.

A Head Manager does not own a personal direct-report subtree.

Head Manager scope is the entire active Team, constrained by normal permissions.

A Head Manager:

- has no active outgoing manager/report relationship edges;
- has no active incoming manager/report relationship edges;
- cannot be assigned under a Manager;
- cannot be used as a normal direct Manager target;
- does not need explicit direct-report edges to see/manage the Team scope.

Multiple Head Managers may exist where the current Team contract permits them.

Preserve the existing protection against removing/changing the last required active Head Manager.

### Structural-role transitions

Use one canonical structural-role change use case.

Every structural-role change requires a reason and normal Audit evidence.

It must use optimistic concurrency/stale-write protection.

#### Employee → Manager

- change structural role to Manager;
- preserve existing incoming Manager relationships;
- create no direct reports automatically.

#### Employee → Head Manager

- change structural role to Head Manager;
- atomically end any active incoming Manager relationships;
- create no explicit direct-report relationships.

#### Manager → Employee

- atomically end all active outgoing direct-report relationships;
- preserve valid incoming Manager relationships;
- change structural role to Employee.

#### Manager → Head Manager

- atomically end all active outgoing direct-report relationships;
- atomically end all active incoming Manager relationships;
- change structural role to Head Manager;
- Head Manager scope becomes the whole Team.

#### Head Manager → Manager

- enforce the last-required-Head-Manager invariant;
- change structural role to Manager;
- do not restore historical relationship edges automatically.

#### Head Manager → Employee

- enforce the last-required-Head-Manager invariant;
- change structural role to Employee;
- do not restore historical relationship edges automatically.

A transition that closes relationships must show its impact before confirmation.

The transition and required relationship endings must be one atomic business operation.

### Manager hierarchy validation

Preserve and adapt canonical hierarchy invariants:

- manager and report are active members of the same Team;
- only structural Managers can own normal direct-report edges;
- Head Managers cannot participate in normal manager/report edges;
- no self-management;
- no duplicate active edge;
- no cycles;
- multiple Managers for one subordinate are valid;
- Manager → Manager is valid when acyclic;
- stale versions are rejected;
- failed mutations leave no partial hierarchy state;
- active-process blockers remain enforced where the current accepted contract requires them;
- Audit remains atomic with successful high-value hierarchy mutations.

### Manager scope consumers

Update canonical manager-scope behavior used by current consumers.

A normal Manager's scoped people are derived from the accepted manager DAG according to the current consumer contract.

A Head Manager's scoped people are the whole active Team rather than a synthetic personal subtree.

Existing TimeTracking and other current manager-scope consumers must be verified against the new Head Manager semantics.

### Tasks

- [ ] Introduce the explicit Employee / Manager / Head Manager structural-role contract.
- [ ] Keep structural role separate from Authorization roles.
- [ ] Migrate existing active Team structure state deterministically.
- [ ] Preserve membership and relationship history.
- [ ] Allow Managers with zero direct reports.
- [ ] Preserve multiple Managers per subordinate.
- [ ] Preserve Manager-to-Manager relationships.
- [ ] Make Head Manager scope equal to the whole Team.
- [ ] Prevent Head Managers from participating in normal relationship edges.
- [ ] Add one canonical structural-role change use case.
- [ ] Require a reason for structural-role changes.
- [ ] Add impact preview for transitions that end relationships.
- [ ] Make role transition plus relationship cleanup atomic.
- [ ] Preserve optimistic concurrency.
- [ ] Preserve last-required-Head-Manager protection.
- [ ] Preserve self/cycle/duplicate/stale/process-blocker validation.
- [ ] Update manager-scope consumers for whole-Team Head Manager scope.
- [ ] Add migration, domain, integration, Audit, and scope regression tests.

---

## P30-W04 — Team Structure interaction and layout rebuild

### Main layout

Rebuild the primary Team Structure screen around people and structural roles rather than an always-visible technical graph.

The main screen contains three clear sections in this order:

1. Head Managers
2. Managers
3. Employees

Each active Team member appears exactly once in the appropriate structural-role section.

Do not make an always-visible hierarchy tree or graph the primary editing interface.

### Head Manager cards

A Head Manager row/card should communicate:

- identity;
- Head Manager role;
- whole-Team scope;
- expandable details;
- structural-role action.

Do not display:

- direct-report count as if the Head Manager owned personal direct reports;
- direct-report drop target;
- parent Manager assignment.

### Manager cards

A Manager row/card should communicate useful concise state such as:

- identity;
- Manager role;
- number of direct reports;
- number of Managers they report to where relevant;
- expandable details;
- structural-role action.

A Manager card is a valid relationship drop target.

### Employee cards

An Employee row/card should communicate useful concise state such as:

- identity;
- Employee role;
- number of Managers they report to;
- expandable details;
- structural-role action.

### Structural-role action

Use one clear action such as:

`Change structure role`

instead of several unrelated promotion/demotion buttons.

The modal shows the valid role choices:

- Employee;
- Manager;
- Head Manager.

The modal:

- explains the selected transition;
- shows relationship-impact information when applicable;
- requires a reason;
- requires confirmation;
- surfaces backend/domain rejection visibly.

### Drag-and-drop relationship assignment

On desktop, assigning a subordinate to a Manager is primarily performed through drag and drop.

A draggable candidate may be:

- Employee;
- Manager.

A Head Manager is not a normal draggable subordinate for manager assignment.

Valid drop targets are structural Managers only.

Dropping user B onto Manager A means:

`add A as one of B's Managers`

It does NOT mean:

`move B exclusively under A`.

Existing valid Manager relationships remain.

This preserves the accepted multiple-manager model.

Do not silently remove another Manager relationship during drag-and-drop assignment.

### Assignment confirmation

Relationship creation continues to use the canonical audited backend mutation.

If the existing relationship contract requires a reason, dropping opens a small focused confirmation modal that:

- identifies Manager;
- identifies subordinate;
- requires reason;
- confirms creation.

Drag-and-drop is a frontend interaction, not a second hierarchy backend.

### Invalid drop behavior

Backend remains the source of truth.

Reject visibly:

- self-management;
- cycle creation;
- duplicate relationship;
- Head Manager participation;
- inactive/non-member users;
- stale state;
- active-process blockers.

Do not fail silently.

### Accessible/mobile alternative

Drag and drop cannot be the only interaction.

Provide an equivalent accessible action for:

- keyboard users;
- touch/mobile;
- users who cannot use drag and drop.

The fallback must invoke the same canonical relationship use case.

Do not build a second relationship workflow with different rules.

### Expanded details

Use expandable member details instead of permanent visual clutter.

A Manager's details may show:

- direct reports;
- Managers they report to;
- relevant active relationship metadata;
- concise historical information where useful.

An Employee's details may show:

- current Managers;
- relevant membership/relationship detail.

A Head Manager's details must not pretend they have explicit direct-report edges.

Membership/history detail may remain available through expansion or another clearly secondary Team Structure detail area.

### Expand/collapse affordance

Use a visible chevron or equivalent affordance for expandable rows/cards.

Do not rely on users guessing that a row is expandable.

### Remove reparent-centric primary UX

The primary interface no longer assumes every subordinate has one parent.

Do not keep a confusing tree-style `move/reparent` interaction as the main workflow merely because an older phase implemented one.

The accepted primary semantics are now:

- add a Manager relationship;
- remove a Manager relationship;
- allow multiple Managers.

An existing atomic reparent backend use case may remain only if it still has a legitimate current consumer.

If it becomes dead/obsolete after the new UX is complete, remove its unused UI/contracts/code safely rather than preserving dead complexity.

### Tasks

- [ ] Rebuild Team Structure into Head Managers / Managers / Employees sections.
- [ ] Render each active member exactly once by structural role.
- [ ] Remove the always-visible graph/tree as the primary editor.
- [ ] Add concise Head Manager cards with whole-Team scope.
- [ ] Add concise Manager cards with direct-report/parent counts.
- [ ] Add concise Employee cards with Manager counts.
- [ ] Add one structural-role change action and modal.
- [ ] Add transition impact preview.
- [ ] Implement desktop drag-and-drop assignment onto Managers.
- [ ] Make drag-and-drop add a relationship rather than replace existing Managers.
- [ ] Keep Head Managers out of normal relationship drag/drop.
- [ ] Route drag/drop through the canonical backend mutation.
- [ ] Add the required relationship reason confirmation.
- [ ] Add accessible keyboard/mobile relationship assignment.
- [ ] Add expandable member details.
- [ ] Add visible expand/collapse chevrons.
- [ ] Remove obsolete reparent-centric primary UI.
- [ ] Remove dead reparent code/contracts only if they have no legitimate remaining consumer.
- [ ] Preserve responsive, keyboard, and focus behavior.

---

## P30-W05 — Membership, relationship removal, and destructive-action UX

### Team Structure remains the membership owner

Team Structure remains the canonical team-context surface for:

- adding members;
- ending/removing active Team membership;
- changing structural role;
- adding Manager relationships;
- removing Manager relationships.

Do not return membership mutation to Team Edit.

### No permanent reason inputs

Do not keep destructive-action reason inputs permanently visible in the main Team Structure UI.

Reasons belong to the destructive action being performed.

Use focused confirmation modals.

### Remove Manager relationship

Each active Manager relationship shown in details has a clear action such as:

`Remove assignment`

The confirmation modal shows:

- Manager;
- subordinate;
- required reason;
- destructive confirmation.

Successful removal:

- effective-dates/ends the relationship;
- preserves history;
- records canonical Audit evidence;
- refreshes the rendered structure.

### Remove Team membership

Ending Team access from Team Structure uses a confirmation modal with:

- user identity;
- required reason;
- relevant impact/blocker information;
- destructive confirmation.

Do not hard-delete membership history.

### Domain blockers must be visible

If membership removal is rejected because the user:

- is the protected last Head Manager;
- still has active Manager relationships;
- participates in another active Team Structure invariant;
- is blocked by another accepted domain rule;

show the actual localized reason in the UI.

The user must never experience:

`click → nothing happens`.

Do not duplicate every backend invariant in frontend code merely to predict failure.

The backend remains authoritative.

### Tasks

- [ ] Keep Team Structure as the only team-context membership mutation surface.
- [ ] Remove permanent destructive-reason inputs from the primary UI.
- [ ] Add relationship-removal confirmation modal with required reason.
- [ ] Add membership-removal confirmation modal with required reason.
- [ ] Preserve effective-dated relationship history.
- [ ] Preserve effective-dated membership history.
- [ ] Preserve canonical Audit evidence.
- [ ] Render domain blocker errors visibly.
- [ ] Render field-specific validation errors next to the relevant modal field.
- [ ] Render operation-level errors in the action/modal context.
- [ ] Keep state unchanged after rejected operations.
- [ ] Add browser coverage for successful and rejected destructive actions.

---

## P30-W06 — Global mutation error and flash-message audit

### Purpose

The Team/User review exposed a broader class of failure:

```text
user performs an action
↓
frontend sends or emits a mutation
↓
backend or component rejects/ignores it
↓
the UI shows no useful result
```

Phase 30 must perform a repository-wide audit of current user-triggered mutations rather than fixing only the discovered Team example.

### Mutation inventory

Inventory current user-triggered mutation paths, including as applicable:

- Inertia `useForm`;
- direct `router.post`;
- `router.patch`;
- `router.put`;
- `router.delete`;
- shared Action components;
- confirmation dialogs;
- destructive modals;
- child-component emitted mutation events;
- Admin high-risk operations;
- current module mutation workflows.

The audit covers current shipped routes and shared mutation primitives.

### No silent mutation failure

Every current user-triggered mutation must have an observable product outcome.

When a mutation fails because of:

- validation;
- domain blocker;
- authorization;
- stale optimistic version;
- conflict;
- rejected precondition;
- other expected application failure;

the user must receive visible, localized feedback.

No mutation may intentionally rely on:

`the request failed in DevTools`

as its only feedback.

### Field vs operation errors

Use the most local useful error presentation.

Field validation belongs beside the relevant field where practical.

Operation/domain errors belong in the relevant form/modal/action context.

Do not turn every validation error into an unrelated global toast.

Existing centralized handling remains appropriate for genuine network/session/system failures.

### Dead action audit

Find controls that:

- emit an event with no listener;
- call a missing handler;
- look enabled while their mutation is unsupported;
- are editable even though no Save path exists;
- disappear without explaining a rejected action.

Remove or repair them according to the actual canonical workflow.

Do not leave inert product controls.

### Flash-message audit

Audit current Atlas flash-message production and rendering.

Check:

- backend `FlashMessage` creation;
- redirect/session transport;
- Inertia shared props;
- layout rendering;
- current toast/flash ownership;
- direct router mutation behavior;
- partial reload behavior;
- redirect chains;
- modal workflows.

Where a current product action promises success feedback, verify that the expected flash message is actually rendered to the user.

Check for:

- expected flash never appearing;
- flash disappearing during redirect/partial reload;
- duplicate success messages;
- duplicate flash plus competing toast;
- stale flash replaying on a later request;
- success message rendered after rejected mutation;
- untranslated flash key;
- unresolved interpolation placeholder;
- incorrect severity;
- wrong operation message.

Do not add a success flash to every tiny interaction merely for consistency.

Preserve the existing product distinction between:

- local validation/domain errors;
- operation feedback;
- global flash/toast success;
- system/network errors.

### Mutation feedback map

Create or update durable canonical testing/architecture documentation containing a practical mutation-feedback map for current product surfaces.

The map should make it possible to verify:

- mutation owner;
- success feedback;
- expected validation/domain error surface;
- whether flash is expected;
- browser-level coverage where UI behavior matters.

Do not create a giant bureaucratic document disconnected from tests.

### Tasks

- [ ] Inventory current user-triggered mutation entry points.
- [ ] Audit `useForm` mutation error rendering.
- [ ] Audit direct Inertia router mutations.
- [ ] Audit emitted component mutation events and listeners.
- [ ] Audit shared Action/confirmation mutation flows.
- [ ] Audit expected validation-error visibility.
- [ ] Audit expected domain-blocker visibility.
- [ ] Audit stale/conflict feedback.
- [ ] Find and remove or repair dead mutation controls.
- [ ] Inventory current backend FlashMessage producers.
- [ ] Verify flash transport through redirect and Inertia shared props.
- [ ] Verify expected flash rendering in the shell.
- [ ] Check partial reload and preserve-state/scroll mutation behavior.
- [ ] Remove duplicate flash/toast feedback for the same terminal action.
- [ ] Prevent stale/replayed success feedback.
- [ ] Prevent success feedback after rejected mutations.
- [ ] Verify localized flash keys and interpolation.
- [ ] Create/update the durable mutation-feedback coverage map.
- [ ] Add permanent tests for the shared mutation/flash contracts.
- [ ] Add representative browser tests proving failed mutations are not silent.

---

## P30-W07 — Localization, interaction polish, and permanent guardrails

### Product language

Repair the discovered authorization/Team presentation details as part of this phase.

At minimum:

- no unresolved `:source`;
- no `Original source` wording;
- no provenance-snapshot/divergence explanation in ordinary authorization UI;
- no ambiguous `0 / 20` assignment summary;
- no duplicate assignment name immediately after expansion;
- visible expand/collapse affordance;
- localized human-readable authorization labels;
- localized Team Structure role/action/modal copy.

### Current-state UI principle

Operational/editing UI should primarily show the state that is true now.

Historical operational facts belong in:

- Audit;
- explicit history/detail views where the user intentionally asks for history.

Do not expose persistence terminology such as:

- provenance snapshot;
- divergence;
- original-source snapshot

in ordinary editing copy.

### Localization interpolation guard

Existing missing-key protection is not enough if a valid translated string renders with an unresolved replacement token.

Add permanent protection for relevant Atlas interpolation contracts.

The guard must catch the concrete class of defect that allowed `:source` to reach rendered UI.

Do not use a broad regex that incorrectly flags ordinary URLs, times, or technical diagnostics.

Test the translation/interpolation mechanism and representative rendered surfaces directly.

### Accordion/expandable interaction contract

Shared expandable assignment/member rows must:

- visibly indicate expandability;
- expose correct `aria-expanded`;
- support keyboard activation;
- preserve focus;
- avoid duplicated heading identity inside expanded content;
- remain usable in light/dark themes and mobile layouts.

### Tasks

- [ ] Remove unresolved authorization interpolation.
- [ ] Replace historical provenance wording with current-state wording.
- [ ] Replace ambiguous assignment-count summaries.
- [ ] Remove duplicated expanded assignment identity.
- [ ] Add canonical expand/collapse affordances.
- [ ] Localize human-readable authorization labels.
- [ ] Localize Employee / Manager / Head Manager structure UI.
- [ ] Add interpolation regression tests.
- [ ] Add expandable-control accessibility tests.
- [ ] Verify Polish and English rendered copy.
- [ ] Verify light and dark rendering.
- [ ] Verify responsive/mobile rendering.
- [ ] Update UI glossary only where the new accepted terminology requires it.

---

## P30-W08 — Browser acceptance and final regression closure

### Team Edit browser acceptance

Verify:

- existing member assignments are visible;
- authorization controls are clearly read-only;
- role checkboxes are disabled;
- direct-permission checkboxes are disabled;
- policy fields are disabled;
- no member authorization Save button is shown;
- no dead Remove-access control is shown;
- current Source renders correctly;
- no unresolved `:source`;
- collapsed summary is explicit;
- expanded assignment does not duplicate the member name;
- chevron reflects expanded/collapsed state.

### User Edit authorization browser acceptance

Verify:

- roles remain editable;
- ordinary direct grants remain editable;
- permissions granted through roles appear checked and disabled in the direct-permission selector;
- granting role source is visible;
- role-derived permissions are not accidentally persisted as new direct grants;
- overlapping real direct grants are preserved;
- removing a role immediately recomputes direct/effective state;
- effective preview remains truthful;
- manual change converts current Source to Manual;
- successful mutation provides the expected visible feedback.

### Membership-removal browser acceptance

Verify:

- removal succeeds when allowed;
- reason is required;
- expected success feedback appears;
- blocked removal displays the actual localized domain reason;
- rejected state remains unchanged;
- user never experiences a silent failed click.

### Team Structure browser acceptance

Verify the new primary layout:

- Head Managers section;
- Managers section;
- Employees section.

Verify structural-role transitions:

- Employee → Manager;
- Employee → Head Manager;
- Manager → Employee;
- Manager → Head Manager;
- Head Manager → Manager where permitted;
- Head Manager → Employee where permitted;
- protected last Head Manager rejection.

Verify transition impact behavior.

Verify Head Manager semantics:

- whole-Team scope;
- no explicit direct reports;
- cannot be assigned under Manager;
- cannot own normal Manager relationship edges.

Verify Manager semantics:

- Manager may exist with zero direct reports;
- Manager may manage Employee;
- Manager may manage Manager;
- one subordinate may have multiple Managers.

### Drag-and-drop acceptance

Using real browser interaction where reliable:

- drag Employee onto Manager;
- create relationship;
- drag same Employee onto second Manager;
- preserve first relationship;
- drag Manager onto another Manager;
- reject duplicate;
- reject self;
- reject cycle;
- reject Head Manager edge participation.

Also verify the keyboard/mobile alternative invokes the same backend contract.

### Destructive relationship workflow

Verify:

- relationship removal action;
- reason modal;
- successful end;
- history preserved;
- expected success feedback;
- rejected mutation feedback.

### Global mutation-feedback acceptance

Use the mutation inventory to ensure every current mutation has meaningful lower-level coverage and browser coverage where rendered behavior matters.

At minimum include representative workflows from multiple current areas rather than testing only Teams.

Verify:

- visible validation failure;
- visible domain blocker;
- visible stale/conflict result;
- successful flash delivery;
- no duplicate terminal feedback;
- no dead rendered action in covered surfaces.

### Browser quality

New/changed browser workflows must also protect:

- no unexpected console errors;
- no uncaught runtime errors;
- no unexpected failed asset/API requests;
- Polish and English copy;
- no raw translation keys;
- no unresolved interpolation placeholders;
- light and dark themes;
- critical mobile behavior;
- keyboard/focus behavior.

### Documentation closure

When implementation is complete, update canonical current-state documentation to reflect the new behavior.

In particular update:

- Authorization;
- Teams and manager hierarchy;
- frontend/UI documentation where affected;
- UI glossary;
- mutation/network/browser documentation where affected;
- testing documentation.

Do not leave the old provenance/head-manager/reparent-centric contract described as current behavior after this phase is complete.

Historical Phase 17/28/29 documents remain historical.

### Tasks

- [ ] Add Team Edit read-only Playwright acceptance.
- [ ] Add User Edit role-derived permission Playwright acceptance.
- [ ] Add current-source/manual-edit Playwright acceptance.
- [ ] Add successful membership-removal Playwright acceptance.
- [ ] Add blocked membership-removal visible-error acceptance.
- [ ] Add Team Structure three-section layout acceptance.
- [ ] Add structural-role transition acceptance.
- [ ] Add Head Manager whole-Team-scope acceptance.
- [ ] Add Manager multi-parent/multi-report acceptance.
- [ ] Add drag-and-drop relationship acceptance.
- [ ] Add keyboard/mobile relationship-assignment acceptance.
- [ ] Add relationship-removal modal acceptance.
- [ ] Add cycle/self/duplicate/stale rejection coverage.
- [ ] Add representative cross-application mutation-feedback browser coverage.
- [ ] Add flash-message rendering and non-duplication coverage.
- [ ] Run targeted backend tests.
- [ ] Run targeted frontend tests.
- [ ] Run Chromium Playwright coverage.
- [ ] Run Firefox Playwright coverage.
- [ ] Run the complete required foundation quality gate.
- [ ] Update all affected canonical current-state documentation.
- [ ] Remove obsolete UI/contracts/code superseded by this phase.
- [ ] Confirm Phase 31 Chat has not started before Phase 30 closure.

---

## Explicit accepted decisions

The following decisions are binding:

1. User Edit is the canonical authorization-mutation surface.
2. Team Edit authorization is read-only.
3. Team Structure is the canonical team-context membership/hierarchy mutation surface.
4. There is no explicit permission deny model.
5. Effective permissions are role grants plus direct grants.
6. Direct permissions only add access.
7. A permission granted through a role cannot be removed through the direct-permission checkbox.
8. Role-derived permissions appear checked and disabled in the direct-permission selector.
9. The granting role or roles are visible.
10. Role-derived UI state does not automatically create a direct grant.
11. Existing real overlapping direct grants are preserved.
12. Assignment `Source` describes current state.
13. Do not show `Original source`.
14. Do not show provenance divergence/snapshot wording in the normal edit UI.
15. A manual authorization edit changes current Source to Manual.
16. Historical provenance belongs in Audit.
17. Team Edit does not show Save/remove controls for member assignments.
18. Team Edit member assignment controls are visibly disabled/read-only.
19. `0 / 20`-style ambiguous summaries are removed.
20. Duplicate member names in expanded assignment content are removed.
21. Expandable rows have a visible chevron/affordance.
22. Team Structure has three primary sections: Head Managers, Managers, Employees.
23. Structural role is separate from Authorization role.
24. A Manager may have zero direct reports.
25. A subordinate may have multiple Managers.
26. A Manager may report to multiple Managers.
27. A Manager may manage another Manager.
28. Employee cannot own direct-report edges.
29. Head Manager manages the whole Team.
30. Head Manager has no personal direct-report relationship edges.
31. Head Manager cannot report to a Manager.
32. Head Manager cannot be a normal Manager relationship target/source.
33. Manager → Head Manager ends all active incoming and outgoing Manager edges atomically.
34. Employee → Head Manager ends active incoming Manager edges atomically.
35. Manager → Employee ends outgoing Manager edges but may preserve valid incoming Manager edges.
36. Demoting Head Manager does not restore old relationships automatically.
37. Existing last-required-Head-Manager protection remains.
38. Existing DAG/self/cycle/stale/process validation remains.
39. Desktop relationship assignment primarily uses drag and drop.
40. Dragging onto a Manager ADDS a Manager relationship.
41. Dragging does not remove other Manager relationships.
42. Head Manager is not a normal drag/drop relationship target.
43. Drag and drop uses the canonical backend relationship mutation.
44. Keyboard/mobile users have an equivalent non-drag workflow.
45. Relationship removal uses a focused modal with mandatory reason.
46. Membership removal uses a focused modal with mandatory reason.
47. Permanent inline destructive-reason inputs are removed from the primary structure UI.
48. Backend domain errors must be visibly rendered.
49. No user-triggered mutation may fail silently.
50. Dead buttons/events without working handlers must be removed or repaired.
51. Flash-message delivery is audited repository-wide.
52. Expected success flash messages must actually render.
53. Duplicate/stale/incorrect flash messages must be repaired.
54. Local validation/domain errors must not be replaced indiscriminately with global toasts.
55. `:source` and equivalent unresolved interpolation must not reach normal rendered UI.
56. Current-state editing UI does not expose persistence/audit jargon.
57. Human-readable authorization labels follow the active locale.
58. Historical Phase 29 remains complete and is not rewritten.
59. Chat remains not started until this phase is complete.

---

## Permanent guardrails

- [ ] Team Edit cannot mutate member authorization.
- [ ] Team Edit cannot mutate Team membership.
- [x] User Edit remains capable of role/direct-permission mutation.
- [x] Role-derived permissions cannot be toggled off through direct grants.
- [x] No explicit deny permission layer is introduced.
- [x] Role-derived UI state cannot accidentally become a persisted direct grant.
- [x] Existing overlapping direct grants are not silently destroyed.
- [x] Current Source becomes Manual after manual edit.
- [x] Normal authorization UI does not expose original-source divergence wording.
- [x] Normal rendered UI cannot expose unresolved `:source`.
- [ ] Team Structure owns team-context membership mutation.
- [ ] Every active member has exactly one structural role.
- [ ] Head Managers cannot participate in normal Manager relationship edges.
- [ ] Head Manager scope is the whole active Team.
- [ ] Employees cannot own direct reports.
- [ ] Managers may have multiple reports and multiple Managers.
- [ ] Manager hierarchy remains acyclic.
- [ ] Structural-role transitions are atomic.
- [ ] Last-required-Head-Manager protection remains.
- [ ] Drag/drop assignment adds rather than replaces Manager relationships.
- [ ] Drag/drop cannot bypass backend validation.
- [ ] Keyboard/mobile relationship assignment uses the same business use case.
- [ ] Destructive relationship/membership actions require reasons.
- [ ] Mutation domain errors are visible.
- [ ] No rendered mutation control is intentionally inert.
- [ ] Expected flash success is delivered exactly through the canonical feedback owner.
- [ ] Rejected mutations cannot render success feedback.
- [ ] Flash/toast ownership does not duplicate terminal feedback.
- [ ] PL/EN, light/dark, keyboard, mobile, console, and request-quality guardrails remain intact.

---

## Completion criteria

Phase 30 is complete only when:

- [x] authorization semantics remain role grants plus direct grants with no deny model;
- [x] role-derived permissions are clearly checked/disabled and source-labelled;
- [x] current assignment Source semantics are implemented;
- [x] historical provenance is removed from the ordinary editing presentation;
- [x] unresolved `:source` is permanently prevented;
- [ ] Team Edit member authorization is clearly read-only;
- [ ] Team Edit contains no dead Save/remove member controls;
- [ ] assignment summaries and expansion affordances are understandable;
- [ ] Team Structure uses explicit Employee / Manager / Head Manager roles;
- [ ] Head Manager scope is the whole Team;
- [ ] Head Managers have no normal direct-report relationship edges;
- [ ] Managers can have zero direct reports;
- [ ] multiple Managers per subordinate work;
- [ ] Manager-to-Manager relationships work without cycles;
- [ ] structural-role transitions and automatic relationship cleanup are atomic;
- [ ] Team Structure primary layout is Head Managers / Managers / Employees;
- [ ] desktop drag-and-drop relationship assignment works;
- [ ] accessible/mobile relationship assignment works;
- [ ] relationship and membership removal use focused reason modals;
- [ ] backend blockers are visibly rendered;
- [ ] current repository mutations have been audited for silent failures;
- [ ] dead mutation controls have been removed or repaired;
- [ ] flash-message production, transport, and rendering have been audited;
- [ ] expected success messages render without duplication/stale replay;
- [ ] relevant Polish and English UI is complete;
- [ ] relevant light/dark/mobile/keyboard behavior is protected;
- [ ] relevant PHPUnit/Vitest/Playwright tests pass;
- [ ] the complete required foundation quality gate passes;
- [ ] canonical current-state Authorization and Teams documentation reflects the new contract;
- [ ] historical completed-phase documentation remains historically accurate;
- [ ] Phase 31 Chat remains `not started` until this phase is complete.
