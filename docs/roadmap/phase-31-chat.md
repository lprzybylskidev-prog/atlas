# Phase 31 — Optional internal company chat and realtime messaging

**Status:** `not started`

## Objective

Implement a complete optional internal-company Chat module for Atlas.

Chat exists only for human-to-human communication between Atlas users.

It is not a business-module event bus, generic conversation-context framework, social network, public messaging platform, bot platform, or replacement for the Notifications module.

System and business-module communication continues to use the existing Notifications module.

Chat must provide:

- canonical direct conversations;
- user-created group conversations;
- system-owned Team conversations;
- realtime messaging;
- presence;
- typing indicators;
- delivery and read state;
- unread state;
- message editing and edit history;
- replies and quotes;
- reactions;
- mentions;
- forwarding;
- pinned messages;
- private bookmarks;
- backend-backed drafts;
- Files-owned attachments;
- voice messages;
- Media / Files / Links browsing;
- full-text Search through the existing Meilisearch foundation;
- browser-native alerts;
- user presence/status information;
- participant-authorized exports;
- configurable retention;
- Admin operational visibility without Admin access to private conversation content.

The implementation must remain appropriate for an internal company deployment of roughly 400 users while allowing reasonable future growth.

Do not create an artificial 400-user product limit.

## Dependencies

Phase 31 builds on the already completed Atlas foundations, including:

- modular architecture and public contracts;
- Authorization and Teams;
- PostgreSQL module schemas;
- Audit;
- Settings and Localization;
- Sessions and active team;
- Module availability and activation;
- Notifications and realtime foundation;
- Admin operations and health;
- Files and ClamAV;
- Search and Meilisearch;
- shared frontend and UI foundations;
- export/PDF foundations;
- queues and scheduler;
- Phase 28 foundation repair;
- Phase 29 foundation acceptance closure.
- Phase 30 Authorization, Team Structure, and mutation feedback repair.

Chat must not depend on TimeTracking or future debt-collection business modules.

## Related documentation

Create and maintain canonical Chat module documentation under the existing `docs/modules/` convention.

Update canonical realtime/browser documentation where this phase extends the existing server-push architecture.

Link existing canonical Files, Search, Teams, Module Activation, Audit, Settings, and export documentation instead of duplicating their contracts.

## Fundamental implementation boundary

Atlas Chat is implemented as an Atlas-owned module.

Do not install or use a ready-made Chat product or Chat framework.

Do not use:

- Wirechat as a runtime dependency;
- Chatify;
- a hosted Chat SaaS;
- a third-party Chat backend;
- a third-party Chat UI framework;
- an external messaging SDK that owns the Chat domain.

Wirechat may be treated only as a historical feature-comparison reference.

Do not copy its source or architecture.

Reuse existing Atlas foundations instead of rebuilding them.

Laravel Reverb and Laravel broadcasting are accepted infrastructure for the realtime transport layer.

Reverb is not a ready-made Chat implementation.

Do not build a custom WebSocket server.

Do not introduce a second competing realtime architecture.

---

## P31-W01 — Module boundary, activation, persistence, and permissions

### Contract

Chat is a fully optional Atlas module and must use the canonical Module Availability and ModuleGate foundations.

Do not build Chat-specific substitutes for existing module activation.

Chat owns its persistence in the appropriate Chat PostgreSQL module schema and must respect the established module ownership rules.

Cross-module behavior must use existing public contracts.

Do not import another module's internals merely because Chat needs Files, Search, Teams, Audit, Settings, exports, or realtime behavior.

### Activation behavior

Global Chat deactivation must:

- hide Chat launchers and Chat UI;
- block Chat HTTP/application endpoints;
- block Chat realtime channels;
- prevent sending new Chat messages;
- preserve all historical Chat data.

Normal direct and user-created group conversations are global to the Atlas user account.

They are not owned by the currently active Team.

Changing the active Team must not duplicate, switch, or hide normal direct/group conversations.

System Team conversations are Team-scoped.

If Chat is unavailable or inactive for a Team:

- that Team conversation is not usable;
- its history is preserved;
- reactivation returns the same canonical Team conversation.

### Permissions

Add canonical Chat permissions using the existing Authorization foundations.

At minimum, cover permissions for:

- using Chat;
- starting direct conversations;
- creating groups;
- uploading Chat attachments;
- sending voice messages;
- Chat operational Admin access;
- Chat retention administration.

Starter roles should normally allow standard employees to use Chat, direct messages, groups, attachments, and voice messages unless explicitly restricted by project permissions.

There must never be an application permission equivalent to:

`chat.admin.read`

that grants an Administrator general access to private Chat content.

### Tasks

- [ ] Create the optional Chat module using canonical Atlas module boundaries.
- [ ] Register Chat availability and activation.
- [ ] Define Chat persistence ownership and PostgreSQL schema usage.
- [ ] Define only the required public contracts.
- [ ] Add the canonical Chat permission catalog.
- [ ] Add appropriate starter-role Chat permissions.
- [ ] Gate Chat UI, HTTP/application actions, Search, and realtime channels.
- [ ] Keep direct/group scope independent from active Team.
- [ ] Apply Team activation semantics only to Team conversations.
- [ ] Add module-boundary and architecture guardrails.
- [ ] Create canonical Chat module documentation.

