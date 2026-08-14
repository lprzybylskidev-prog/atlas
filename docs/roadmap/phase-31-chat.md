# Phase 31 — Optional internal company chat, calendar, calls, meetings, and realtime communication

**Status:** `in progress` (`P31-W01` through `P31-W05` complete; `P31-W06` is next)

## Objective

Implement Atlas-owned internal company communication covering:

- direct messaging;
- group messaging;
- system Team messaging;
- voice messages;
- realtime presence and unread state;
- direct audio/video calls;
- group audio/video calls;
- Team audio/video calls;
- screen sharing;
- a shared Core Calendar capability;
- immediate and scheduled Meetings;
- recurring Meetings;
- explicit online, in-person, and hybrid Meeting modes;
- first-class physical Meeting location where applicable;
- Meeting invitations and RTC-derived attendance without false physical-presence inference;
- persistent Meeting chat;
- Meeting moderation;
- Meeting recording;
- recording retention and sharing;
- a provider-neutral future transcription boundary;
- authorization-safe Search;
- browser-native alerts;
- participant-authorized exports;
- operational visibility without privileged access to private communication content.

Atlas communication remains an internal company capability.

It is not:

- a public messaging product;
- a social network;
- a business-event bus;
- a generic business-object conversation framework;
- a public video-conferencing service;
- a bot platform;
- a replacement for Notifications;
- an external guest-conferencing system.

System and business-module events continue to use the existing Notifications module.

The known initial deployment is approximately 400 company users with expected future growth.

Do not impose an artificial product-wide 400-user or Meeting-participant limit.

Do not overengineer for public Internet-scale social-network traffic.

## Dependencies

Phase 31 depends on the completed Atlas foundations, including:

- modular architecture and public contracts;
- Authorization;
- Teams;
- PostgreSQL module schemas;
- Audit;
- Settings and Localization;
- Sessions and active Team;
- Module availability and activation;
- Notifications and realtime;
- Admin operations and health;
- Files and ClamAV;
- Search and Meilisearch;
- shared frontend/UI;
- export/PDF foundations;
- queues and scheduler;
- Managed Processes;
- Phase 28 foundation repair;
- Phase 29 foundation acceptance closure;
- completed Phase 30 Authorization, Team Structure, and mutation feedback repair.

Phase 31 must not depend on TimeTracking or future debt-collection business modules.

## Related documentation

Maintain canonical current-state documentation under the existing conventions.

At completion update as applicable:

- Chat module documentation;
- Calendar documentation;
- realtime/network/browser operations;
- Files;
- Search;
- Notifications;
- Authorization;
- Audit;
- Settings/Integrations;
- production/runtime documentation;
- testing documentation.

Do not duplicate existing cross-cutting contracts unnecessarily.

## Fundamental implementation boundary

Atlas owns:

- conversations;
- messages;
- groups;
- Team Chat;
- Calls;
- Meeting definitions;
- invitations;
- recurring Meeting rules;
- Calendar composition;
- Meeting chat;
- RTC authorization;
- recording lifecycle metadata;
- recording access grants;
- transcription lifecycle metadata;
- permissions;
- retention;
- Search projections;
- privacy;
- Audit;
- frontend product UX.

Every Meeting has exactly one canonical mode:

- `online`;
- `in_person`;
- `hybrid`.

A Meeting is an Atlas business object whose identity and lifecycle do not depend on a LiveKit room. Atlas owns its title, description, schedule, recurrence, organizer, invitations, RSVP, reminders, Calendar contribution, Meeting chat, location, mode, and history. LiveKit provides only the optional live-media capability of an RTC-enabled `online` or `hybrid` Meeting.

An `in_person` Meeting remains a full Meeting without an Atlas RTC session. A `hybrid` Meeting is one Meeting combining a physical location with an optional Atlas RTC session; it is not split into physical and online records.

Do not use a ready-made Chat product/framework such as:

- Wirechat;
- Chatify;
- hosted Chat SaaS;
- third-party Chat backend;
- third-party Chat UI product.

Wirechat may remain only a historical feature-comparison reference.

Laravel Reverb and Laravel broadcasting remain the canonical Atlas server-push foundation for messaging/application events.

Audio/video media uses self-hosted LiveKit.

LiveKit is RTC infrastructure, not the Chat domain.

Do not build a custom WebRTC/SFU server.

Do not require LiveKit Cloud.

The baseline implementation is Atlas-managed self-hosted LiveKit.

Do not implement a baseline option to connect an already-existing external LiveKit installation.

Self-hosted LiveKit Egress is the canonical Meeting-recording engine.

Normal direct/group/Team ad-hoc Calls are not recordable.

Only RTC-enabled `online` and `hybrid` Meetings may be recorded. Pure `in_person` Meetings cannot invoke Atlas/LiveKit recording.

---

## Execution discipline

Workstreams are strictly sequential:

1. `P31-W01` — Module boundaries, persistence, permissions, and cross-cutting contracts
2. `P31-W02` — Shared Core Calendar foundation
3. `P31-W03` — Direct, group, Team, and Meeting conversation ownership
4. `P31-W04` — Message behavior, editing, reactions, forwarding, pins, bookmarks, and drafts
5. `P31-W05` — Files, attachments, content viewer, and voice messages
6. `P31-W06` — Reverb messaging realtime, presence, typing, delivery, read, and unread state
7. `P31-W07` — Self-hosted LiveKit RTC and development runtime foundation
8. `P31-W08` — Direct/group/Team ad-hoc Calls and Call history
9. `P31-W09` — Meeting scheduling, invitations, recurrence, Meeting chat, and Calendar composition
10. `P31-W10` — RTC-enabled Meeting session, moderation, devices, screen sharing, and lifecycle
11. `P31-W11` — Meeting recording, Egress, Files ownership, retention, and controlled sharing
12. `P31-W12` — Provider-neutral asynchronous transcription boundary
13. `P31-W13` — Shell/modal UX, Calendar UI, notifications, reminders, and preferences
14. `P31-W14` — Search and Meilisearch
15. `P31-W15` — Retention, privacy, Admin operations, Audit, and exports
16. `P31-W16` — Scale, performance, browser workflows, and final acceptance closure

Only the earliest incomplete workstream is active.

Do not start a later workstream while any mandatory work remains in an earlier workstream.

Do not select work based on ease, speed, convenience, or file locality.

Do not create endless suffix packages merely to report progress.

Preparation or partial implementation does not count as workstream completion.

A workstream is complete only when:

- implementation;
- tests;
- documentation;
- legacy cleanup;
- permanent guardrails;
- browser acceptance where required

are all complete.

Do not calculate completion percentages or readiness percentages.

---

## P31-W01 — Module boundaries, persistence, permissions, and cross-cutting contracts

### Chat module

Chat is an optional Atlas module governed by canonical Module Availability, ModuleGate, and Authorization foundations.

Global Chat deactivation must:

- hide Chat/Call/Meeting launchers;
- block Chat HTTP/application actions;
- block Chat realtime channels;
- block starting/joining Calls and Meetings;
- preserve historical Chat/Meeting data.

Normal direct and user-created group conversations are global to the Atlas user account.

They are not scoped by the current active Team.

System Team conversations are Team-scoped.

Meeting conversations are scoped by Meeting invitation/membership, not by the user's current active Team.

### Calendar boundary

Phase 31 introduces a shared Core Calendar capability.

Calendar is not owned by Chat.

It must be reusable by future Atlas modules through a narrow public contract.

Personal Calendar events remain useful independently from Chat messaging.

Meetings contribute Calendar events through the canonical Calendar boundary.

Do not build a second Meeting-only calendar engine.

### Persistence

Chat/Calls/Meetings own their persistence in the appropriate Chat-owned PostgreSQL schema.

Calendar owns its persistence according to the existing Core schema/module ownership conventions.

Do not query another module's persistence directly.

Use owner-owned public contracts.

### Authorization

All protected operations use the normal Atlas route/use-case permission model.

Add granular capabilities for at least:

- use Chat;
- start direct conversation;
- create group;
- upload Chat attachment;
- send voice message;
- start/join audio/video Calls;
- share screen;
- create Meetings;
- invite participants;
- moderate Meetings;
- manage Meeting recording;
- view recordings;
- download recordings;
- share recordings;
- request transcription;
- view transcription;
- edit transcription;
- share transcription;
- Calendar use;
- Calendar event mutation;
- Chat operational Admin access;
- Chat retention administration;
- recording retention administration.

Follow canonical route-permission naming rather than creating role-name checks.

Starter roles should normally allow ordinary employees to use the standard internal communication capabilities where product permissions allow.

Do not create an application permission equivalent to:

`chat.admin.read`

that grants Administrators general access to private communication content.

Admin status alone must never grant private content access.

### Tasks

- [x] Preserve the optional Chat module boundary.
- [x] Define the shared Core Calendar boundary.
- [x] Define Chat/Call/Meeting persistence ownership.
- [x] Define Calendar persistence ownership.
- [x] Add only required public contracts.
- [x] Add the expanded permission catalog.
- [x] Add appropriate starter-role capabilities.
- [x] Gate Chat/Call/Meeting UI and application actions.
- [x] Preserve global direct/group scope.
- [x] Preserve Team-scoped Team conversation behavior.
- [x] Define Meeting invitation-based scope.
- [x] Add architecture/module-boundary guardrails.
- [x] Update planning/current documentation boundaries without duplicating module internals.

---

## P31-W02 — Shared Core Calendar foundation

### Calendar purpose

Create one shared Atlas Calendar capability.

It supports:

- personal events;
- personal reminders;
- future module-owned Calendar contributions;
- Meeting events.

Do not build Calendar as a Chat-only widget.

Do not create a task-management product.

Personal Calendar items do not have a `completed` task state.

### Personal events

A personal event belongs to exactly one user.

It is private through the Atlas application.

Administrator status does not grant access to another user's private personal-event details.

Support:

- title;
- description;
- start;
- end;
- all-day;
- text location;
- recurrence;
- reminders;
- Free/Busy availability state.

Do not add:

- colors;
- categories;
- invited participants to ordinary personal events;
- public sharing.

Invitations belong to Meetings, not ordinary personal Calendar events.

### Calendar timezone

Use the existing canonical Atlas timezone behavior.

The accepted system timezone is:

`Europe/Warsaw`

Do not introduce a new per-user timezone subsystem in this phase.

Recurring events must preserve the intended local Europe/Warsaw clock time through DST transitions.

### Views

Provide:

- Month;
- Week;
- Day;
- Agenda.

### Recurrence

Support recurring personal events and Meetings with useful rules including:

