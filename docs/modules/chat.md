# Internal communication and Chat module

Canonical current boundary and accepted target for the Atlas internal-communication capability. Phase 31 is in progress: P31-W01 through P31-W11 established the module boundary, shared Calendar, persistent conversation ownership, canonical message behavior, Files-backed attachments, Reverb messaging realtime, self-hosted LiveKit RTC, ad-hoc Calls, Meeting scheduling and RTC lifecycle, Meeting recording, controlled sharing, Files finalization, and separate recording retention. P31-W12 is next. The phase file remains the binding implementation and acceptance contract.

## Purpose and boundary

The accepted capability supports human-to-human Chat, a shared Core Calendar, internal Calls, Meetings, screen sharing, Meeting recording, and provider-neutral future transcription readiness for Atlas users. It is not a business-module event bus, generic conversation-context framework, public messaging/conferencing service, social network, bot platform, or replacement for Notifications. Business modules continue to communicate system events through Notifications.

Atlas owns Chat, Calendar, Call, Meeting, invitation, recording-metadata, sharing, retention, and transcript state. It does not use a ready-made Chat/conferencing product or an external SDK that owns those domains. Laravel broadcasting and Laravel Reverb remain the application-event and text-message realtime transport. A narrow Atlas RTC boundary uses Atlas-managed self-hosted LiveKit only for realtime media transport and separate self-hosted Egress only for Meeting recording; LiveKit data channels do not replace Reverb Chat delivery.

The expected deployment has roughly 400 users with reasonable future growth. This is not an artificial product limit.

## Availability, activation, and authorization

Chat is an optional Atlas module with key `chat`. It supports global activation and deliberately does not support team activation. The canonical ModuleGate wrapper is the common entry point for UI capability contribution and future Chat/Call/Meeting application actions. Global deactivation therefore removes offered capabilities and denies backend access while preserving historical data.

Chat owns its persistence in `optional_chat`. It stores conversations, messages and personal state, ad-hoc Calls, Meeting definitions and invitations, occurrence RTC/attendance state, logical Meeting recordings, private Egress segments, recording access grants, and its settings. A recording row remains as lightweight history after retention removes its Files artifact and dependent grants. Transcription metadata remains a later workstream. Calendar owns `core_calendar`; Chat reaches it only through Calendar's `Application/Public` API.

The module catalog includes granular route-aligned permissions for Chat use, direct/group creation, attachments, voice messages, Call start/join, screen sharing, Meetings and invitations, moderation, recording, transcription, and content-free operational/retention administration. Calendar permissions remain Calendar-owned. Standard communication capabilities are included in `workspace.access` and the reusable `communication.access` bundle; `communication.meeting-host` and `communication.operations` separate host and content-free operational capabilities.

No permission equivalent to `chat.admin.read` exists. Operational Admin permissions do not include conversation or message read routes. Administrator permissions never replace participant/Meeting scope checks.

## Conversation types and scope

Chat supports exactly four conversation types:

- `direct` — a global conversation between two users;
- `group` — a global user-created conversation;
- `team` — a system-owned Team-scoped conversation.
- `meeting` — a system-owned persistent conversation attached to one Meeting or recurring Meeting series.

The implemented scope and persistence policies make direct and group access depend on active conversation membership and explicitly ignore the active Team. Team conversation access requires synchronized active membership and a conversation Team matching the active Team. Meeting conversation access depends on active invitation-derived membership and explicitly ignores the active Team. ModuleGate and the normal Chat permission remain mandatory before participant scope is evaluated.

Direct and group conversations belong to the Atlas user account context, not the active Team. Switching active Team does not duplicate, switch, or hide them. Every active user may start a direct conversation with every other active user regardless of Team membership. Each unordered user pair has exactly one canonical direct conversation: a sorted pair row and PostgreSQL unique constraint arbitrate concurrent creation. There are no message requests, contact approval, friend requests, or user blocking.

A group has one owner, zero or more members, a name, an optional avatar, and historical membership. Its only roles are `owner` and `member`. PostgreSQL permits only one active owner and one active membership per user/conversation, while ended rows remain attributable. The owner manages its name, avatar, membership, and ownership transfer. Adding a user requires no approval, and a newly added member can see available history from the beginning. There are no invite links, join requests, moderators, or custom conversation roles. An owner transfers ownership before leaving while another active member remains; a last remaining owner may leave and close the conversation without destroying its membership or timeline history. Group mutations and their mandatory audit evidence commit atomically.