---

## P31-W02 — Direct conversations, groups, membership, and Team conversations

### Conversation types

Chat supports exactly three core conversation types:

- `direct`;
- `group`;
- `team`.

Do not add a generic business-object conversation context.

Do not add case/customer/task/process conversations.

If users need to discuss a business object, they may send its ID or normal Atlas link in a regular conversation.

Business modules continue to communicate system events through Notifications.

### Direct conversations

Every unordered pair of users has exactly one canonical direct conversation.

Do not allow multiple independent 1:1 conversations for the same pair.

Canonical direct-conversation uniqueness must remain safe under concurrent creation.

Every active Atlas user may start a direct conversation with every other active Atlas user, regardless of Team membership.

Do not implement:

- friend requests;
- message requests;
- contact approval;
- user blocking.

Atlas Chat is an internal company tool.

An inactive user cannot actively participate in new messaging, but historical messages remain.

Reactivating the same account returns access to the same canonical historical direct conversation where authorization permits.

### User-created groups

A group has:

- one owner;
- zero or more additional members;
- a name;
- an optional avatar;
- historical membership records.

Group roles are exactly:

- `owner`;
- `member`.

Do not add:

- admin;
- moderator;
- custom per-conversation roles.

The owner may:

- rename the group;
- change its avatar;
- add members;
- remove members;
- transfer ownership.

Adding a user does not require approval.

Do not implement:

- invite links;
- public links;
- join requests;
- invitation acceptance.

A normal member may leave the group at any time.

An owner must transfer ownership before leaving when another active member remains.

If the owner is the last remaining member, the owner may leave and the conversation becomes closed/inactive while remaining historically preserved.

Do not destructively delete the conversation when the last participant leaves.

A newly added member may see the available conversation history from the beginning.

Historical membership changes must remain traceable.

### System Team conversations

Every Team for which Chat is active has exactly one canonical system Team conversation.

The Team conversation:

- is created idempotently;
- is system-owned;
- has no human owner;
- cannot be manually deleted;
- cannot be manually left;
- does not allow manual membership management from Chat;
- derives active membership from canonical Teams membership;
- synchronizes membership changes from Teams;
- preserves historical membership/conversation information;
- derives its identity/name from the Team;
- is not affected by head-manager changes;
- gives managers no special Chat authority.

Team Structure remains the canonical source for Team membership.

Chat must never become a second Team membership editor.

### System timeline entries

Conversation timelines should contain immutable system entries for relevant structural events such as:

- member added;
- member removed;
- member left;
- ownership transferred;
- group renamed;
- group avatar changed;
- Team membership synchronized;
- conversation closed.

System timeline entries are not Notifications records.

### Tasks

- [ ] Implement direct, group, and Team conversation models.
- [ ] Enforce one canonical direct conversation per user pair.
- [ ] Add concurrency protection for direct-conversation creation.
- [ ] Support cross-Team direct conversations.
- [ ] Implement group ownership and membership history.
- [ ] Implement owner/member invariants.
- [ ] Implement ownership transfer.
- [ ] Implement member removal and voluntary leave.
- [ ] Implement safe last-member group closure.
- [ ] Implement one canonical system Team conversation per enabled Team.
- [ ] Synchronize Team conversation membership from canonical Teams membership.
- [ ] Reject manual Team-conversation membership mutation.
- [ ] Preserve historical membership records.
- [ ] Add immutable system timeline entries.
- [ ] Add authorization, membership, and concurrency tests.

---

## P31-W03 — Messages, editing, replies, reactions, forwarding, pins, bookmarks, and drafts

### Core message behavior

Chat messages support:

- plain text;
- multiline text;
- Unicode emoji;
- safe links;
- Markdown-lite formatting;
- reply/quote;
- edit;
- edit history;
- delete-for-me;
- reactions;
- mentions;
- forwarding;
- pins;
- private bookmarks;
- drafts.

### Markdown-lite

Support safe lightweight formatting for:

- bold;
- italic;
- inline code;
- fenced/code blocks;
- lists;
- block quotes;
- links;
- emoji.

Do not support raw untrusted HTML.

Do not introduce a large rich-text editor framework when the accepted Markdown-lite contract is sufficient.

Rendered content must be safe from script/content injection.

### Reply / quote

A reply references one existing message.

Render a clear quoted/reference fragment.

Do not implement nested Slack-style threads.

### Editing

The author may edit their own message without an arbitrary time limit.

Edited messages must visibly show an `edited` state.

Preserve historical message versions.

Authorized conversation participants may inspect the edit history.

Admin status alone never grants edit-history access.

Protect edits against stale concurrent updates using optimistic concurrency or an equivalent explicit version check.

### Delete for me

The only normal user-facing message removal action is:

`Delete for me`.

Do not implement:

- delete for everyone;
- moderator message deletion;
- privileged Admin deletion of arbitrary conversation messages.

Delete-for-me changes only that user's visibility.

Other participants continue to see the original content.

For the user who deleted the message, keep a tombstone such as:

`Message deleted`.

Delete-for-me must also be respected by:

- Search;
- exports;
- content viewers.