- daily;
- weekly;
- monthly;
- selected weekdays;
- end date;
- occurrence count.

Recurring edits support:

- this occurrence;
- this and future occurrences;
- entire series.

### Reminders

Default Meeting/Calendar reminder timing is:

15 minutes before.

The user profile may change the default.

A specific event/Meeting may override the user's default.

Allow multiple reminders on one event.

Personal Calendar reminders use existing Atlas notification delivery.

Allow the user to control whether personal Calendar reminder email delivery is enabled.

### Free/Busy

Personal events may be marked:

- Busy;
- Free.

Meeting scheduling may query another user's availability.

Free/Busy lookup must disclose only availability windows.

It must not expose another user's private:

- event title;
- description;
- location;
- reminder;
- other private event content.

Scheduling a Meeting warns about conflicts for each invited user.

Conflicts do not block Meeting creation.

### Tasks

- [x] Create the shared Core Calendar capability.
- [x] Add personal private Calendar events.
- [x] Add title/description/start/end/all-day/location.
- [x] Add recurrence.
- [x] Add multiple reminders.
- [x] Add user default reminder preference.
- [x] Add per-event reminder override.
- [x] Add Free/Busy state.
- [x] Add privacy-safe Free/Busy lookup.
- [x] Add conflict warnings without blocking scheduling.
- [x] Add Month / Week / Day / Agenda views.
- [x] Preserve Europe/Warsaw recurrence semantics.
- [x] Keep personal events participant-free.
- [x] Keep Calendar independent from Chat persistence.
- [x] Add Calendar authorization/privacy tests.
- [x] Add recurrence and DST regression tests.
- [x] Add Calendar browser coverage.

---

## P31-W03 — Direct, group, Team, and Meeting conversation ownership

### Conversation types

Chat supports four conversation types:

- `direct`;
- `group`;
- `team`;
- `meeting`.

Do not add generic business-object conversation contexts.

### Direct conversations

Every unordered pair of users has exactly one canonical direct conversation.

Creation must remain concurrency-safe.

Every active Atlas user may start a direct conversation with every other active Atlas user regardless of Team membership.

Do not implement:

- friends;
- message requests;
- approval before DM;
- blocking.

Inactive users cannot participate in new communication but historical data remains.

### Groups

A user-created group has:

- exactly one owner;
- members;
- name;
- optional avatar;
- historical membership.

Roles are exactly:

- owner;
- member.

No moderator/admin/custom group role.

The owner may:

- rename;
- change avatar;
- add members;
- remove members;
- transfer ownership.

No invitation acceptance is needed for normal Chat groups.

Members may leave.

Owner must transfer ownership before leaving while another active member remains.

Last owner/member may leave and close the group historically.

Newly added members may see existing available history from the beginning.

### Team conversations

Every Team with Chat active has one canonical Team conversation.

It is:

- system-owned;
- not manually deletable;
- not manually leaveable;
- membership-synchronized from Teams;
- not affected by Manager/Head Manager status;
- not a second Team administration surface.

### Meeting conversations

Every Meeting has a system-owned Meeting chat.

For a recurring Meeting series:

- the series has one shared Meeting conversation;
- all occurrences use the same chat;
- attendance and recordings remain occurrence-specific.

Meeting chat is available:

- before the live Meeting;
- during the Meeting;
- after the Meeting.

Invitation grants access to the Meeting chat according to the accepted Meeting access state.

A user who explicitly declines an invitation remains able to see the Meeting chat because the user was invited and may later change RSVP.

A removed invitee loses Meeting chat access.

A user kicked from a live Meeting loses active Meeting access including Meeting chat for that occurrence/access state.

Meeting chat history remains preserved.

### System timeline entries

Use immutable system entries for structural events where appropriate, including:

- group member added/removed/left;
- ownership transferred;
- group metadata changed;
- Team membership synchronized;
- group closed;
- Call started/ended/missed;
- Meeting scheduled;
- Meeting rescheduled;
- Meeting cancelled;
- participant invited/removed;
- recording started/paused/resumed/stopped.

System timeline entries are not Notifications persistence.

Do not put private message body content into structural entries.

### Tasks

- [x] Implement direct/group/team/meeting conversation models.
- [x] Enforce canonical DM uniqueness.
- [x] Preserve group owner/member lifecycle.
- [x] Preserve Team-owned membership synchronization.
- [x] Add system-owned Meeting conversations.
- [x] Keep one Meeting conversation per recurring series.
- [x] Apply invitation/removal access to Meeting chat.
- [x] Keep declined invitee Meeting-chat visibility.
- [x] Preserve conversation history.
- [x] Add structural timeline entries.
- [x] Add membership/concurrency/authorization tests.

---

## P31-W04 — Message behavior, editing, reactions, forwarding, pins, bookmarks, and drafts

### Core messages

Support:

- text;
- multiline;
- Unicode emoji;
- safe URLs;
- Markdown-lite;
- reply/quote;
- edit;
- edit history;
- delete-for-me;
- reactions;
- mentions;
- forwarding;
- pins;
- bookmarks;
- drafts.

### Markdown-lite

Support:

- bold;
- italic;
- inline code;
- code blocks;
- lists;
- block quotes;
- links;
- emoji.

Do not allow raw untrusted HTML.

Do not add a large rich-text framework unless genuinely required.

### Replies

One reply references one message.

Do not implement nested Slack-style threads.

### Editing

Authors may edit their own messages without an arbitrary time limit.

Show `edited`.

Preserve edit history.

Authorized conversation participants may inspect edit history.

Admin status alone never grants access.

Use optimistic concurrency/version protection.

### Delete for me

Normal users have only:

`Delete for me`

Do not add delete-for-everyone or Admin arbitrary message deletion.

Delete-for-me affects only that user's visibility.

Other participants retain the content.

The deleting user sees a tombstone.

Respect delete-for-me in:

- Search;
- exports;
- content viewers.

### Reactions

Support emoji reactions.

Keep uniqueness for user/message/emoji.

### Mentions

Support:

- individual mentions;
- `@everyone`;
- `@online`.

Every active participant may use group mentions.

Mentions remain Chat behavior.

They do not become email Chat messages.

### Forwarding

Forwarding creates a new destination message.

Never leak private source conversation metadata.

### Pins and bookmarks

Every active participant may pin/unpin.

Bookmarks are private per user.

### Drafts

Maintain one backend-owned draft per user/conversation.

Drafts survive navigation and closing the Chat modal.

Do not store company Chat drafts in `localStorage` or `sessionStorage`.

### Message idempotency

Prevent duplicate sends from:

- double-click;
- network retry;
- reconnect;
- frontend retry.

### Tasks

- [x] Implement canonical messages.
- [x] Add safe Markdown-lite.
- [x] Add replies.
- [x] Add editing and edit history.
- [x] Add optimistic message edit protection.
- [x] Add delete-for-me.
- [x] Add reactions.
- [x] Add mentions.
- [x] Add forwarding.
- [x] Add pins.
- [x] Add private bookmarks.
- [x] Add backend drafts.
- [x] Add send idempotency.
- [x] Add negative authorization tests.

---

## P31-W05 — Files, attachments, content viewer, and voice messages

### Files ownership

Chat must not create a separate storage subsystem.

All attachments use canonical Files contracts and policy.

Reuse:

- size limits;
- MIME/content rules;
- validation;
- authorization;
- quarantine;
- ClamAV;
- retention;
- download/preview.

### Upload UX

Support:

- picker;
- drag/drop;
- clipboard paste including screenshots;
- upload progress;
- scan/quarantine state;
- failure state;
- allowed retry.

### Content viewer

Conversation details provide:

- Media;
- Files;
- Links.

Use existing Files preview/download authorization.

Do not fetch external URL metadata.

No automatic external link previews.

### Voice messages

Voice messages remain in scope.

Maximum recording duration:

15 minutes.

UX:

1. start;
2. stop;
3. preview/listen;
4. send;
   or
5. discard/re-record.

Never auto-send when recording stops.

Voice messages:

- use explicit browser microphone permission;
- become Files-owned attachments;
- follow canonical Files/ClamAV lifecycle;
- follow Files limits;
- have accessible playback;
- follow conversation authorization;
- follow Chat retention.

Voice messages are not RTC Calls.

### Tasks

- [x] Integrate attachments through Files.
- [x] Preserve Files/ClamAV lifecycle.
- [x] Add picker/drop/clipboard upload.
- [x] Add upload/scan/failure states.
- [x] Add Media / Files / Links.
- [x] Add safe media preview.
- [x] Add 15-minute voice recording.
- [x] Add preview/send/discard/re-record.
- [x] Persist voice messages through Files.
- [x] Add attachment/scan authorization tests.
- [x] Add browser attachment/voice coverage.

---

## P31-W06 — Reverb messaging realtime, presence, typing, delivery, read, and unread state

### Realtime architecture

Continue using:

- Laravel broadcasting;
- Laravel Reverb

for Atlas message/application realtime.

Do not build a second Chat WebSocket architecture.

LiveKit later owns only RTC media.

### Source of truth

WebSockets are delivery, not persistence.

After reconnect, reconcile authoritative application state.

Do not lose or duplicate persisted messages.

### Channels

Authorize every private conversation channel.

Guessing an identifier never grants subscription.

### Presence

Support:

- online;
- offline;
- last seen.

Use bounded/coalesced persistence.

### Manual status

Support:

- Available;
- Busy;
- Do not disturb;
- Out of office;
- optional custom text/emoji.

These labels are informational.

DND does not suppress message persistence.

### Typing

Typing is ephemeral.

Do not persist or Audit typing.

Expire it automatically.

### Delivery/read

DM supports:

- sent;
- delivered;
- read.

Group/Team/Meeting chat supports viewing who read where appropriate.

Prefer per-membership cursor structures rather than user × message receipt explosion.

### Unread

Support:

- unread counts;
- new-message separator;
- last-read cursor;
- mark unread;
- realtime updates.

### Tasks

- [ ] Extend existing Reverb foundation for Chat.
- [ ] Add private/presence channels.
- [ ] Add reconnect reconciliation.
- [ ] Add presence.
- [ ] Add manual status.
- [ ] Add typing.
- [ ] Add delivery/read state.
- [ ] Add unread state.
- [ ] Add multi-context browser tests.
- [ ] Add channel-negative tests.
- [ ] Update realtime documentation.

---

## P31-W07 — Self-hosted LiveKit RTC and development runtime foundation

### RTC ownership

Use self-hosted LiveKit as the canonical RTC media infrastructure.

Atlas owns business/session authorization.

