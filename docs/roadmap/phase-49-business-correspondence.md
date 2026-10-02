# Phase 49 — Optional business correspondence and delivery tracking

**Status:** `not started`

## Objective

Add an Optional Business Correspondence capability for official outbound communication to external recipients.

Business Correspondence is distinct from Notifications.

Notifications are internal Atlas user notifications such as:

`You have a new Task.`

Business Correspondence represents business communication such as:

`An official email or letter was sent to an external recipient.`

Support baseline channels:

- email;
- postal/provider-based correspondence.

The architecture must allow additional provider adapters without binding business modules to a vendor.

## Dependencies

- Phase 37 contact/address primitives and business numbering.
- Phase 44 DocumentGeneration.
- Files.
- Integrations.
- Managed Processes/queues/scheduler.
- Audit.
- Authorization.
- Settings.
- ModuleGate.
- Notifications for internal operational/user alerts.
- privacy/retention.

## Ownership

Create an Optional `Correspondence` module.

It owns:

- correspondence record;
- channel;
- recipient delivery snapshot;
- Atlas correspondence number;
- template/generation references;
- outgoing Files attachment references;
- submission lifecycle;
- external provider references;
- canonical delivery status;
- provider-status history;
- retries;
- tracking polling state;
- callback/webhook correlation;
- retention metadata.

It does not own Customer/Person/Company/Contractor/contact master data.

## Recipient snapshot

The source business module supplies an authorized recipient snapshot using Phase 37 contact/address primitives.

Correspondence stores only the delivery information required for historical evidence/retry/tracking.

The source business domain remains owner of its contact data.

Changing a source address later must not rewrite the historical destination of already submitted correspondence.

## Templates

All business email and document templates use Phase 44 DocumentGeneration.

Do not create a separate Correspondence email-template engine.

Email templates include versioned:

- subject;
- text body;
- HTML body.

Postal/attached documents use versioned generated artifacts.

## Atlas business number

Each correspondence may receive an immutable Atlas business-facing number through Phase 37 sequence infrastructure.

Example format is configuration/sequence-owned, not hardcoded.

The Atlas correspondence number:

- is distinct from internal ID/public ULID;
- may be used as a merge field in generated content;
- may be supplied to an external provider as a client reference;
- is searchable;
- is immutable after issuance.

Do not create a second numbering engine.

## Number-before-render ordering

When the outgoing document/email must contain the correspondence number:

1. create/idempotently identify the correspondence request;
2. issue/reserve the canonical immutable number through the sequence contract;
3. generate final content using that number;
4. submit to the external provider.

Retrying the same logical request must not issue a second number.

## External provider references

Store separately:

- Atlas correspondence number;
- provider submission ID;
- postal tracking number;
- message/provider ID;
- other provider reference.

A provider reference never replaces Atlas identity.

Support multiple external references where a provider lifecycle requires them.

## Canonical lifecycle

Use a bounded code-owned lifecycle such as:

- `draft`;
- `generated`;
- `queued`;
- `submitted`;
- `sent`;
- `delivered`;
- `failed`;
- `returned`;
- `cancelled`;

with channel-specific applicability documented.

Do not expose raw provider status as the canonical business lifecycle.

Preserve raw/safe provider status metadata/history separately when useful.

## Provider-neutral submission

Provider adapters may include:

- existing Atlas mail infrastructure;
- external email provider;
- postal operator API;
- courier/document delivery API;
- company gateway;
- another registered service.

Business modules depend only on Correspondence public contracts.

## Delivery tracking

Support both:

### Polling

A provider adapter may query external status by provider reference/tracking number.

Use scheduler/Managed Processes.

Define retry/backoff/terminal behavior.

### Callback/webhook

A provider may send status callbacks/webhooks.

Validate:

- provider identity/signature/authentication;
- replay/idempotency;
- reference correlation;
- allowed status mapping.

Reuse existing Integration Event/webhook/security primitives where appropriate.

Do not build a second generic webhook platform.

## Provider status mapping

Each adapter maps provider-specific statuses into canonical Correspondence lifecycle/events.

Unknown provider statuses:

- are recorded safely;
- do not silently map to delivered/success;
- surface operationally for adapter maintenance.

## Attachments

Attachments/final documents are Files-owned.

Correspondence stores safe references.

Do not copy file bytes into correspondence tables.

## Retries

Retry behavior must distinguish:

- generation failure;
- provider submission failure before confirmed acceptance;
- uncertain submission result;
- delivery failure;
- tracking failure.