It must not physically remove the message solely because one participant hid it.

Global lifecycle deletion caused by retention is a separate operation.

### Reactions

Support emoji reactions.

A participant may toggle supported emoji reactions on a message.

Enforce uniqueness for the same participant/message/emoji combination.

Do not build a social-media reaction subsystem.

### Mentions

Support:

- individual user mentions;
- `@everyone` with localized UI wording;
- `@online`.

Every active participant may use group mentions.

Mentions remain Chat behavior.

They do not create normal Notifications-module records or email delivery.

### Forwarding

A participant may forward a message or attachment to another conversation that the participant is authorized to access.

Forwarding creates a new destination message.

Do not leak private source-conversation information to destination participants.

Do not expose:

- private source conversation names;
- source member lists;
- source authorization metadata.

### Pins

Every active participant may pin or unpin messages.

Expose a conversation-level pinned-message view.

### Bookmarks

A user may privately bookmark a message.

Bookmarks:

- belong only to that user;
- are not visible to other participants;
- do not alter shared conversation state.

### Drafts

Maintain one draft per user per conversation.

Drafts should survive:

- moving between conversations;
- closing the Chat modal;
- navigating to another Atlas area.

Do not persist Chat draft content in `localStorage` or `sessionStorage`.

Drafts may contain company/business data and must use backend-owned storage.

### Message idempotency

Prevent duplicate messages caused by:

- double-click/send;
- network retry;
- WebSocket reconnect;
- stale frontend retry.

Use an explicit client/idempotency identifier or equivalent canonical mechanism.

### Tasks

- [ ] Implement the canonical message model.
- [ ] Implement safe Markdown-lite rendering.
- [ ] Implement reply/quote.
- [ ] Implement unlimited author editing.
- [ ] Show the edited state.
- [ ] Preserve and expose participant-authorized edit history.
- [ ] Protect message editing against stale concurrent writes.
- [ ] Implement delete-for-me with a per-user tombstone.
- [ ] Ensure delete-for-me is respected by Search and exports.
- [ ] Implement emoji reactions.
- [ ] Implement individual, everyone, and online mentions.
- [ ] Implement authorization-safe forwarding.
- [ ] Implement pinned messages.
- [ ] Implement private bookmarks.
- [ ] Implement backend-owned conversation drafts.
- [ ] Add message-send idempotency.
- [ ] Add message behavior and negative authorization tests.

---

## P31-W04 — Files, attachments, content viewer, and voice messages

### Files ownership

Chat must not implement a separate file-storage subsystem.

Every Chat attachment must use the existing Files module through its canonical public contracts.

Reuse existing global policy for:

- file-size limits;
- allowed content/MIME types;
- validation;
- authorization;
- quarantine;
- ClamAV scanning;
- retention participation;
- download/preview handling.

Do not add separate Chat file policy unless a concrete Chat-specific requirement cannot be represented by the canonical Files policy.

### ClamAV

Every Chat attachment must pass through the existing Files/ClamAV lifecycle.

Attachments must not become available to conversation participants before they are accepted by the existing scanner workflow.

This applies to:

- normal uploaded files;
- images;
- PDFs;
- audio;
- video;
- voice messages;
- clipboard-pasted files/images;
- drag-and-drop uploads.

### Upload UX

Support:

- file picker;
- drag and drop;
- clipboard paste, including screenshots;
- upload progress;
- scanner/quarantine state;
- upload/scan failure state;
- retry only where allowed by existing Files behavior.

### Conversation content viewer

Conversation details must provide:

- Media;
- Files;
- Links.

Media includes appropriate accessible rendering for:

- images;
- supported audio;
- supported video;
- voice messages.

Use existing Files preview/download authorization.

Do not fetch third-party page metadata for links.

Do not implement automatic external URL previews.

The Links view lists only URLs contained in messages that the current participant is allowed to see.

### Voice messages

Voice messages are in scope.

Maximum recording length:

15 minutes.

Required UX:

1. start recording;
2. stop recording;
3. preview/listen;
4. send;
   or
5. discard and record again.

Do not automatically send a recording when recording stops.

Voice messages must:

- use the supported browser microphone/MediaRecorder capability;
- request microphone access through an explicit user action;
- become Files-owned attachments;
- pass through the canonical Files/ClamAV lifecycle;
- follow the global Files size/content policy;
- have an accessible audio player;
- follow normal conversation authorization;
- follow Chat retention.

Audio calls, video calls, and screen sharing are outside this phase.

Do not create a future calls phase in this task.

### Tasks

- [ ] Integrate Chat attachments through Files public contracts.
- [ ] Preserve the existing Files validation and authorization lifecycle.
- [ ] Enforce ClamAV/quarantine before participant access.
- [ ] Implement file-picker attachment upload.
- [ ] Implement drag-and-drop upload.
- [ ] Implement clipboard-paste upload.
- [ ] Implement upload, scan, failure, and retry states.
- [ ] Implement Media / Files / Links conversation browsing.
- [ ] Implement safe existing image/PDF/audio/video preview behavior.
- [ ] Implement voice recording with a 15-minute maximum.
- [ ] Implement stop, preview, send, discard, and re-record behavior.
- [ ] Persist voice messages through Files.
- [ ] Add attachment and scanner authorization tests.
- [ ] Add browser coverage for representative attachments and voice recording.