Meeting domain identity and lifecycle remain independent from RTC room existence. LiveKit rooms are created only for RTC-enabled `online` and `hybrid` Meetings. An `in_person` Meeting must never create or require a LiveKit room or participant token.

LiveKit carries:

- microphone audio;
- camera video;
- screen-share media.

Do not use LiveKit for normal Chat-message persistence.

### Runtime services

The canonical development/runtime stack must support:

- LiveKit server;
- LiveKit Egress.

They are sibling infrastructure services.

Do not install LiveKit inside the PHP workspace/container.

Egress is deployed separately from the LiveKit server.

Use canonical Redis connectivity required by the accepted LiveKit/Egress topology.

Production topology and installer work belongs to Phase 40, but Phase 31 must provide a reproducible development/test topology.

### Media gateway

Create a narrow Atlas-owned infrastructure boundary for operations such as:

- create/prepare RTC room;
- issue authorized short-lived participant access;
- remove participant;
- end room/session;
- start recording;
- stop recording;
- query required RTC/recording status.

One LiveKit infrastructure implementation is sufficient.

Do not build speculative multi-provider RTC abstraction complexity.

### Tokens/secrets

Browser clients must receive only short-lived access required for the authorized room.

Never expose:

- LiveKit API secret;
- infrastructure credentials

to the browser.

### TURN/TLS planning

RTC must work for trusted company users over:

- LAN;
- VPN;
- restrictive firewall environments where supported.

Plan self-hosted TURN/TLS as part of the production baseline.

Do not require public Internet exposure or public Let's Encrypt.

Internal/company certificates or existing company TLS infrastructure are valid.

### Health/failure isolation

Text Chat must continue working if RTC is unavailable.

RTC failures produce a clear user-facing unavailable/error state.

Meeting/Call errors must not take down message persistence, Meeting chat, Calendar/Meeting metadata, invitations, or RSVP.

When LiveKit or TURN is unavailable, `online` and `hybrid` live media is unavailable with a clear state, while `in_person` Meetings remain fully usable.

Egress failure must not end an active RTC-enabled Meeting and has no effect on an `in_person` Meeting.

If recording cannot start, show a clear recording error while the Meeting continues.

### Capacity

Do not impose an artificial participant-count product limit.

Infrastructure capacity may still reject or fail a session.

Such capacity failures must produce a clear user-facing result rather than a hanging/broken call.

### Tasks

- [ ] Add self-hosted LiveKit development service.
- [ ] Add self-hosted LiveKit Egress development service.
- [ ] Keep services separate from PHP workspace.
- [ ] Define RTC gateway boundary.
- [ ] Add LiveKit infrastructure implementation.
- [ ] Add short-lived room-token authorization.
- [ ] Keep LiveKit secrets server-side.
- [ ] Preserve Reverb as messaging realtime.
- [ ] Add LAN/VPN/TURN/TLS production requirements to Phase 40 planning.
- [ ] Add RTC health/readiness hooks.
- [ ] Isolate RTC failures from Chat messaging.
- [ ] Isolate Egress failure from live Meetings.
- [ ] Keep Meeting domain state independent from LiveKit room existence.
- [ ] Prevent in-person Meetings from creating LiveKit rooms or participant tokens.
- [ ] Keep in-person Meetings usable while LiveKit, TURN, or Egress is unavailable.
- [ ] Add deterministic RTC integration test infrastructure.
- [ ] Add unauthorized-room negative tests.

---

## P31-W08 — Direct/group/Team ad-hoc Calls and Call history

### Call types

Ad-hoc Calls exist inside existing:

- direct conversations;
- user-created group conversations;
- Team conversations.

They do not create a new conversation.

A Call may begin with:

- microphone only;
- camera enabled.

Camera may be toggled during the Call.

There is no immutable distinction between an "audio call" and "video call" after connection.

### Recording prohibition

Ad-hoc Calls cannot be recorded.

This applies to:

- direct Calls;
- group Calls;
- Team Calls.

Do not show recording controls.

Do not invoke Egress for ad-hoc Calls.

Meeting recording is a separate contract.

### One active Call per conversation

A conversation may have at most one active ad-hoc Call.

If one exists, other users see:

`Join`

rather than starting a second parallel Call.

### One active RTC session per user

A user may participate in only one active Call/Meeting RTC session at a time.

Do not implement:

- call waiting;
- holding one call;
- switching between simultaneous active calls.

If another direct/group caller reaches a busy user:

- caller sees Busy/unavailable;
- target may receive a missed-call indication.

### Direct/group ringing

Direct Calls use a normal incoming-call UX.

User-created group Calls may notify/ring eligible group participants.

### Team Call behavior

Do not create a ringtone storm for an entire large Team.

Starting a Team Call should create an active Team-call state and appropriate realtime/browser alert.

Eligible Team members may join.

### Incoming call UX

Incoming video-capable Call presents explicit choices such as:

- Decline;
- Answer without camera;
- Answer with camera.

Do not automatically enable the camera merely because the caller initiated with video.

### Device preferences

Persist per-user preferences for:

- preferred camera;
- preferred microphone;
- preferred speaker/output where supported;
- whether outgoing Calls start with camera enabled.

If a preferred device is unavailable:

- fall back safely;
- allow device choice;
- do not hard-fail the Call.

### Pre-call screen

Calls use a pre-call device screen.

Allow:

- camera preview;
- camera selection;
- microphone selection;
- speaker selection where supported;
- camera on/off;
- microphone on/off.

Request camera/microphone browser permissions only after an explicit Call/Meeting action.

Do not prompt during ordinary Atlas navigation.

### Device switching

Allow device changes while connected.

### Screen sharing

Ad-hoc Calls support screen sharing.

Only one active screen share exists in a Call at a time.

### Call lifecycle

Support:

- outgoing ringing;
- incoming ringing;
- accepted;
- declined;
- busy;
- missed;
- active;
- ended;
- failed.

Persist useful Call metadata without recording content.

### Call history

Provide a user Call history with filters:

- All;
- Missed;
- Incoming;
- Outgoing.

Show useful metadata such as:

- person/group/Team;
- initial audio/video mode;
- direction;
- missed/answered state;
- start time;
- duration.

Add structural conversation timeline entries for relevant Call outcomes.

### Missed call notification

A missed Call also creates appropriate normal Atlas Notification/browser alert according to user preferences.

Do not send Call emails.

### Refresh/rejoin

If the browser refreshes during an active Call:

- detect the still-active authorized session;
- offer `Rejoin call`.

Do not silently create a second session.

Do not automatically re-enable mic/camera without user interaction.

### Tasks

- [ ] Implement DM ad-hoc Calls.
- [ ] Implement group ad-hoc Calls.
- [ ] Implement Team ad-hoc Calls.
- [ ] Enforce one active Call per conversation.
- [ ] Enforce one active RTC session per user.
- [ ] Add Busy behavior.
- [ ] Add incoming Call UI state.
- [ ] Add outgoing camera-default preference.
- [ ] Persist preferred devices.
- [ ] Add pre-call device screen.
- [ ] Add in-call device switching.
- [ ] Add camera toggle.
- [ ] Add microphone toggle.
- [ ] Add one-person screen share.
- [ ] Keep ad-hoc Calls non-recordable.
- [ ] Add Team join-style notification behavior.
- [ ] Add Call history and filters.
- [ ] Add missed Call timeline state.
- [ ] Add missed Call Notifications/browser alert.
- [ ] Add Rejoin Call flow.
- [ ] Add Call lifecycle/idempotency/concurrency tests.

---

## P31-W09 — Meeting scheduling, invitations, recurrence, Meeting chat, and Calendar composition

### Meeting types

Support:

- `Meet now`;
- scheduled Meeting.

Meetings are standalone objects.

They are not attached to an existing DM/group/Team conversation.

Each Meeting has its own system Meeting conversation.

Every Meeting has exactly one canonical mode:

- `online` — Atlas online joining and the existing LiveKit RTC behavior are available; physical location is not required;
- `in_person` — a physical Meeting with no Atlas RTC room, token, online join, media controls, recording, or transcription workflow;
- `hybrid` — one Meeting with both a physical text location and an Atlas online join option.

Meeting identity, scheduling, invitations, RSVP, recurrence, reminders, Calendar event, Meeting chat, cancellation, and history are Atlas-owned and apply across all three modes. Meeting creation must not be modeled around LiveKit room existence.

### Meeting creation and location

The organizer explicitly selects Online, In person, or Hybrid in the Meeting form.

The form is mode-aware:

- Online exposes relevant online Meeting configuration and does not require physical location;
- In person requires/shows the physical text location and does not show RTC, device, recording, or transcription configuration;
- Hybrid requires/shows the physical text location and explains that Atlas online joining is also available.

Location is first-class Meeting product data for `in_person` and `hybrid`, using a normal text value such as a room or floor description. Do not introduce room inventory, booking, capacity, maps, resource scheduling, or a room-conflict engine.

### Internal-only participants

Only active Atlas users may be invited.

Do not support:

- external guests;
- anonymous guests;
- public join links;
- external email-only participants.

External company communication may continue through another product outside Atlas.

### Roles

Meeting roles are exactly:

- organizer;
- participant.

Do not add:

- co-organizer;
- presenter role;
- moderator role hierarchy.

Organizer retains Meeting administration capabilities.

Normal participants in RTC-enabled `online` and `hybrid` Meetings still have broad in-Meeting media capabilities.

### Invitations

A user must be invited to join a Meeting.

Invitation states include at least:

- no response;
- accepted;
- declined.

Invitees may change their response later.

A declined user remains historically invited and keeps accepted Meeting-chat visibility rules.

A declined response must not be treated as permanent removal.

Every invited/participating user may invite additional active Atlas users.

A newly invited user receives a normal invitation and chooses accept/decline.

Participant invitation does not automatically mark the new user Accepted.

Organizer may remove an invited participant before the Meeting.

Removal:

- removes Calendar Meeting access;
- removes join access;
- removes Meeting-chat access;
- preserves historical invitation evidence.

### No lobby

Do not add a waiting room/lobby.

An eligible invited user may join an RTC-enabled `online` or `hybrid` Meeting without organizer approval.

### Join timing

An invited eligible user may join the RTC session of an `online` or `hybrid` Meeting before the scheduled start time.

Do not impose an arbitrary 15-minute early-join limit.

A Meeting may run without the organizer present.

### Recurring Meetings

Support recurring Meetings using the Calendar recurrence foundation.

Provide:

- daily;
- weekly;
- monthly;
- selected weekdays;
- end date;
- occurrence count.