Every Team with active Chat has exactly one idempotently created system Team conversation. Teams owns its active membership and invokes a neutral shared synchronization port inside the owning membership transaction; Chat contributes the optional implementation without exposing Chat internals to Core Teams. Addition and removal end or add Chat membership rows and append content-free structural entries without deleting history. The conversation is system-owned, cannot be manually deleted or left, and does not permit Chat-side membership editing. Team deactivation preserves history and reactivation restores the same conversation. Team identity follows the Team; head-manager changes confer no Chat authority. Chat does not create automatic manager/direct-report conversations.

Meeting chat is persistent, system-owned, participant-authorized, and governed by the Meeting lifecycle. The Meeting owner key is the series public identifier for recurring Meetings and the Meeting public identifier otherwise, with a unique database arbiter guaranteeing one conversation. Pending, accepted, and declined invitation-derived memberships retain access; removal ends membership and removes access without deleting history. Meeting scheduling, invitation/RSVP changes, scoped recurrence mutations, cancellation, and Calendar composition are implemented; occurrence-specific RTC kick behavior remains owned by P31-W10. Conversation timelines store immutable, content-free structural entries separately from Notifications and message persistence.

## Messages and personal state

Messages support plain and multiline text, Unicode emoji, safe links, safe Markdown-lite, replies/quotes, unlimited author editing with a visible edited state and participant-visible edit history, reactions, individual/`@everyone`/`@online` mentions, authorization-safe forwarding, participant pins, private bookmarks, and backend-backed per-user/per-conversation drafts. Raw untrusted HTML and nested threads are not supported.

Markdown-lite is rendered with the explicitly pinned CommonMark dependency, raw HTML stripping, unsafe-link rejection, bounded nesting, and only the accepted CommonMark/autolink behavior. Message bodies are stored as source text; rendered HTML is derived at the authorized read boundary.

Editing uses an expected version and atomically appends every accepted revision to immutable edit history. Sending uses an author-scoped client message key, canonical request hash, transaction-scoped advisory arbitration, and a database unique constraint so double submission, retry, and reconnect return the original message while reuse for different content is rejected. Replies must target a visible message in the same conversation. Forwarding copies the current authorized content into a new destination message and exposes only a forwarded marker, never source-conversation or source-message metadata.

Individual mention targets must be active Atlas users with active conversation membership. `@everyone` and `@online` are derived from message content and remain Chat-owned mention state. Reactions are unique per user/message/emoji, one participant-owned pin exists per message, and bookmarks remain private per user.

The only normal user-facing removal action is `Delete for me`. It changes only that user's visibility and produces an immutable per-user deletion record. The central authorized message view returns a content-free tombstone to that user and blocks replies, edit-history access, reactions, pins, bookmarks, and forwarding through the hidden message, while other participants retain the content. Later Search, export, and content-viewer workstreams must consume the same authoritative visibility rule. It is separate from lifecycle deletion by retention.

Exactly one draft is stored per user and conversation. Draft updates retain a backend version, may retain a same-conversation reply target, are private to their owner, and are cleared after a successful send. Draft content is never delegated to browser local or session storage. HTTP presentation, the final Chat modal, realtime delivery, Search projections, and participant exports remain owned by their later sequential Phase 31 workstreams rather than being introduced prematurely by P31-W04.

## Files, content browsing, and voice messages

Every attachment uses the Files module's public contracts and canonical global size, MIME/content, validation, authorization, quarantine, ClamAV, lifecycle deletion, preview, and download policies. Chat stores only conversation/message ownership metadata and the Files public identifier; it does not own a parallel file store or bypass scanning. New uploads remain unavailable while pending or scanning and after failed, infected, or unsupported results. Only clean files cross the participant-authorized download/preview boundary. An uploader may retry only an allowed pending/failed scan and may discard only an unattached upload that they own.

Uploads support file selection, drag and drop, clipboard paste including screenshots, progress, quarantine/scanner status, localized failure, and allowed retries. Attachment-only messages are valid, but a pending attachment remains unavailable until Files marks it clean. Conversation details provide Media, Files, and Links browsing. Links are extracted only from messages visible to the participant, use safe HTTP(S)-only external navigation, and never trigger third-party metadata fetching or automatic previews. Inline media preview is restricted to an explicit raster-image/audio/video MIME allowlist and uses no-store, nosniff, and restrictive content-security headers; other clean files are downloads.