---

## P31-W05 — Realtime, Laravel Reverb, presence, typing, delivery, and read state

### Realtime architecture

Chat is a genuine server-push workflow.

Extend the existing Atlas realtime foundation using canonical Laravel broadcasting and Laravel Reverb.

Do not build:

- a custom WebSocket server;
- a Chat-specific realtime stack;
- a hosted Chat transport;
- a second competing event architecture.

Integrate Reverb with the existing Atlas network/realtime contracts.

### Source of truth

WebSockets are a delivery mechanism, not the authoritative data store.

Authoritative Chat state remains application/persistence owned.

After disconnect/reconnect, the browser must reconcile authoritative state and retrieve anything it missed.

A temporary realtime failure must not:

- lose persisted messages;
- create duplicate messages;
- produce permanently incorrect unread/read state.

### Channel authorization

All private conversation channels must be explicitly authorized.

A user may subscribe only to:

- permitted user-level Chat events;
- conversations they are authorized to access;
- permitted Team conversations.

Knowing or guessing a conversation identifier must never grant channel access.

### Presence

Support:

- online;
- offline;
- last seen.

Avoid unnecessary high-frequency persistent writes for presence/last-seen updates.

Use a bounded/coalesced mechanism appropriate for hundreds of users.

### Manual user status

Support manual presence labels:

- Available;
- Busy;
- Do not disturb;
- Out of office;
- optional custom text and/or emoji.

These are informational status labels.

`Do not disturb` does not implement message muting or delivery suppression.

Message delivery, unread state, and browser alerts remain controlled by their own contracts/preferences.

### Typing indicators

Show typing state such as:

`Jan is typing…`

Typing state is ephemeral.

Do not persist it to the database.

Do not put typing events in Audit.

Typing state must expire automatically.

### Delivery and read state

Direct conversations support meaningful:

- sent;
- delivered;
- read

state.

Group and Team conversations must allow a participant to inspect who has read a message.

Do not create a wasteful permanent receipt row for every user/message combination when a conversation-membership cursor model can satisfy the behavior.

Prefer scalable per-membership read/delivery cursors where appropriate.

### Unread behavior

Support:

- unread count;
- new-message separator;
- last-read cursor;
- mark as unread;
- realtime unread updates.

### Tasks

- [ ] Integrate Laravel Reverb with the existing realtime foundation.
- [ ] Add authorized private/presence Chat channels.
- [ ] Add reconnect and authoritative-state reconciliation.
- [ ] Protect message delivery against duplicate retries.
- [ ] Implement online/offline/last-seen presence.
- [ ] Implement manual user status.
- [ ] Implement ephemeral typing indicators.
- [ ] Implement direct-message delivery/read state.
- [ ] Implement group/Team read visibility.
- [ ] Implement unread counters and new-message separator.
- [ ] Implement mark-as-unread.
- [ ] Add multi-browser-context realtime tests.
- [ ] Add unauthorized-channel negative tests.
- [ ] Update canonical realtime/network documentation.

---

## P31-W06 — Chat launcher, unread dropdown, modal UI, responsive behavior, and conversation navigation

### Shell integration

The primary Chat interface is not a dedicated main-navigation application page.

Chat is opened through a shell-level launcher.

Place a small canonical Chat control near existing shell controls such as language/theme controls, following the current Atlas layout rather than creating a separate navigation architecture.

The launcher:

- is visible only when Chat is available to the current user;
- exposes an unread badge;
- opens the Chat modal.

### Chat unread dropdown

Chat owns a separate unread-message dropdown.

Its visual language should intentionally match the existing Notifications dropdown so the shell feels consistent.

Chat unread entries are not Notifications-module records.

Show one unread entry per conversation, not one row for every unread message.

Example:

`Jan Kowalski — 4 unread messages`

Include an appropriate preview of the latest visible message.

Clicking an unread entry:

- opens the Chat modal;
- opens the exact conversation.

After the conversation is actually read, its unread dropdown entry disappears.

If the user later marks that conversation unread, it appears again.

The Chat launcher badge represents the total Chat unread-message count.

### Desktop modal

Use one substantial Chat modal/window.

Do not implement multiple Messenger-style floating chat windows.

Only one conversation is active at a time.

The desktop layout may include:

- conversation list;
- active conversation;
- details/content section where appropriate.

### Mobile

On mobile, Chat becomes a full-screen interaction.

Use a simple navigation model:

conversation list
→
conversation
→
back

Composer, attachments, content viewer, voice messages, and details must remain usable on touch devices.

### Conversation list views

Provide at least:

- All;
- Unread;
- Direct;
- Groups;
- Team.

Do not add Archive.

Archiving conversations is intentionally outside scope.

### Favorites

Users may favorite/star conversations.

Favorites should be surfaced prominently at the top of the relevant conversation list.

### Conversation list metadata

Where applicable show:

- avatar;
- display name;
- last-message preview;
- timestamp;
- unread count;
- DM presence;
- favorite state;
- Team/system marker.

### Conversation view

The main conversation UX includes:

- timeline;
- composer;
- reply state;
- attachment actions;
- voice recording;
- typing state;
- delivery/read state;
- pinned messages;
- bookmarks;
- content viewer;
- conversation details.

### Accessibility

Follow existing Atlas UI/accessibility contracts for:

- keyboard navigation;
- focus management;
- focus trapping;
- focus restoration;
- Escape behavior;
- screen-reader labels;
- logical tab order;
- mobile touch targets.

### Tasks

- [ ] Add a shell-level Chat launcher near existing shell controls.
- [ ] Add the Chat unread badge.
- [ ] Add the separate Chat unread dropdown.
- [ ] Use one unread entry per conversation.
- [ ] Open the exact conversation from unread entries.
- [ ] Implement the desktop Chat modal.
- [ ] Implement one active conversation at a time.
- [ ] Implement full-screen mobile Chat behavior.
- [ ] Implement All / Unread / Direct / Groups / Team views.
- [ ] Implement favorite/starred conversations.
- [ ] Implement the conversation list and metadata states.
- [ ] Implement the main conversation timeline/composer UI.
- [ ] Implement conversation details, pins, bookmarks, and content-viewer integration.
- [ ] Add keyboard/focus/accessibility behavior.
- [ ] Add light/dark theme coverage.
- [ ] Add responsive/mobile coverage.

---

## P31-W07 — Browser-native notifications and preferences

### Boundary with Notifications

Chat messages do not become normal Notifications-module records.

Do not:

- create an in-app Notifications record for every Chat message;
- send Chat messages by email;
- send Chat mentions by email.

The Chat unread dropdown remains a separate Chat-owned mechanism.

### Shared browser-native capability

Add one shared browser-native notification delivery mechanism that may be used by:

- existing Atlas system Notifications;
- Chat new/unread-message alerts.

This is a shared browser delivery capability.

It does not merge the Chat persistence/domain with Notifications persistence.

### Configuration and user control

Browser-native notifications have three control layers:

1. application-level configuration;
2. per-user preference;
3. browser permission.

Application configuration may globally disable browser-native notifications.

If globally disabled:

- do not ask for browser permission;
- do not attempt native delivery.

The user settings must allow the user to control browser-native alerts.

Provide independent user preferences for at least:

- system Notifications browser alerts;
- Chat browser alerts.

A global user master toggle may also be used if it does not remove the independent controls.

Browser permission remains controlled by the browser:

- default;
- granted;
- denied.

### Permission UX

Do not trigger an aggressive permission prompt automatically on every visit.

Request browser permission only after an intentional user action in the relevant preference UI.

Clearly expose states such as:

- disabled by application;
- disabled by user;
- permission not requested;
- permission denied;
- permission granted.

### Native Chat alert behavior

A Chat browser notification may contain only an appropriate minimum preview.

Clicking the browser notification must:

- focus/open Atlas;
- open the Chat modal;
- navigate to the correct conversation.

No dedicated Chat page is required.

### Tasks

- [ ] Add application-level browser-native notification configuration.
- [ ] Add per-user browser-native notification settings.
- [ ] Allow independent system-Notification and Chat browser alert preferences.
- [ ] Add explicit browser-permission UX.
- [ ] Add one shared browser-native notification frontend service.
- [ ] Integrate existing system Notifications with that browser capability without changing their canonical ownership.
- [ ] Integrate Chat new/unread-message alerts.
- [ ] Keep Chat out of Notifications persistence and email delivery.
- [ ] Open the correct Chat conversation from native Chat alerts.
- [ ] Add browser-permission and browser-alert tests.

---

## P31-W08 — Search and Meilisearch

### Search foundation

Use the existing Atlas Search/Meilisearch foundation.

Do not create a second full-text search engine for Chat.

Chat search should cover:

- conversations;
- users;
- message text;
- attachment filenames;
- links.

Support useful filtering by:

- person/author;
- conversation;
- date;
- content/attachment type.

### Critical authorization invariant

Meilisearch must never disclose any Chat data from a conversation that the current user is not authorized to access.

This includes even partial result metadata such as:

- message body;
- snippet;
- attachment filename;
- link;
- conversation name;
- matched text;
- participant metadata.

Admin status does not bypass this rule.

There is no privileged Admin Chat-content search.

Authorization must be enforced before rendering any result content.

### Visibility changes

Search visibility must react correctly to:

- group member added;
- group member removed;
- participant leaving;
- Team membership changes;
- Team Chat activation changes;
- user deactivation;
- delete-for-me;
- retention cleanup.

A newly added group member may search the history that the member is allowed to see from the beginning.

A removed participant must not continue receiving new search results from a conversation they no longer have access to.

Delete-for-me must not be bypassed through Search.

### Index lifecycle

Keep Search projection synchronized with relevant events such as:

- new message;
- message edit;
- retention deletion;
- attachment metadata change;
- conversation metadata change;
- visibility/membership changes.

Do not index unnecessary secrets or unrelated data.

### Tasks