Editing supports:

- this occurrence;
- this and future;
- whole series.

One recurring series has one shared Meeting chat.

Each occurrence has separate:

- optional live RTC session for `online` or `hybrid` mode;
- RTC-derived attendance where an RTC session exists;
- recording where the occurrence is RTC-enabled and recording is started;
- transcript only where an eligible retained recording exists.

Meeting mode is coherent across a recurring series and its occurrence/future/series edits. Mode changes must preserve one Meeting series and must not create a second physical or online Meeting record.

### Meeting Calendar event

Meeting events appear in the Calendar of authorized invitees.

Meeting Calendar data contains appropriate Meeting details for invitees.

Calendar presentation distinguishes Online, In person, and Hybrid without exposing LiveKit, RTC, or SFU terminology. It shows physical location for `in_person` and `hybrid`, and indicates Atlas online joining for `online` and `hybrid` where authorized.

Non-invitees do not receive the Meeting event in their Calendar.

Free/Busy scheduling checks do not expose private event details.

### Meeting metadata

Support at least:

- title;
- description;
- start/end;
- recurrence;
- explicit `online`, `in_person`, or `hybrid` mode;
- physical text location for `in_person` and `hybrid`;
- organizer;
- invitees;
- RSVP state;
- reminders.

### Cancellation

Cancelled Meetings remain historical.

Render them as cancelled rather than deleting them from history.

Send appropriate update/cancellation delivery.

### Meeting chat

Meeting chat exists from Meeting creation.

It remains accessible before/during/after the Meeting according to invitation/removal authorization, independently from Meeting mode or RTC availability.

An `in_person` Meeting retains the complete Meeting chat even though no LiveKit session exists.

Newly invited authorized participants may access the existing Meeting conversation history.

### Tasks

- [ ] Implement Meet now.
- [ ] Implement scheduled Meetings.
- [ ] Add explicit online / in-person / hybrid Meeting mode.
- [ ] Keep Meeting domain ownership independent from RTC room existence.
- [ ] Add physical location behavior for in-person/hybrid Meetings.
- [ ] Implement organizer/participant roles.
- [ ] Implement invitation state.
- [ ] Add accept/decline/change-response.
- [ ] Allow participants to invite additional Atlas users.
- [ ] Keep newly invited users pending until response.
- [ ] Allow organizer removal.
- [ ] Keep Meeting internal-only.
- [ ] Keep no-lobby behavior.
- [ ] Allow joining before scheduled start.
- [ ] Allow Meeting without organizer present.
- [ ] Add recurring Meeting rules.
- [ ] Add occurrence/future/series editing.
- [ ] Add one Meeting chat per recurring series.
- [ ] Keep all invitation/RSVP/recurrence/Calendar/chat behavior across all Meeting modes.
- [ ] Prevent in-person Meetings from creating LiveKit rooms/tokens.
- [ ] Preserve RTC capability only for online/hybrid Meetings.
- [ ] Add occurrence-specific optional RTC/attendance/recording.
- [ ] Publish Meetings into Core Calendar.
- [ ] Add Calendar mode/location presentation.
- [ ] Add conflict warnings.
- [ ] Preserve cancelled history.
- [ ] Add Meeting invitation/update/cancellation Notifications/email behavior.
- [ ] Add mode-specific authorization/domain tests.
- [ ] Add authorization/recurrence/browser tests.

---

## P31-W10 — RTC-enabled Meeting session, moderation, devices, screen sharing, and lifecycle

This workstream applies only to `online` and `hybrid` Meetings that have an active RTC session. An `in_person` Meeting has no Join online action, pre-call screen, microphone/camera/speaker controls, screen sharing, RTC moderation, RTC kick/room-lock behavior, empty-room timer, minimized RTC session, or Rejoin flow.

A `hybrid` Meeting remains one Meeting with a physical location and one optional Atlas RTC session. Do not create separate physical and online Meeting objects.

### Pre-Meeting

Use the same canonical media-device preparation foundation as Calls.

Provide:

- camera preview;
- preferred devices;
- microphone on/off;
- camera on/off;
- device selection.

### Participant capabilities

A normal participant may:

- enable/disable own microphone;
- enable/disable own camera;
- change devices;
- share screen;
- stop own screen sharing;
- invite additional users while the Meeting is unlocked.

### Screen sharing

Only one participant may actively share a screen at a time.

If a screen share is active, another participant cannot start a second simultaneous share.

Organizer may stop another participant's screen share.

### Organizer moderation

Organizer may:

- mute a participant;
- disable a participant's microphone;
- restore permission to use microphone;
- turn off a participant's camera;
- remove/kick a participant;
- lock/unlock Meeting;
- stop another participant's screen share;
- end Meeting for everyone.

Remote moderation must never:

- remotely enable another user's microphone;
- remotely enable another user's camera.

Normal mute:

- turns microphone off;
- participant may unmute again.

Disable microphone:

- is a temporary speaking ban;
- participant cannot unmute until organizer restores microphone permission.

Organizer camera-off action turns off the participant's camera.

It never remotely turns camera on.

### Kick

Kicking a participant bans that user from the remainder of the current Meeting occurrence.

The kicked user:

- leaves the RTC room;
- cannot immediately rejoin from the old invitation;
- loses active Meeting access including Meeting chat for the occurrence.

Historical participation remains.

Do not silently convert kick into simple disconnect.

### Meeting lock

Lock prevents:

- new joins;
- new participant invitations.

Existing connected participants remain.

Unlock restores normal invitation/join behavior.

### Leave/end

Any participant may Leave.

Organizer also has:

`End meeting for everyone`.

If organizer chooses only Leave:

- Meeting continues for remaining users.

### Empty room cleanup

If an active RTC Meeting room has no human participants for 15 continuous minutes:

- end the live session;
- release RTC resources.

If a user rejoins before the 15-minute window expires:

- reset/cancel the empty-room shutdown timer.

Empty-room cleanup ends only the LiveKit RTC session and releases its media resources. It does not cancel, end, or delete the Meeting domain object, cancel its Calendar event, close Meeting chat, or change invitations or RSVP. It never applies to an `in_person` Meeting.

### Attendance

Persist RTC-derived occurrence attendance for users who actually join an `online` or `hybrid` RTC session, including:

- participant;
- join time;
- leave time;
- total presence duration where derivable.

Every authorized Meeting participant may see full attendance information.

Do not restrict complete attendance to organizer only.

RTC-derived attendance describes online participation only. For `in_person` and `hybrid` Meetings, absence of an RTC join must never be treated as proof that an invitee was physically absent. Atlas does not automatically know physical presence and this phase adds no QR, NFC, room-hardware, or geolocation attendance system.

### Minimize/rejoin

The active Call/Meeting experience uses a large Atlas modal that may be minimized.

A minimized persistent control remains available while navigating Atlas.

After browser refresh, offer Rejoin rather than silently recreating media state.

### Tasks

- [ ] Add Meeting pre-call device screen.
- [ ] Keep RTC session controls limited to online/hybrid Meetings.
- [ ] Keep in-person Meetings free of Join online/pre-call/media controls.
- [ ] Keep in-person Meetings independent from LiveKit/TURN availability.
- [ ] Keep Hybrid as one Meeting combining physical location with an optional Atlas RTC session.
- [ ] Add mic/camera/device controls.
- [ ] Add one active screen share.
- [ ] Add organizer stop-screen-share.
- [ ] Add organizer mute.
- [ ] Add organizer microphone-disable/restore.
- [ ] Add organizer camera-off.
- [ ] Add kick/occurrence ban.
- [ ] Add lock/unlock.
- [ ] Disable invitations while locked.
- [ ] Add Leave.
- [ ] Add organizer End for everyone.
- [ ] Add 15-minute empty-room termination.
- [ ] Limit empty-room cleanup to RTC resources without changing Meeting domain state.
- [ ] Add attendance join/leave/duration.
- [ ] Prevent RTC attendance from falsely classifying physical attendance.
- [ ] Expose attendance to participants.
- [ ] Add minimized session state.
- [ ] Add Rejoin behavior.
- [ ] Add moderation and negative authorization tests.
- [ ] Add multi-user browser acceptance.

---

## P31-W11 — Meeting recording, Egress, Files ownership, retention, and controlled sharing

### Recording boundary

Only RTC-enabled `online` and `hybrid` Meetings may be recorded through Atlas/LiveKit Egress.

An `in_person` Meeting without an RTC session cannot start Atlas recording and must not expose recording controls, REC/Paused state, processing state, or an occurrence recording viewer.

Do not allow recording for:

- direct ad-hoc Calls;
- group ad-hoc Calls;
- Team ad-hoc Calls.

### Permission and role

Recording controls require the appropriate recording permission.

In addition, only the Meeting organizer may:

- start;
- pause;
- resume;
- stop

recording.

A non-organizer cannot control Meeting recording merely because the user possesses a general recording permission.

### Participant indication

Every connected participant must clearly see:

- recording active;
- recording paused.

Use an obvious REC/Paused state.

Do not require a separate acceptance dialog before remaining in the Meeting.

Recording start/resume still requires a clearly visible state change.

### Recording lifecycle

Support:

- not recording;
- starting;
- recording;
- pausing;
- paused;
- resuming;
- stopping;
- processing;
- ready;
- failed;
- removed by retention.

Do not block the Meeting while final recording processing occurs.

### LiveKit Egress

Use self-hosted LiveKit Egress for Meeting recording.

Use room-composite recording.

Create an Atlas-owned recording composition/template so recording appearance remains controlled by Atlas rather than depending on an incidental default vendor UI layout.

Accepted composition:

- when screen sharing is active, shared screen is primary and participant cameras are secondary;
- without screen share, use a stable grid/active-speaker style appropriate to the Meeting.

Record:

- participant audio;
- participant video where enabled;
- active screen share;
- the composed Meeting result.

For a `hybrid` Meeting, recording contains only media actually published into the LiveKit RTC session. Atlas does not claim to record people physically present in a room unless a room laptop/browser or other authorized participant publishes their microphone/camera into that RTC session.

### Recording output

Use one consistent final video format/lifecycle.

Even an audio-only Meeting recording ultimately uses the canonical final Meeting recording representation rather than introducing a separate user-facing audio-recording product path.

Final user-facing recording/download is one file.

### Pause/resume

User-facing recording is one logical recording.

Pause/resume may internally create multiple recording segments.

Do not expose those segments as separate recordings to users.

Finalization must produce:

- one playable recording;
- one downloadable file.

If finalization is still running:

`Processing`

is an accepted product state.