Voice messages are Files-owned attachments with a server-enforced maximum recording length of 15 minutes. The explicit browser flow is record, stop, preview/listen, then send or discard and re-record. Stopping never sends automatically. Browser media permission is requested only by the intentional record action, media tracks and temporary object URLs are released, and the canonical Atlas security headers allow microphone access only to the same origin and local blob playback. Voice uploads pass through Files and ClamAV and use an accessible audio player.

## Shared Core Calendar boundary and target

Calendar is registered as a shared Core capability rather than Chat-owned storage or a Meeting-only widget. Its narrow public API accepts owner-tagged event contributions and exposes privacy-safe Free/Busy windows. Chat's Meeting Calendar adapter uses that API and never Calendar persistence. The P31-W02 implementation provides private personal events, Month/Week/Day/Agenda views, Europe/Warsaw recurrence, reminders, and Free/Busy behavior without turning Calendar into task management or exposing private personal events to Administrators.

## Implemented Calls and Meeting scheduling

Atlas implements direct, group, and Team ad-hoc audio/video Calls inside their existing conversations. Call lifecycle, participant state, initial media mode, room identity, useful timestamps, and structural timeline outcomes remain Atlas/PostgreSQL state; LiveKit transports media only. Partial unique database indexes plus transaction-scoped advisory locks enforce one active Call per conversation, one joined ad-hoc RTC session per user, one participant per Call, one idempotent start request, and one active screen share per Call. Busy, declined, missed, failed, active, ended, leave, and rejoin states remain explicit. The same RTC occupancy contract is extended across Meetings when their RTC lifecycle is introduced in P31-W10.

Direct and group Calls ring eligible participants through their private Reverb user channels. Team Calls become active immediately and use a quiet join-style alert instead of ringing an entire Team. A normal missed-Call Notification is created without requesting email delivery, while Reverb and an already-authorized browser Notification provide live alerts. The personal Call history is a canonical DataTable with All, Missed, Incoming, and Outgoing filters and localized conversation type, initial mode, direction, result, start time, and duration.

The global authenticated layout detects authorized current Call state and offers explicit incoming choices, quiet Team joining, and rejoin after refresh. It never restores camera or microphone automatically. The reusable conversation launcher first opens the pre-call screen; browser media permission is requested only after that user action. Camera, microphone, and supported output choices are persisted per user with a safe default-device fallback. The same controls remain available in-session alongside camera/microphone toggles and one-person screen sharing. Ad-hoc Calls have no recording control, recording persistence, or Egress invocation.

Online and hybrid Meeting details expose an intentional Join online action using the same canonical media-device preparation component as Calls, then connect that prepared state to the authorized LiveKit Meeting room. In-person Meetings render neither the action, pre-call dialog, nor recording controls.

Meetings may start immediately, be scheduled, or recur. The implemented Meeting domain supports explicit `online`, `in_person`, and `hybrid` modes and remains Atlas-owned independently from LiveKit room existence. Every mode keeps invitations, RSVP, Calendar composition, recurrence, reminders, cancellation, history, and persistent Meeting chat. Participants may invite another active Atlas user, new invitations remain pending until response, and only the organizer may edit, cancel, or remove a participant. In-person Meetings require a physical text location and never receive an RTC room/token; Hybrid Meetings remain one Meeting with physical location plus optional Atlas online eligibility.

Daily, weekly with selected weekdays, and monthly recurrence uses the shared Warsaw-time Calendar behavior and supports occurrence, future, or whole-series mutation. Invitees see only their authorized Meeting projections, including localized mode/location; Free/Busy conflicts produce a non-blocking warning. Cancellation preserves the Meeting and Calendar history. Invitation, update, and cancellation notifications use the Notifications catalog, safe Meeting deep links, and requested email delivery. The regular `/meetings` surfaces expose Meet now, scheduling, RSVP, participant invitation/removal, scoped editing, and cancellation in Polish and English.