- [ ] Add the Chat Search/Meilisearch projection.
- [ ] Search conversations and users.
- [ ] Search message content.
- [ ] Search attachment filenames and links.
- [ ] Add author/conversation/date/type filters.
- [ ] Enforce participant-only Search authorization.
- [ ] Prevent Admin Chat-content Search bypass.
- [ ] Prevent delete-for-me leakage through Search.
- [ ] Update Search visibility when membership changes.
- [ ] Synchronize message edits and retention with the index.
- [ ] Add negative/mutation tests proving cross-conversation search leakage fails.
- [ ] Add Search integration and browser coverage.

---

## P31-W09 — Retention, privacy boundary, Admin operations, and Audit

### Retention configuration

Chat retention is configurable.

The canonical default is:

`null`

meaning:

`retain indefinitely`.

A configured positive day count means automatic Chat retention after that age.

Do not invent a finite default period.

### Retention lifecycle

Automatic retention must consistently cover Chat-owned and Chat-related data, including as applicable:

- messages;
- edit history;
- reactions;
- pins;
- bookmarks whose source no longer exists;
- system timeline entries according to the canonical retention policy;
- Search projections;
- Files attachments;
- voice messages;
- dependent Chat records.

Do not leave orphaned Files or stale Meilisearch documents.

A long retention operation should reuse existing queue/managed-process foundations rather than block an HTTP request.

### Admin retention panel

Provide an Admin operational Chat-retention surface.

It may expose aggregate metadata such as:

- current retention policy;
- total conversation count;
- total message count;
- attachment/storage usage;
- eligible message count;
- eligible Files size;
- last cleanup;
- cleanup status.

Admin may perform controlled actions such as:

- run the configured retention policy now;
- purge data older than an explicitly selected cutoff/date.

Manual retention actions must:

- require the appropriate Admin permission;
- use the existing high-risk confirmation/reauthentication pattern where appropriate;
- preview/count affected data before execution;
- be audited;
- use a managed process where the operation is large;
- never expose private conversation contents merely to perform retention.

### Application privacy boundary

An Atlas Administrator does not gain access to private conversation content through the application.

Admin status alone must never permit an Administrator to:

- open another user's direct conversation;
- open a private group solely because they are an Administrator;
- read message bodies;
- inspect message edit history;
- listen to private voice messages;
- download private Chat attachments;
- search private Chat contents;
- export private conversations.

Direct database/root/infrastructure access is outside the Atlas application authorization model and is governed by organizational procedures.

Do not attempt to cryptographically protect Chat from an authorized infrastructure/root administrator in this phase.

### Encryption boundary

Do not implement:

- Chat-specific application-level message encryption;
- per-message cryptographic envelopes;
- end-to-end encryption;
- conversation key management;
- device key management.

Production encryption at rest belongs to the production deployment/storage phase and protects Atlas persistent storage as a whole.

### Admin operational visibility

Admin may inspect aggregate technical Chat metadata required for operations, such as:

- Chat module health;
- Reverb/realtime health;
- failed Chat jobs;
- conversation count;
- message count;
- Files/storage usage;
- voice/media usage;
- Search index health;
- retention state;
- last retention cleanup.

Prefer aggregates.

Do not expose private conversation names or participant lists without a concrete operational need.

### Audit

Audit structural and administrative Chat operations, for example:

- group created;
- membership changed;
- ownership transferred;
- group closed;
- retention configuration changed;
- manual retention started/completed;
- privileged operational action.

Do not copy normal Chat content into Audit.

Audit must not store:

- normal message bodies;
- voice content;
- attachment content;
- every ordinary Chat message.

Use safe identifiers and metadata only where needed for traceability.

### Historical employee identity

Deactivating or removing an employee account does not automatically anonymize historical Chat content.

Historical messages remain attributable to their historical employee identity.

The UI may mark such a user as inactive.

Do not apply debtor anonymization rules automatically to employee Chat history.

### Tasks

- [ ] Add nullable automatic Chat-retention configuration.
- [ ] Default Chat retention to indefinite.
- [ ] Add scheduled retention when a finite policy is configured.
- [ ] Coordinate retention across Chat data, Search, and Files.
- [ ] Add the Admin Chat-retention operations surface.
- [ ] Add retention preview/count behavior.
- [ ] Add controlled manual purge-by-cutoff.
- [ ] Add high-risk confirmation where required.
- [ ] Audit structural and retention operations without copying message content.
- [ ] Add Chat aggregate health/usage visibility.
- [ ] Prevent Admin application access to private Chat content.
- [ ] Preserve historical employee identity.
- [ ] Add privacy and negative authorization tests.
- [ ] Add retention lifecycle tests.

---

## P31-W10 — Participant-authorized exports and conversation history

### Export contract

A conversation participant may export a conversation that the participant is currently authorized to access.

Reuse the existing Atlas export/PDF foundation.

Support appropriate existing export formats, including:

- PDF;
- CSV;
- JSON.

Do not build a separate Chat export engine if the existing Core export foundation already provides the required mechanisms.

Admin status alone does not grant export access to conversations the Administrator does not participate in.

Exports must respect:

- conversation authorization;
- delete-for-me state;
- retention state;
- message history;
- attachment authorization.

Exports must not expose private source metadata from forwarded messages.

### Tasks