### Recording system timeline

Meeting timeline records structural entries for:

- started;
- paused;
- resumed;
- stopped

including safe actor/time metadata.

Do not put media contents into Audit or system timeline.

### Files ownership

Final recordings become canonical Atlas Files-owned private artifacts.

LiveKit/Egress staging is not the long-term authorization/storage system.

After successful finalization/import:

- Files owns access/download;
- Meeting metadata references the Files artifact.

Clean temporary/staging artifacts safely.

Do not leave a second permanent LiveKit recording-storage domain.

### Recording access

All currently authorized Meeting participants may view/download the final recording subject to the normal recording permissions.

Admin status does not grant access.

A user whose Meeting access was explicitly removed/kicked does not gain privileged access solely because historical participation metadata exists.

### Sharing recording outside Meeting

A Meeting participant with recording-share permission may share a specific recording with another active Atlas user who was not a Meeting participant.

This is an explicit recording access grant.

The recipient:

- does not become a Meeting participant;
- does not gain Meeting chat;
- does not gain Calendar event;
- does not gain attendance details merely from recording share;
- cannot re-share the recording.

The original participant who created a share may revoke that share.

Only actual Meeting participants may create onward recording shares.

No public links.

No external/non-Atlas recipients.

Admin does not gain a bypass.

### Recording retention

Recording retention is separate from normal Chat-message retention.

Default:

`null`

meaning retain indefinitely.

Admin may configure a positive retention period.

Scheduled cleanup prevents indefinite disk growth when the company enables a finite policy.

Do not send users pre-deletion warnings merely because recording retention will run.

Retention cleanup removes heavy recording content and coordinated dependents.

Preserve lightweight historical Meeting/recording metadata such as:

- that recording occurred;
- start/stop timing;
- duration;
- retention-removal state;
- relevant structural/Audit history.

When a recording is deleted by retention, also delete:

- transcript;
- transcript versions;
- transcript Search projection;
- transcript share grants;
- recording share grants;
- other content dependents that no longer have a valid source.

Do not leave orphaned Files or stale Search documents.

### Admin recording retention

Provide aggregate operational retention visibility without private content.

Use queue/Managed Processes for large cleanup.

### Tasks

- [ ] Add RTC-enabled Meeting recording permission/organizer enforcement.
- [ ] Allow online Meetings to be recorded under the existing organizer/permission rules.
- [ ] Allow hybrid Meetings to be recorded under the existing organizer/permission rules.
- [ ] Prevent in-person Meetings from starting Atlas RTC recording.
- [ ] Keep in-person Meeting UI free of recording controls.
- [ ] Limit hybrid recordings to media actually published into LiveKit.
- [ ] Keep all ad-hoc Calls non-recordable.
- [ ] Add start/pause/resume/stop UX.
- [ ] Add clear REC/Paused participant state.
- [ ] Integrate self-hosted LiveKit Egress.
- [ ] Add Atlas-owned composite recording template.
- [ ] Add screen-share-first recording layout.
- [ ] Add internal segment model where needed.
- [ ] Produce one final playable/downloadable file.
- [ ] Add Processing/Ready/Failed states.
- [ ] Add recording structural timeline events.
- [ ] Import final recording into Files.
- [ ] Keep recording lifecycle Files-owned after Egress finalization.
- [ ] Clean Egress staging safely.
- [ ] Add participant recording view/download authorization.
- [ ] Add explicit participant-created recording shares.
- [ ] Prevent share recipient onward sharing.
- [ ] Add share revocation.
- [ ] Prevent public/external sharing.
- [ ] Add nullable separate recording retention.
- [ ] Add recording-retention cleanup.
- [ ] Delete transcript and shares with removed recording.
- [ ] Preserve lightweight recording metadata.
- [ ] Add Admin aggregate recording-retention operations.
- [ ] Add recording/privacy/retention browser and integration tests.

---

## P31-W12 — Provider-neutral asynchronous transcription boundary

### Scope

Phase 31 prepares Atlas for transcription.

Do not make a specific production transcription provider mandatory.

Do not require:

- OpenAI;
- Whisper;
- whisper.cpp;
- Python;
- Azure;
- AWS;
- Google;
- any other concrete STT vendor.

Do not deploy a Whisper container in the Atlas baseline.

Do not implement Atlas-owned speech-to-text/AI.

Atlas responsibility is intentionally narrow:

```text
recording
→ queue transcription request
→ send recording to configured provider adapter
→ provider processes
→ Atlas receives result
→ Atlas stores transcript
```

### Provider boundary

Create a narrow provider-neutral transcription boundary.

It must support providers that are:

- synchronous;
- asynchronous.

An async provider may use:

```text
submit
→ external job id
→ poll/check
→ result
```

The Atlas domain must not care which provider style is used.

### Queue

Transcription is always background work.

Never run transcription synchronously inside the user HTTP request.

Use the existing queue/Managed Process infrastructure.

Requirements:

- idempotency;
- retry/backoff;
- provider timeout handling;
- failure state;
- safe manual retry;
- no duplicate active transcription job for one recording.

A dedicated queue may be used if it fits current queue conventions.

### Provider configuration

Transcription is disabled unless a provider is:

- available;
- configured;
- explicitly enabled.

Admin Settings/Integrations may expose transcription provider configuration.

Provider credentials:

- remain secret;
- never enter Git;
- never enter normal logs;
- never reach the browser.

If multiple provider adapters exist in the future, configuration may select the active provider.

### Baseline provider implementation

Phase 31 does not require a concrete production STT adapter.

Use deterministic fake/test infrastructure where needed to prove the contract.

Do not claim transcription is available in production when no provider exists/configured.

### Frontend gating

When no provider is configured/enabled, normal users must see no transcription product UI.

Transcription UI is eligible only when both conditions are true:

- an eligible retained recording from an `online` or `hybrid` Meeting exists;
- a transcription provider is available, configured, and enabled.

A pure `in_person` Meeting without an RTC recording never exposes transcription actions merely because a provider is configured.

Hide:

- Transcript tab;
- Create transcript;
- queue/processing status;
- failed state;
- retry;
- transcript viewer;
- transcript Search controls.

Do not show a useless normal-user placeholder such as:

`Transcription is not configured`.

Admin configuration may show the disabled/unconfigured state.

### Manual request

When a provider is enabled:

- transcription is initiated manually;
- do not automatically transcribe every recording.

Any authorized Meeting participant with transcription-request permission may request it.

Allow manual transcription for historical retained recordings created before provider configuration.

If one transcription job is already queued/processing:

- other users see its state;
- do not create duplicates.

### Result

Minimum provider result:

- transcript text.

If provider also returns:

- timestamps;
- speaker labels;
- structured segments

preserve them where useful.

Do not require Atlas to perform its own diarization.

### Transcript ownership/access

Transcript belongs to the Meeting recording.

Authorized Meeting participants with transcript-view permission may view it.

Admin status does not bypass this.

### Editing

Every authorized Meeting participant with transcript-edit permission may edit the transcript.

Preserve:

- provider/original result as the initial version;
- every later version;
- editor;
- timestamp;
- previous content;
- new/current content.

Participants may inspect full edit history.

Do not silently overwrite the transcript.

### Transcript sharing

A Meeting participant with transcript-share permission may share the current transcript with another active Atlas user outside the Meeting.

Recipient:

- receives transcript access only;
- does not become Meeting participant;
- does not receive Meeting chat;
- does not receive recording automatically;
- sees only the current transcript;
- does not see transcript edit history;
- cannot edit;
- cannot re-share.

The participant who created a transcript share may revoke it.

No public/external sharing.

### Recording dependency

Transcript lifecycle follows its recording.

Transcription cannot be requested without an eligible retained Meeting recording. Do not add live transcription or direct microphone-to-transcript processing outside the recording workflow.

When recording retention deletes the recording:

- delete transcript;
- delete transcript versions;
- delete transcript shares;
- remove transcript Search projection.

### Tasks

- [ ] Add provider-neutral transcription contract.
- [ ] Support sync provider adapter behavior.
- [ ] Support async submit/poll/result provider behavior.
- [ ] Run all transcription through queue/Managed Processes.
- [ ] Add idempotency.
- [ ] Add retry/backoff/failure state.
- [ ] Add provider configuration boundary.
- [ ] Keep credentials secret.
- [ ] Keep concrete production provider optional/unimplemented.
- [ ] Add deterministic test provider.
- [ ] Hide user transcript UI while provider unavailable.
- [ ] Require an eligible retained online/hybrid Meeting recording for transcription.
- [ ] Keep in-person Meetings without recordings free of transcript UI and jobs.
- [ ] Preserve transcript eligibility for retained online/hybrid recordings when the provider is enabled.
- [ ] Add manual Create transcript flow when enabled.
- [ ] Allow historical recording transcription.
- [ ] Prevent duplicate active transcription requests.
- [ ] Persist text and optional timestamps/speaker segments.
- [ ] Add transcript version history.
- [ ] Allow participant editing with permission.
- [ ] Add participant transcript sharing.
- [ ] Prevent onward sharing by recipient.
- [ ] Hide edit history from share recipient.
- [ ] Delete transcript with recording retention.
- [ ] Prevent live or microphone-direct transcription outside the recording workflow.
- [ ] Add provider/queue/privacy/versioning tests.

---

## P31-W13 — Shell/modal UX, Calendar UI, notifications, reminders, and preferences

### Chat shell

Chat remains primarily shell-launched.

Do not create a dedicated full-page Chat application as the primary interface.

Provide:

- Chat launcher;
- unread badge;
- Chat unread dropdown.

Chat unread data remains separate from Notifications persistence.

One unread dropdown entry per conversation.

### Chat modal

Desktop uses one substantial Chat modal.

One active conversation at a time.

Do not create multiple floating Messenger windows.

Mobile Chat becomes full-screen.

Conversation list provides:

- All;
- Unread;
- Direct;
- Groups;
- Team;
- Meeting where appropriate.

Preserve favorites/starred conversations.

No Archive.

### Live Call/Meeting modal

Calls and RTC-enabled `online`/`hybrid` Meetings use a large live-session modal. An `in_person` Meeting does not render this media surface.

It can be minimized.

When minimized, provide a persistent compact control containing at least:

- session/Call/Meeting identity;
- microphone state/control;
- camera state/control;
- screen-share indication;
- REC/Paused state when a Meeting is recording;
- return to full session;
- Leave;
- organizer End where applicable.

The user may navigate elsewhere in Atlas while the live session continues.

### Meeting creation UI

Meeting creation explicitly offers localized Online, In person, and Hybrid mode choices.