Only RTC-enabled online/hybrid Meetings use device preparation, attendance derived from online joins, screen sharing, organizer media moderation, occurrence-ban RTC kick semantics, live-room lock, minimize/rejoin, and 15-minute empty-room resource cleanup. The minute scheduler ends only an empty LiveKit room after 15 continuous minutes; it never cancels or mutates the Meeting, Calendar, invitation, RSVP, or Meeting-chat lifecycle. RTC attendance is visible to every authorized participant and explicitly describes online participation only: it does not prove physical presence or absence. RTC infrastructure failure does not make in-person Meetings unavailable. There is no public guest access, lobby, external conferencing identity, room-resource management, or physical-attendance automation.

## Recording and transcription target

Recording is available only for RTC-enabled online/hybrid Meetings and only to an organizer who also has the recording permission. In-person Meetings without RTC expose no recording controls. Separate self-hosted LiveKit Egress produces one composed final recording from media actually published into the RTC session; pause/resume is represented to users as one recording lifecycle with visible `REC`/`Paused` states. Intermediate Egress staging is temporary, while the finalized artifact becomes an authorized Files-owned object. Recordings may be shared with selected active Atlas users outside the Meeting, but recipients cannot re-share them.

Pause stops the active Egress segment and resume starts the next private segment. The scheduler finalizes stopped recordings through `ffmpeg`, imports one MP4 through the Files public boundary, and removes staging only after successful import. Current Meeting participants with view/download permission can access the clean Files artifact; explicitly removed or occurrence-banned participants cannot. A participant with share permission may grant access to one active Atlas user, while recipients receive no Meeting, chat, Calendar, attendance, or onward-sharing capability. The grant creator can revoke the grant; public and external sharing do not exist.

Recording retention is separate from Chat retention and defaults to `null` (indefinite). Admin Communication operations shows aggregate eligibility, configures a positive day count or indefinite retention, and queues manual cleanup as a Managed Process. Scheduled cleanup deletes the Files artifact first, removes recording shares and available transcript dependents, then preserves timing, duration, structural timeline, Audit, and an explicit removed state. Transcription is a provider-neutral, queue-only capability introduced by the next workstream; normal transcript UI remains hidden while unavailable.

## Realtime, presence, and unread state

Phase 31 extends the existing realtime foundation with canonical Laravel broadcasting and Laravel Reverb. WebSockets deliver events but are not the source of truth. Persisted application state remains authoritative, reconnect reconciles missed state, and retry cannot lose or duplicate persisted messages or permanently corrupt read state.

Every private and presence channel requires explicit user/conversation/Team authorization. Guessing a conversation identifier grants no access.

Chat supports online/offline/last-seen presence, informational manual statuses, expiring ephemeral typing indicators, direct sent/delivered/read state, group and Team read visibility, unread counts, a new-message separator, last-read cursors, and mark-as-unread. Presence and typing avoid excessive persistent writes; typing is neither persisted nor audited. `Do not disturb` is informational and does not mute delivery.

The implemented transport uses a private per-user channel and participant-authorized per-conversation presence channels. Heartbeats are persisted at most once per 45 seconds, online state expires after 90 seconds, and browser typing indicators expire after five seconds. Delivery, read, and mark-unread use one cursor row per conversation membership. Initial connection and reconnect call the authoritative reconciliation endpoint and merge persisted messages by public identifier.

The RTC foundation uses pinned, self-hosted LiveKit Server and separate LiveKit Egress development services connected through the canonical Redis service. They remain sibling infrastructure containers and are not installed in the PHP workspace or runtime image. Reverb remains the only canonical Chat-message transport; LiveKit data channels do not store or deliver Atlas messages.

The internal `RtcGateway` prepares and ends rooms, removes participants, and issues five-minute room-scoped participant credentials through a narrow Atlas-owned HTTP/JWT adapter to LiveKit's server API. Atlas exposes only the browser-visible LiveKit WebSocket URL and an authorized participant token; the API key and secret remain backend/service configuration. Ad-hoc Call admission now requires an active Atlas Call, authorized conversation access, and a joined Call-participant row. Meeting admission remains deny-by-default until P31-W09/P31-W10 connect it to authoritative Meeting state, so a browser-supplied or guessed room identifier never authorizes access.