- [ ] Integrate Chat exports with the existing export foundation.
- [ ] Support participant-authorized PDF export.
- [ ] Support participant-authorized CSV export where appropriate.
- [ ] Support participant-authorized JSON export.
- [ ] Respect delete-for-me and retention visibility.
- [ ] Preserve safe message/edit-history representation.
- [ ] Prevent privileged Admin export bypass.
- [ ] Add export authorization and output tests.

---

## P31-W11 — Scale, performance, full browser workflows, and acceptance closure

### Scale target

The known deployment target is approximately 400 users with expected future growth.

Do not design the module around a tiny-team assumption.

Do not add an artificial maximum-user cap.

At the same time, do not overengineer for Internet-scale social-network traffic.

### Required performance behavior

Message history must use cursor-based or equivalently stable incremental pagination.

Do not load the full conversation history into memory or browser state.

Avoid obvious N+1 behavior for:

- participants;
- unread state;
- reactions;
- read state;
- attachments;
- conversation list.

Do not implement per-user/per-message permanent data structures where a bounded per-membership cursor provides the required behavior.

Presence/typing must not create excessive persistent writes.

Conversation lists and unread state must remain usable with realistic company-scale data.

### Browser acceptance workflows

Playwright must cover real multi-user browser workflows, not only isolated component rendering.

At minimum verify:

#### Direct conversation

- user A opens Chat;
- starts the canonical DM with user B;
- sends text;
- user B receives it through realtime;
- unread dropdown appears;
- clicking the unread entry opens the exact conversation;
- reading removes the unread entry;
- read state reaches user A;
- duplicate DM creation is prevented.

#### Message interactions

- reply;
- edit;
- visible edited marker;
- edit history;
- reaction;
- mention;
- pin;
- bookmark;
- forward;
- delete-for-me;
- mark unread;
- draft persistence.

#### Group

- create group;
- owner/member behavior;
- add member without invitation;
- newly added member sees history;
- member leaves;
- ownership transfer;
- owner-leave protection;
- final-member closure.

#### Team chat

- canonical Team Chat exists;
- active Team members participate automatically;
- adding a Team member synchronizes Chat membership;
- ending Team membership removes active Chat access;
- history remains;
- Team Chat cannot be manually left;
- Team Chat membership cannot be manually edited;
- changing head manager gives no special Chat capability.

#### Attachments

- normal file upload;
- ClamAV lifecycle;
- attachment unavailable before acceptance;
- accepted file becomes accessible;
- representative rejected scan state;
- clipboard/drop behavior where browser automation permits.

#### Voice message

- microphone-capability flow using a deterministic supported browser fixture/mocking strategy where real hardware is unavailable;
- recording lifecycle;
- preview;
- send;
- Files/scan lifecycle;
- playback.

Do not bypass production Chat/Files code solely to make the test easier.

#### Realtime

- two browser contexts;
- message delivery;
- typing;
- presence;
- unread;
- read state;
- reconnect/backfill;
- no duplicate message.

#### Search

- user finds authorized message content;
- filters work;
- user cannot find another conversation's private content;
- Admin cannot bypass Chat content authorization;
- delete-for-me content does not reappear through Search.

#### Browser-native notifications

Where browser automation permits:

- configuration state;
- user preference state;
- browser permission state;
- Chat browser alert;
- existing system Notification browser alert;
- clicking a Chat alert opens the correct conversation.

#### Mobile

Cover critical mobile workflows:

- open Chat;
- conversation list;
- conversation;
- back navigation;
- text/reply;
- attachment;
- voice controls where practical;
- unread flow.

### Browser quality

Chat E2E remains subject to existing browser-quality guardrails:

- no unexpected console errors;
- no uncaught runtime errors;
- no unexpected failed asset/API requests;
- no accidental raw translation keys;
- Polish and English coverage;
- light and dark theme coverage.

### Backend/frontend test coverage

Add meaningful coverage for:

- domain invariants;
- canonical DM uniqueness;
- permissions;
- group ownership;
- Team synchronization;
- membership authorization;
- message edit concurrency;
- idempotent send;
- delete-for-me;
- Files authorization;
- Search authorization;
- Reverb channel authorization;
- retention;
- Admin privacy;
- browser notification preferences;
- exports.

### Documentation closure

Update:

- canonical Chat module documentation;
- realtime/network documentation;
- Search integration documentation;
- Files integration documentation where necessary;
- Admin operational documentation where necessary;
- testing/quality documentation where necessary.

Do not rewrite unrelated completed phase history.

### Tasks

- [ ] Add realistic company-scale Chat fixtures/tests.
- [ ] Use incremental/cursor message-history loading.
- [ ] Verify conversation-list query behavior.
- [ ] Verify no obvious participant/message N+1 regressions.
- [ ] Verify presence/typing write behavior remains bounded.
- [ ] Add direct-conversation browser E2E.
- [ ] Add message-interaction browser E2E.
- [ ] Add group lifecycle browser E2E.
- [ ] Add Team Chat synchronization browser E2E.
- [ ] Add attachment/scanner browser E2E.
- [ ] Add voice-message browser E2E.
- [ ] Add realtime multi-context browser E2E.
- [ ] Add authorized and unauthorized Search E2E.
- [ ] Add browser-native notification coverage.
- [ ] Add critical mobile Chat E2E.
- [ ] Cover Polish and English Chat UI.
- [ ] Cover light and dark themes.
- [ ] Enforce console/runtime/request cleanliness.
- [ ] Run targeted Chat backend tests.
- [ ] Run targeted Chat frontend tests.
- [ ] Run the required full application quality gates.
- [ ] Update all affected canonical documentation.