- Online does not require a physical location and exposes the applicable online Meeting configuration;
- In person exposes the physical text location and hides online join, device, screen-share, recording, and transcription controls;
- Hybrid exposes the physical text location and clearly indicates that Atlas online joining is available.

The mode-aware form creates one Meeting in every case.

### Incoming Call

Incoming Call UI appears globally regardless of the current Atlas page.

Use explicit ringtone/alert behavior.

Video-capable incoming Calls provide:

- Decline;
- Answer without camera;
- Answer with camera.

### Calendar UI

Expose the shared Calendar using:

- Month;
- Week;
- Day;
- Agenda.

Support personal events and Meeting events in one coherent Calendar.

Meeting entries present mode and location in product language:

- Online indicates that joining in Atlas is available;
- In person shows the physical location;
- Hybrid shows the physical location and that online joining is available.

Do not expose LiveKit room, RTC session, or SFU terminology in Calendar UI.

### Browser-native notifications

Preserve the shared browser-native notification capability for:

- existing system Notifications;
- Chat alerts;
- incoming/missed Call alerts where appropriate.

Control layers:

1. application-level configuration;
2. user preference;
3. browser permission.

Do not aggressively request browser permission.

Request it only after intentional user action.

### Chat messages

Do not:

- create Notifications-module records for every Chat message;
- email Chat messages;
- email Chat mentions.

### Meeting notifications

Meetings may use existing Notifications and email.

User preferences separately control at least:

- invitation/update/cancellation email;
- Meeting reminder email.

Meeting reminder default:

15 minutes before

unless user profile default or per-Meeting override changes it.

### Personal Calendar reminders

Personal Calendar reminders may use:

- Atlas Notifications;
- browser-native alert where enabled;
- email where user preferences allow.

### Missed Calls

Missed Calls create:

- Call-history state;
- conversation system entry;
- appropriate Atlas Notification/browser alert.

No Call email.

### Accessibility

Protect:

- keyboard navigation;
- focus;
- focus trapping/restoration;
- Escape semantics;
- screen-reader labels;
- mobile touch targets;
- device-control accessibility.

### Tasks

- [ ] Preserve Chat shell launcher.
- [ ] Preserve unread dropdown/badge.
- [ ] Add Meeting conversation list integration.
- [ ] Preserve desktop Chat modal.
- [ ] Preserve full-screen mobile Chat.
- [ ] Add minimizable Call/Meeting modal.
- [ ] Add persistent minimized controls.
- [ ] Add mode-aware Meeting creation UI.
- [ ] Hide RTC/recording/transcription controls for in-person Meeting creation and use.
- [ ] Add global incoming Call UI.
- [ ] Add Calendar Month/Week/Day/Agenda UI.
- [ ] Add localized Meeting mode/location presentation in Calendar.
- [ ] Add Meeting invitation/update/cancellation notifications.
- [ ] Add Meeting email preferences.
- [ ] Add Meeting reminder preferences.
- [ ] Add personal Calendar reminders.
- [ ] Add personal Calendar email preference.
- [ ] Preserve Chat browser notification separation.
- [ ] Add missed Call notification behavior.
- [ ] Preserve no-Chat-email rule.
- [ ] Add accessibility/focus behavior.
- [ ] Add mobile/light/dark coverage.

---

## P31-W14 — Search and Meilisearch

### Search foundation

Use existing Atlas Search/Meilisearch.

Do not create a second search engine.

Search Chat content including:

- conversations;
- users;
- message text;
- attachment filenames;
- links.

Support useful filters:

- person/author;
- conversation;
- date;
- type.

### Meeting content

When authorized and available, Search may include:

- Meeting conversation messages;
- current transcript text;
- useful authorized Meeting metadata.

Do not index raw audio/video content.

### Critical authorization invariant

Search must never reveal unauthorized communication content.

This includes:

- body;
- snippet;
- filename;
- URL;
- Meeting name;
- transcript text;
- participant metadata;
- matched fragment.

Admin has no private-content Search bypass.

### Transcript Search

Only current transcript content is indexed.

Update Search when transcript is edited.

Historical transcript versions do not need to appear as ordinary Search results.

Transcript Search access requires current authorization through:

- Meeting participation;
- or an explicit valid transcript-share grant where that grant gives transcript visibility.

Revoking access removes future Search visibility.

Recording retention deletion removes transcript Search documents.

### Visibility changes

Update Search visibility on:

- group membership changes;
- Team membership changes;
- Meeting invitation/removal changes;
- Meeting kick/access changes;
- Chat activation changes;
- user deactivation;
- delete-for-me;
- transcript share/revocation;
- retention.

### Tasks

- [ ] Preserve Chat Search projection.
- [ ] Search conversations/users/messages/files/links.
- [ ] Add Meeting chat Search.
- [ ] Add current transcript Search.
- [ ] Add filters.
- [ ] Enforce content authorization before rendering results.
- [ ] Prevent Admin bypass.
- [ ] Respect delete-for-me.
- [ ] Respect Meeting access removal.
- [ ] Respect transcript shares/revocation.
- [ ] Remove retained/deleted recording transcript documents.
- [ ] Add negative mutation tests.
- [ ] Add browser Search coverage.

---

## P31-W15 — Retention, privacy, Admin operations, Audit, and exports

### Chat retention

Normal Chat retention remains configurable.

Default:

`null`

meaning retain indefinitely.

Finite positive days enable cleanup.

Coordinate as applicable across:

- messages;
- edits;
- reactions;
- pins;
- bookmarks;
- system entries;
- Search;
- attachments;
- voice messages;
- dependents.

Do not leave stale Search documents or orphan Files.

### Recording retention

Meeting recording retention is a separate policy.

It also defaults to:

`null`.

Do not silently inherit normal Chat-message retention as the recording policy.

Recording retention cleanup follows the P31-W11 contract.

### Privacy boundary

Administrator status alone never allows an Admin through Atlas to:

- open another user's DM;
- open a private group;
- open private Meeting chat;
- read messages;
- inspect private edit history;
- listen to voice messages;
- view/download private attachments;
- view/download Meeting recording;
- read transcript;
- inspect transcript edit history;
- Search private content;
- export private conversation content.

Direct root/database/infrastructure access is outside application authorization and governed by company procedure.

### Encryption boundary

Do not implement:

- E2EE;
- application-level Chat encryption protocol;
- per-message key management;
- RTC E2EE system;
- custom recording encryption layer.

Production encryption at rest belongs to Phase 40 infrastructure.

### Admin operational visibility

Admin may see safe aggregates such as:

- Chat health;
- Reverb health;
- LiveKit RTC health;
- Egress health;
- failed jobs;
- active Call/Meeting counts;
- conversation count;
- message count;
- attachment usage;
- recording storage usage;
- transcription queue/provider health;
- Search health;
- retention state.

Do not expose private room names, participant lists, message bodies, recording contents, or transcripts without a concrete authorized reason.

Prefer aggregates.

### Audit

Audit structural/high-value operations such as:

- group created;
- membership changed;
- ownership transferred;
- Call started/ended;
- Meeting created/rescheduled/cancelled;
- participant invited/removed/kicked;
- Meeting locked/unlocked;
- organizer moderation action where security-relevant;
- recording started/paused/resumed/stopped;
- recording shared/revoked;
- recording retention configuration/run;
- transcription requested/completed/failed;
- transcript shared/revoked;
- privileged operational action.

Do not copy into Audit:

- normal message bodies;
- voice content;
- audio/video recording content;
- transcript body;
- attachment content;
- every ordinary message.

Use safe identifiers/metadata.

### Employee identity

Employee deactivation does not automatically anonymize historical Chat/Meeting content.

Historical identity remains attributable, with inactive marker where appropriate.

Do not apply debtor anonymization automatically.

### Exports

Preserve participant-authorized conversation exports through existing export foundation.

Support appropriate:

- PDF;
- CSV;
- JSON.

Admin does not gain export bypass.

Exports respect:

- authorization;
- delete-for-me;
- retention;
- attachments;
- forwarding privacy.

Recording download is handled by recording Files authorization rather than pretending video is a CSV export.

### Tasks

- [ ] Preserve nullable Chat retention.
- [ ] Keep recording retention separate.
- [ ] Add coordinated cleanup.
- [ ] Add safe Admin Chat/RTC/Egress aggregates.
- [ ] Add safe provider/transcription operational state.
- [ ] Prevent Admin private-content access.
- [ ] Audit structural Calls/Meetings/recording/transcription events.
- [ ] Keep content bodies out of Audit.
- [ ] Preserve historical employee identity.
- [ ] Preserve participant-authorized Chat exports.
- [ ] Prevent Admin export bypass.
- [ ] Add retention/privacy/Audit/export tests.

---

## P31-W16 — Scale, performance, browser workflows, and final acceptance closure

### Scale

Target realistic company use around hundreds of users with future growth.

Do not add artificial user/participant limits.

Do not load full conversation histories.

Use cursor/incremental pagination.

Avoid obvious N+1 behavior.

Bound presence/typing writes.

Do not create per-user × per-message permanent structures where a cursor model works.

### RTC capacity

No product participant limit is required.

Infrastructure capacity remains finite.

Capacity failure must produce:

- deterministic backend/session state;
- clear user-facing error;
- no half-created hanging session.

### Browser workflows

Playwright must cover meaningful multi-user workflows using deterministic RTC/media fixtures where physical hardware cannot be used.

#### Messaging

Cover:

- DM creation;
- text realtime;
- unread;
- read;
- reply;
- edit;
- edit history;
- reaction;
- mention;
- pin;
- bookmark;
- forward;
- delete-for-me;
- draft;
- group lifecycle;
- Team synchronization.

#### Files/voice

Cover:

- file upload;
- scan lifecycle;
- voice recording lifecycle;
- playback.

#### Ad-hoc Calls

Cover:

- direct outgoing/incoming;
- answer without camera;
- answer with camera;
- camera toggle;
- mic toggle;
- device preference;
- Busy behavior;
- one active session/user;
- Call history;
- missed Call;
- Rejoin;
- group Call;
- Team join-style Call;
- screen share;
- no recording control.

#### Calendar

Cover:

- personal event;
- recurrence;
- reminders;
- Month/Week/Day/Agenda;
- Free/Busy privacy;
- conflict warning;
- Europe/Warsaw recurrence behavior.

#### Meetings

Cover:

- Meet now;
- scheduled Meeting;
- recurring Meeting;
- explicit Online, In person, and Hybrid selection;
- in-person physical location;
- hybrid physical location plus Atlas online joining;
- invite;
- accept;
- decline;
- response change;
- participant invites another user;
- organizer removal;
- no lobby;
- early join;
- Meeting without organizer;
- shared series chat;
- lock/unlock;
- mute;
- microphone disable/restore;
- camera off;
- screen share;
- organizer stop share;
- kick/ban;
- attendance;
- Leave;
- End for everyone;
- empty-room auto-end.

In-person coverage must prove invitation/RSVP, Calendar mode/location, reminders, Meeting chat, recurrence, and cancellation without Join online, pre-call/device controls, screen sharing, RTC moderation, recording, transcription, or a LiveKit/TURN dependency.

Online coverage preserves the complete existing RTC workflow.

Hybrid coverage proves one Meeting with physical location plus online Join, RTC controls, screen sharing, and authorized recording. An invitee who does not join RTC must not be classified as physically absent.

Recurring coverage proves coherent mode behavior across the series and occurrence/future/series edits without creating separate physical and online Meeting objects.

#### Recording

Cover:

- permission;
- organizer-only control;
- start;
- REC visible;
- pause;
- Paused visible;
- resume;
- stop;
- Processing;
- Ready;
- one final file;
- Files authorization;
- participant access;
- outside-Meeting share;
- no onward share;
- revoke;
- no Admin bypass;
- retention deletion;
- transcript deleted with recording;
- online Meeting recording under organizer/permission rules;
- hybrid Meeting recording under organizer/permission rules;
- no in-person Meeting recording controls;
- hybrid recording limited to media actually published into LiveKit.

#### Transcription

Using a deterministic fake provider:

- UI hidden when no provider active;
- provider enabled test configuration;
- manual request;
- queue;
- duplicate-request prevention;
- processing;
- success;
- failure/retry;
- optional timestamps/segments;
- transcript view;
- participant edit;
- version history;
- outside-Meeting share;
- recipient current-version-only;
- no recipient edit/re-share;
- Search authorization;
- no Admin bypass;
- no transcript action for an in-person Meeting without an eligible retained recording.

Do not install a production AI/STT provider merely to make tests pass.

### Browser quality

Protect:

- Chromium;
- Firefox;
- PL;
- EN;
- light;
- dark;
- mobile;
- keyboard/focus;
- no unexpected console errors;
- no uncaught runtime errors;
- no unexpected failed asset/API requests;
- no raw translation keys;
- no unresolved interpolation placeholders.

### Documentation closure

At Phase 31 completion update canonical documentation so current behavior is no longer described using the old "calls/video/screen sharing out of scope" contract.

Update production requirements consumed by Phase 40.

### Tasks

- [ ] Add realistic company-scale fixtures.
- [ ] Add cursor message history.
- [ ] Verify query/N+1 behavior.
- [ ] Verify bounded presence/typing.
- [ ] Add messaging multi-user E2E.
- [ ] Add group E2E.
- [ ] Add Team Chat E2E.
- [ ] Add Files/voice E2E.
- [ ] Add Calendar E2E.
- [ ] Add direct Call E2E.
- [ ] Add group Call E2E.
- [ ] Add Team Call E2E.
- [ ] Add device/pre-call E2E.
- [ ] Add Busy/Rejoin E2E.
- [ ] Add Meeting scheduling/invitation E2E.
- [ ] Add in-person Meeting mode/location/Calendar/chat E2E without RTC controls or dependency.
- [ ] Preserve online Meeting RTC E2E.
- [ ] Add hybrid Meeting location plus RTC E2E.
- [ ] Prove hybrid physical attendance is not inferred from RTC absence.
- [ ] Add recurring Meeting E2E.
- [ ] Add Meeting moderation E2E.
- [ ] Add attendance E2E.
- [ ] Add screen-share E2E.
- [ ] Add Meeting recording E2E.
- [ ] Prove recording is limited to RTC-enabled online/hybrid Meetings.
- [ ] Prove in-person Meetings expose no recording or transcription controls.
- [ ] Add recording sharing/retention E2E.
- [ ] Add provider-disabled transcription UI coverage.
- [ ] Add fake-provider transcription E2E.
- [ ] Add Search authorization E2E.
- [ ] Add browser-native notification coverage.
- [ ] Add critical mobile workflows.
- [ ] Cover PL/EN.
- [ ] Cover light/dark.
- [ ] Enforce console/runtime/request cleanliness.
- [ ] Run targeted backend tests.
- [ ] Run targeted frontend tests.
- [ ] Run Chromium Playwright.
- [ ] Run Firefox Playwright.
- [ ] Run complete required foundation quality gate.
- [ ] Update all affected canonical documentation.
- [ ] Confirm Phase 40 has not been implemented from this Phase 31 planning task.

---

## Explicit accepted decisions

The following decisions are binding:

1. Chat remains internal-company only.
2. Only active Atlas users participate.
3. No external/public guests.
4. Direct/group Chat remains global, not active-Team scoped.
5. Team Chat remains Teams-membership owned.
6. Meeting chat is a fourth system conversation type.
7. Recurring Meeting series shares one Meeting chat.
8. Ad-hoc Calls use the existing DM/group/Team conversation.
9. Direct audio/video Calls are supported.
10. Group ad-hoc audio/video Calls are supported.
11. Team ad-hoc audio/video Calls are supported.
12. Camera may be toggled during Calls/Meetings.
13. Users persist preferred camera/microphone/speaker.
14. Users persist outgoing start-with-camera preference.
15. Incoming video Call offers answer with or without camera.
16. Mic/camera permission is requested only after an explicit media action.
17. Pre-call/pre-Meeting device setup exists.
18. Devices may be changed while connected.
19. One user participates in one active RTC session at a time.
20. No call waiting/session switching.
21. One ad-hoc Call exists per conversation at a time.
22. Team Calls do not synchronously ring an entire large Team.
23. Screen sharing is supported.
24. Only one active screen share exists per live session.
25. Ad-hoc Calls cannot be recorded.
26. Only RTC-enabled online/hybrid Meetings may be recorded.
27. Meetings may be immediate or scheduled.
28. Recurring Meetings are supported.
29. Calendar is a shared Core capability, not Chat-owned.
30. Personal Calendar events are private.
31. Personal events have no invited users.
32. Personal events have no task-completed state.
33. Calendar has Month/Week/Day/Agenda.
34. Personal events support title/description/start/end/all-day/location/recurrence/reminders/Free-Busy.
35. No Calendar colors/categories are required.
36. Atlas continues using canonical Europe/Warsaw timezone behavior.
37. Free/Busy exposes availability only.
38. Scheduling conflicts warn but do not block.
39. Default reminder is 15 minutes.
40. User profile may change reminder default.
41. Per-event/Meeting reminders may override and may be multiple.
42. Meeting invitations may be accepted/declined.
43. RSVP may later change.
44. Every invited/participating user may invite another active Atlas user.
45. Newly invited users still receive their own RSVP.
46. Organizer may remove invitees.
47. No Meeting lobby.
48. Invited users may join before scheduled time.
49. Meeting may operate without organizer present.
50. Roles are organizer and participant only.
51. Participants in RTC-enabled online/hybrid Meetings may use microphone/camera/screen share.
52. Organizer may mute.
53. Normal mute allows self-unmute.
54. Organizer may disable microphone as a speaking ban.
55. Organizer may restore microphone permission.
56. Organizer never remotely enables another user's mic.
57. Organizer may turn off another user's camera.
58. Organizer never remotely enables another user's camera.
59. Organizer may stop another user's screen share.
60. Organizer may kick a participant from an RTC-enabled Meeting occurrence.
61. Kick bans the user for the remainder of the occurrence.
62. Kick removes active Meeting/chat access.
63. Organizer may lock/unlock an RTC-enabled live Meeting room.
64. Locked Meeting prevents joins and new invitations.
65. Organizer may End meeting for everyone.
66. Organizer Leave alone does not end Meeting.
67. Empty RTC session ends and releases media resources after 15 continuous minutes without ending the Meeting domain object.
68. RTC attendance stores online join/leave/duration without claiming physical presence or absence.
69. All authorized participants may see the available RTC-derived attendance without claims about physical presence.
70. RTC-enabled live session uses a minimizable modal.
71. Minimized persistent controls remain while navigating Atlas.
72. Browser refresh uses Rejoin rather than silent media reactivation.
73. LiveKit is self-hosted and Atlas-managed.
74. No LiveKit Cloud requirement.
75. No external-existing-LiveKit baseline mode.
76. LiveKit handles RTC media only.
77. Reverb remains message/application realtime.
78. LiveKit/Egress are separate services from PHP.
79. TURN/TLS is part of trusted LAN/VPN production planning.
80. No artificial Meeting participant limit.
81. Capacity failures must be visible.
82. Only organizer may control Meeting recording.
83. Recording additionally requires permission.
84. All participants see REC/Paused state.
85. No extra participant recording-consent modal is required.
86. Recording captures audio/video/screen share in one composed result.
87. Screen share is primary in recording layout while active.
88. Recording uses an Atlas-owned composite template.
89. User-facing pause/resume represents one logical recording.
90. Final playback/download is one file.
91. Processing state is acceptable.
92. Final recordings are Files-owned.
93. Recording retention is separate from Chat retention.
94. Recording retention defaults to null/indefinite.
95. Admin may configure finite recording retention.
96. No pre-retention user warning is required.
97. Lightweight recording metadata remains after heavy file deletion.
98. Transcript is deleted with its recording.
99. Recording participants may share recording with active Atlas users outside the Meeting.
100. Shared recipient does not become Meeting participant.
101. Share recipient cannot re-share.
102. Participant who created share may revoke it.
103. No public recording links.
104. Admin has no recording-content bypass.
105. Transcription is provider-neutral.
106. Atlas does not perform speech recognition itself.
107. No concrete production transcription provider is required in Phase 31.
108. Transcription always runs through queue/background processing.
109. Sync and async providers can be adapted.
110. Transcription is manual, not automatic.
111. Historical retained recordings may be transcribed later.
112. No duplicate active transcription job per recording.
113. Transcript UI is completely hidden when provider is disabled/unconfigured.
114. Provider result minimum is text.
115. Preserve timestamps/speaker data when returned.
116. Every authorized participant with permission may edit transcript.
117. Preserve original provider result and full transcript edit history.
118. Transcript share recipient sees only current version.
119. Transcript share recipient cannot edit or re-share.
120. Participants may share transcript to active Atlas users outside Meeting.
121. Admin has no transcript bypass.
122. Current transcript may participate in Meilisearch under strict authorization.
123. Transcript/search/share data is removed when recording retention deletes the recording.
124. Chat messages do not become Notifications records.
125. Chat messages are not emailed.
126. Meeting invitations/updates/cancellations may use email.
127. Meeting reminder emails are separately user-configurable.
128. Personal Calendar reminder email may be user-configurable.
129. Missed Calls use normal Notification/browser alert but no email.
130. No E2EE/application-level Chat content encryption is introduced.
131. Every Meeting mode is exactly `online`, `in_person`, or `hybrid`.
132. Meeting domain identity and lifecycle do not depend on LiveKit room existence.
133. Online Meetings preserve the complete accepted RTC behavior.
134. In-person Meetings are full Atlas Meetings without an RTC session or RTC dependency.
135. Hybrid Meetings are one Meeting with physical location and optional Atlas RTC.
136. Physical location is first-class Meeting product data for in-person/hybrid modes.
137. All modes preserve invitations, RSVP, recurrence, Calendar, reminders, cancellation, history, and Meeting chat.
138. In-person Meetings remain usable while LiveKit, TURN, or Egress is unavailable.
139. Recording is available only to RTC-enabled online/hybrid Meetings under the existing organizer/permission rules.
140. Hybrid recording includes only media actually published into LiveKit.
141. Transcription requires an eligible retained Meeting recording and an enabled provider.
142. In-person Meetings without recordings expose no transcription workflow.
143. RTC-derived attendance never proves physical absence.
144. Empty-room cleanup terminates RTC resources only, not the Meeting domain object.
145. Hybrid mode does not create separate physical and online Meeting records.
146. No automatic physical attendance subsystem is introduced.