Never resend blindly when provider acceptance is uncertain.

Use idempotency/provider idempotency keys where supported.

## Audit

Audit:

- creation;
- number issuance association;
- generation;
- submission;
- retry;
- cancellation;
- material provider status transitions;
- delivery/return/failure.

Do not put private body/attachment content into Audit.

## Notifications

Internal Atlas Notifications may inform authorized Users of:

- failed correspondence;
- returned correspondence;
- terminal generation/provider failure;
- selected completion events.

Notifications are not the correspondence record itself.

## Search

Allow authorized Search by:

- Atlas correspondence number;
- safe recipient presentation where allowed;
- external tracking/reference;
- source resource reference;
- canonical status.

Do not expose recipient/contact data outside source/correspondence authorization.

## Privacy/retention

Correspondence may be long-lived business evidence.

Retention is explicit and may differ by source business module/channel.

Deleting source records does not automatically falsify required legal correspondence history.

Use the shared privacy/legal-hold lifecycle.

Attachments remain Files-owned.

## Module activation

Correspondence is Optional.

DocumentGeneration is a required dependency for template-backed baseline Correspondence.

Required dependency deactivation is blocked while Correspondence is active.

Provider configuration is optional per channel; unavailable providers disable only the affected configured path unless the deployment marks it required.

## Operational visibility

Expose safe provider:

- availability;
- queue depth;
- failed submission count;
- tracking lag;
- callback failure;
- unknown-status count.

Do not expose private correspondence bodies/attachments in Health/Admin diagnostics.

## UI

Provide authorized:

- correspondence list;
- detail;
- draft/generation state;
- provider submission state;
- delivery history;
- tracking references;
- retries where safe.

PL/EN, responsive, light/dark required.

## Explicit non-goals

- internal Notifications replacement;
- central Customer/Person/Company;
- contact master ownership;
- separate template engine;
- separate sequence engine;
- separate job/process platform;
- arbitrary provider workflow rules;
- hidden automatic resending after uncertain provider acceptance.

## Tasks

### P49-W01 — Module/lifecycle/public contracts

- [ ] Create Optional Correspondence module, schema, permissions, module metadata, public contracts, and docs.
- [ ] Implement canonical lifecycle and recipient snapshot.

### P49-W02 — Numbering and DocumentGeneration

- [ ] Integrate Phase 37 sequences.
- [ ] Guarantee idempotent one-number issuance.
- [ ] Integrate Phase 44 templates for email subject/body and documents.
- [ ] Ensure number is available before final render where required.

### P49-W03 — Email provider path

- [ ] Implement provider-neutral email submission.
- [ ] Reuse current mail/runtime foundations where appropriate.
- [ ] Add provider IDs, idempotency, retries, and failure states.

### P49-W04 — Postal/external provider contract

- [ ] Implement generic provider adapter interface.
- [ ] Support submission/tracking references.
- [ ] Add deterministic fake postal/delivery provider.

### P49-W05 — Tracking polling and callbacks

- [ ] Implement scheduler/Managed Process polling.
- [ ] Implement secure idempotent provider callback/webhook handling using existing integration foundations.
- [ ] Implement provider-to-canonical status mapping and unknown-status handling.

### P49-W06 — Files, Audit, Search, Notifications, retention

- [ ] Integrate Files-owned attachments/artifacts.
- [ ] Add Audit and internal Notifications.
- [ ] Add authorization-safe Search.
- [ ] Add privacy/legal-hold/retention lifecycle.

### P49-W07 — UI, operational visibility, activation, docs

- [ ] Implement list/detail/tracking/retry UI.
- [ ] Add provider Health/metrics without content leakage.
- [ ] Add PL/EN/light/dark/browser coverage.
- [ ] Test activation/dependency/provider-outage/uncertain-submit behavior.
- [ ] Update canonical Integrations/Files/DocumentGeneration/operations/privacy/testing docs.

## Completion criteria

- [ ] Correspondence is distinct from Notifications.
- [ ] Email and postal content reuse DocumentGeneration.
- [ ] Atlas correspondence numbers are immutable and separate from provider tracking IDs.
- [ ] External tracking supports polling and secure callbacks where providers support them.
- [ ] Provider status is normalized without hiding unknown states.
- [ ] Retry cannot create duplicate sends/numbers under uncertain outcomes.
- [ ] Contact ownership remains with business modules.
- [ ] `WORKROAD.md` status is `complete`.