---

## Explicit out-of-scope behavior

The following are intentionally outside Phase 31 unless a later explicit phase adds them:

- public/external users;
- public Internet Chat;
- message requests;
- user blocking;
- friend/contact requests;
- invite links;
- join requests;
- group moderator/admin roles beyond owner/member;
- conversation archive;
- conversation mute;
- notification mute schedules;
- scheduled messages;
- nested message threads;
- audio calls;
- video calls;
- screen sharing;
- bots;
- Chat webhooks;
- generic slash-command platform;
- GIF/Giphy integrations;
- generic business-object conversation contexts;
- business-module event delivery through Chat;
- automatic external URL previews;
- multiple floating Chat windows;
- a dedicated full-page Chat application as the primary UI;
- Chat email delivery;
- persistence of Chat messages inside the Notifications module;
- E2EE;
- Chat-specific application-level content encryption.

Do not create future phases for these items during this task.

---

## Permanent guardrails

- [ ] There is exactly one canonical direct conversation per unordered user pair.
- [ ] Direct/group Chat remains global and is not accidentally scoped by active Team.
- [ ] Team Chat has exactly one canonical conversation per enabled Team.
- [ ] Team Chat membership is owned by Teams, not Chat.
- [ ] Team Chat cannot become a second Team-management surface.
- [ ] Admin status never grants private Chat-content access.
- [ ] There is no `chat.admin.read` equivalent.
- [ ] Chat Search cannot leak unauthorized snippets or metadata.
- [ ] Reverb channels cannot be subscribed to without conversation authorization.
- [ ] Chat attachments cannot bypass Files/ClamAV.
- [ ] Voice messages cannot bypass Files/ClamAV.
- [ ] Chat messages do not become Notifications-module records.
- [ ] Chat messages are not delivered by email.
- [ ] Browser-native notifications remain configurable globally and by user.
- [ ] Draft contents are not stored in browser local/session storage.
- [ ] Delete-for-me cannot be bypassed through Search or export.
- [ ] Chat does not introduce a second full-text engine.
- [ ] Chat does not introduce a second realtime architecture.
- [ ] Retention does not leave orphaned Files or stale Search documents.
- [ ] Admin operational surfaces expose aggregates rather than private content.
- [ ] Normal Chat message bodies are not copied into Audit.
- [ ] Historical employee Chat identity is preserved.
- [ ] Chat does not implement E2EE or its own cryptographic protocol.
- [ ] Chat does not become a generic business-module communication framework.

---

## Completion criteria

Phase 31 is complete only when:

- [ ] Chat is a fully optional Atlas module governed by ModuleGate and Authorization.
- [ ] Direct conversations work globally across Teams.
- [ ] Exactly one canonical direct conversation exists per user pair.
- [ ] User-created groups support the accepted owner/member lifecycle.
- [ ] System-owned Team conversations synchronize canonical Teams membership.
- [ ] Team conversations cannot be manually left or membership-edited.
- [ ] Text, Markdown-lite, reply/quote, editing, edit history, reactions, mentions, forwarding, pins, bookmarks, delete-for-me, and drafts work.
- [ ] Files-owned attachments work through validation, quarantine, ClamAV, and authorization.
- [ ] Drag/drop and clipboard attachment UX is implemented.
- [ ] Media / Files / Links browsing works.
- [ ] Voice messages up to 15 minutes work through Files and ClamAV.
- [ ] Laravel Reverb provides the canonical Chat realtime transport.
- [ ] Presence, typing, delivery/read state, unread state, and reconnect reconciliation work.
- [ ] The shell Chat launcher, unread dropdown, desktop modal, and mobile full-screen workflow work.
- [ ] Chat unread state remains separate from Notifications persistence.
- [ ] Browser-native notifications support both system Notifications and Chat through global config, user preferences, and browser permission.
- [ ] Meilisearch provides Chat search without unauthorized content leakage.
- [ ] Admin cannot read, search, download, or export other users' private Chat content through the application.
- [ ] Retention defaults to indefinite and supports configured automatic retention.
- [ ] Admin has safe manual retention operations without private-content access.
- [ ] Participant-authorized Chat export works through the existing export foundation.
- [ ] Historical employee identity remains understandable after account deactivation.
- [ ] Company-scale message/history/conversation behavior is incremental and bounded.
- [ ] Chromium and Firefox browser workflows cover critical multi-user Chat behavior.
- [ ] Critical mobile Chat workflows are covered.
- [ ] Polish and English Chat UI is complete.
- [ ] Light and dark Chat UI is covered.
- [ ] Browser console/runtime/request cleanliness remains protected.
- [ ] Relevant backend, frontend, Search, Files, realtime, and browser tests pass.
- [ ] Canonical module and cross-cutting documentation is current.
- [ ] No accepted Chat behavior exists only in historical chat context.
- [ ] Phase 32 deployment has not been started as part of this phase-planning task.