---

## Explicit out of scope

Do not implement in Phase 31:

- public/external Chat users;
- external Meeting guests;
- anonymous participants;
- public Meeting links;
- public Internet conferencing product;
- friend/contact requests;
- message requests;
- user blocking;
- invite links for Chat groups;
- public Chat groups;
- group moderators/admins beyond owner/member;
- Meeting co-organizer role;
- Meeting presenter role;
- Meeting lobby/waiting room;
- recording of direct/group/Team ad-hoc Calls;
- simultaneous multiple screen shares;
- call waiting;
- holding/switching simultaneous Calls;
- public recording links;
- external recording recipients;
- external Calendar sync;
- Google Calendar integration;
- Outlook integration;
- CalDAV;
- ICS synchronization;
- calendar task manager/completed-task workflow;
- Calendar categories/colors;
- meeting-room inventory;
- room booking or resource reservation;
- room capacity management;
- physical office maps;
- room availability/conflict engine;
- NFC attendance;
- QR attendance or check-in;
- geolocation attendance;
- automatic physical-presence detection;
- conference-room hardware integration;
- SIP/PSTN room systems;
- automatic physical-room recording without media published into RTC;
- live speech transcription;
- microphone-to-transcript processing outside the retained-recording workflow;
- a second Meeting object for the physical side of a Hybrid Meeting;
- concrete OpenAI transcription provider;
- concrete Whisper/whisper.cpp provider;
- Python transcription service implementation;
- Atlas-owned speech recognition;
- automatic transcription of every recording;
- LiveKit Cloud requirement;
- selectable external existing LiveKit baseline;
- custom Atlas WebRTC/SFU server;
- bots;
- Chat webhooks;
- slash-command platform;
- GIF/Giphy integration;
- generic business-object conversations;
- business-module event delivery through Chat;
- external URL previews;
- multiple floating Chat windows;
- dedicated full-page Chat as primary UI;
- Chat message email;
- Chat persistence inside Notifications;
- E2EE;
- Chat-specific content cryptography.

Do not create another future phase for these items during this planning task.

---

## Permanent guardrails

- [ ] Exactly one canonical DM exists per unordered user pair.
- [ ] Direct/group Chat does not become active-Team scoped.
- [ ] Team Chat membership remains Teams-owned.
- [ ] Meeting conversation is system-owned and invitation-scoped.
- [ ] Recurring series has one shared Meeting chat.
- [ ] Meeting mode is explicitly `online`, `in_person`, or `hybrid`.
- [ ] Meeting domain state does not depend on LiveKit room existence.
- [ ] In-person Meetings never create LiveKit RTC sessions or participant tokens.
- [ ] Hybrid Meetings remain one Meeting rather than separate physical/online records.
- [ ] In-person Meetings remain usable while LiveKit, TURN, or Egress is unavailable.
- [ ] Meeting chat works independently from RTC mode and availability.
- [ ] Physical location is Meeting product data rather than RTC metadata.
- [ ] In-person Meetings expose no RTC device, screen-share, moderation, or rejoin controls.
- [ ] In-person Meetings expose no Atlas recording controls.
- [ ] Recording remains limited to RTC-enabled online/hybrid Meetings.
- [ ] Transcript creation requires an eligible retained recording.
- [ ] RTC attendance is never treated as proof of physical absence.
- [ ] The 15-minute empty-room rule terminates RTC resources rather than the Meeting domain object.
- [ ] No physical-room hardware, check-in, geolocation, or automatic attendance system is introduced.
- [ ] Admin never gains private Chat/Call/Meeting content access by status alone.
- [ ] Chat Search cannot leak unauthorized content.
- [ ] Reverb remains canonical message/application realtime.
- [ ] LiveKit remains RTC/media infrastructure only.
- [ ] LiveKit secret never reaches browser.
- [ ] Calls/Meetings cannot join unauthorized rooms.
- [ ] One user cannot remain actively connected to multiple RTC sessions.
- [ ] Ad-hoc Calls cannot invoke recording.
- [ ] Meeting recording requires organizer + permission.
- [ ] Recording participants always see REC/Paused state.
- [ ] Final recording is one Files-owned artifact.
- [ ] Egress staging is not permanent content storage.
- [ ] Recording share does not grant Meeting membership.
- [ ] Recording share recipient cannot re-share.
- [ ] Recording retention does not leave orphan Files.
- [ ] Recording deletion also deletes transcript content/shares/Search.
- [ ] Transcript does not exist without a retained source recording.
- [ ] Transcription cannot run synchronously in user HTTP request.
- [ ] No concrete STT provider is required for baseline completion.
- [ ] User transcript UI is absent while provider is disabled.
- [ ] Transcript edit history cannot be silently overwritten.
- [ ] Transcript share recipient cannot see edit history.
- [ ] Transcript Search cannot bypass access.
- [ ] Personal Calendar event contents remain private.
- [ ] Free/Busy does not expose event details.
- [ ] Europe/Warsaw recurrence behavior remains consistent.
- [ ] Meeting lock prevents new joins/invites.
- [ ] Kick blocks rejoin for the current occurrence.
- [ ] Organizer cannot remotely enable another user's mic/camera.
- [ ] Only one screen share is active.
- [ ] Empty RTC rooms release live-media resources after 15 continuous minutes without ending the Meeting domain object.
- [ ] Chat messages remain outside Notifications persistence/email.
- [ ] Calls/Meetings do not introduce application-level E2EE.
- [ ] No public guest/public Meeting mode appears.
- [ ] PL/EN/light/dark/mobile/accessibility/browser-quality guards remain intact.

---

## Completion criteria

Phase 31 is complete only when:

- [ ] original Chat messaging scope is complete;
- [ ] direct/group/Team conversations work;
- [ ] Meeting conversation type works;
- [ ] messaging/replies/edits/history/reactions/mentions/forwarding/pins/bookmarks/drafts work;
- [ ] Files attachments and voice messages work;
- [ ] Reverb messaging/presence/read/unread/reconnect works;
- [ ] Core Calendar works for private personal events;
- [ ] Month/Week/Day/Agenda work;
- [ ] recurrence/reminders/Free-Busy work;
- [ ] direct audio/video Calls work;
- [ ] group Calls work;
- [ ] Team Calls work;
- [ ] device preferences and pre-call setup work;
- [ ] only one active RTC session per user is enforced;
- [ ] Call history/missed Calls work;
- [ ] self-hosted LiveKit development/runtime integration works;
- [ ] Calls/Meetings remain authorization-safe;
- [ ] Meetings can be immediate or scheduled;
- [ ] online Meetings preserve the accepted RTC workflow;
- [ ] in-person Meetings work without LiveKit, TURN, Egress, online Join, or media controls;
- [ ] hybrid Meetings combine one physical location with an Atlas RTC option;
- [ ] Calendar clearly presents Meeting mode and physical location where applicable;
- [ ] every Meeting mode preserves invitations, RSVP, recurrence, reminders, cancellation, and Meeting chat;
- [ ] recurring Meetings work;
- [ ] invitation/RSVP/invite-more/remove behavior works;
- [ ] Meeting chat works before/during/after;
- [ ] participant moderation works;
- [ ] one screen share works;
- [ ] lock/kick/End/empty-room behavior works;
- [ ] RTC attendance works without falsely inferring physical presence or absence;
- [ ] live modal may be minimized and rejoined;
- [ ] ad-hoc Calls cannot be recorded;
- [ ] Meeting recording start/pause/resume/stop works only for RTC-enabled online/hybrid Meetings;
- [ ] in-person Meetings expose no recording controls;
- [ ] LiveKit Egress composite recording works;
- [ ] final recording is one Files-owned file;
- [ ] recording sharing works without Meeting-membership leakage;
- [ ] separate recording retention works;
- [ ] provider-neutral transcription boundary exists;
- [ ] transcription remains queued;
- [ ] no concrete production STT provider is required;
- [ ] transcript UI is absent with no provider;
- [ ] transcription requires an eligible retained Meeting recording;
- [ ] in-person Meetings without recordings expose no transcription workflow;
- [ ] fake-provider tests prove transcription lifecycle;
- [ ] transcript editing/versioning/sharing works under authorization;
- [ ] transcript is removed with recording retention;
- [ ] Meilisearch does not leak Chat/Meeting/transcript content;
- [ ] Admin remains unable to read private communication content;
- [ ] safe operational RTC/Egress/transcription aggregates exist;
- [ ] Notifications/browser/email preferences follow accepted ownership;
- [ ] participant-authorized exports remain functional;
- [ ] Chromium and Firefox cover critical workflows;
- [ ] browser tests cover online, in-person, hybrid, and recurring mode behavior;
- [ ] mobile flows are covered;
- [ ] Polish and English UI is complete;
- [ ] light/dark UI is covered;
- [ ] console/runtime/request cleanliness remains protected;
- [ ] complete required foundation quality gates pass;
- [ ] canonical documentation reflects the final contract;
- [ ] Phase 40 deployment has not been implemented as part of this Phase 31 planning task.