RTC modes are explicit. Ad-hoc Calls and online/hybrid Meetings may use RTC, while an in-person Meeting is rejected before room preparation and again by the LiveKit adapter as defense in depth. Meeting eligibility has no lobby, organizer-presence, or scheduled-start gate; the actual Meeting room admission/session lifecycle is introduced by P31-W10. RTC infrastructure owns no Meeting persistence. LiveKit outage produces a typed RTC-unavailable result without disabling Reverb or text Chat; Egress health is independently degraded and cannot disable live RTC. Chat's ModuleGate technical dependency remains Reverb only.

## Shell and browser experience

Chat opens from a permission- and activation-gated shell control with a total unread badge. A separate Chat-owned dropdown, visually consistent with Notifications, shows one entry per unread conversation and opens the exact conversation. Chat entries are not Notifications records.

Desktop uses one substantial modal with one active conversation. Mobile uses a full-screen list-to-conversation-to-back flow. Conversation views include All, Unread, Direct, Groups, and Team, with favorite conversations surfaced prominently. Archive and multiple floating windows are not supported.

The UI follows Atlas accessibility, keyboard/focus, responsive, localization, and light/dark-theme contracts.

## Browser-native notifications

Chat messages and mentions do not create Notifications-module records and are not emailed. Phase 31 adds one shared browser-native delivery capability usable by existing system Notifications and Chat without merging their persistence or domains.

Native delivery is controlled independently by application configuration, per-user system-Notification and Chat preferences, and browser permission. Permission is requested only after intentional user action. A minimal Chat preview may open/focus Atlas and navigate the Chat modal to the correct conversation.

## Search and exports

Chat uses the existing Search/Meilisearch foundation for conversations, users, message text, attachment filenames, and links, with person, conversation, date, and content-type filtering. Meilisearch is a derived projection, not an authorization boundary or source of truth.

No result content or metadata may be exposed unless the current user is authorized for the conversation. Admin status provides no content-search bypass. Membership, Team activation, account state, delete-for-me, edit, attachment, and retention changes must update effective visibility and projections.

A current participant may export an authorized conversation through the Core Exports foundation in PDF, CSV where appropriate, and JSON. Exports respect conversation access, delete-for-me, retention, edit history, attachment authorization, and safe forwarding metadata. Admin status alone does not grant export access.

## Retention, Admin privacy, health, and Audit

Chat retention defaults to `null`, meaning indefinite retention. A positive configured day count enables automatic retention. Cleanup coordinates Chat records, Search projections, Files attachments, voice messages, and dependent state without leaving stale documents or orphaned files, and long work uses queue/managed-process foundations.

Authorized Admin retention operations may preview aggregate impact, run the configured policy, or purge older than an explicit cutoff through appropriate confirmation, managed-process, and Audit controls. Operational surfaces may show aggregate health, usage, Reverb, job, Search, Files, and retention metrics without exposing private content.

Within the Atlas application, Administrator status never grants access to another user's private conversation, message bodies or edit history, voice messages, attachments, Search results, or exports. Direct root, database, or infrastructure access is governed by organizational procedures and is outside application authorization.

Chat audits structural and administrative operations using its typed audit catalog, including group/membership/ownership/closure and retention operations. Audit does not copy ordinary message bodies, voice content, attachment content, or every message. Historical employee identity remains attributable after account deactivation and may be marked inactive; debtor anonymization rules do not automatically apply.

## Encryption boundary

Chat does not implement end-to-end encryption, application-level message encryption, per-message envelopes, or conversation/device key management. Infrastructure-level encryption at rest protects Atlas persistent production data as a whole in Phase 33. It does not replace Chat application authorization or claim protection from authorized root access while storage is unlocked.

## Explicitly out of scope

Phase 31 excludes public/external users, public Internet Chat/conferencing, external guests, dial-in/SIP/PSTN, requests or blocking, friend/contact workflows, public invite links, join requests, group moderator/admin roles, archive, conversation muting or schedules, scheduled messages, nested threads, ad-hoc Call recording, multiple simultaneous screen shares, bots, webhooks, slash commands, GIF services, generic business-object contexts, business-module event delivery, external URL previews, multiple floating Chat windows, a primary dedicated Chat page, Chat email delivery, Chat-message persistence in Notifications, mandatory production transcription/AI, LiveKit Cloud, E2EE, and Chat-specific application encryption.

The complete binding implementation and acceptance checklist remains in [Phase 31](../roadmap/phase-31-chat.md).
