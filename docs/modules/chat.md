# Chat module

Canonical accepted product contract for the future optional internal-company Chat module. Implementation is scheduled in [Phase 31](../roadmap/phase-31-chat.md) and has not started.

## Purpose and boundary

Chat supports human-to-human communication between Atlas users. It is not a business-module event bus, generic conversation-context framework, public messaging service, social network, bot platform, or replacement for Notifications. Business modules continue to communicate system events through Notifications.

Chat is Atlas-owned. It does not use a ready-made Chat product, hosted backend, third-party Chat UI framework, or external SDK that owns the domain. Laravel broadcasting and Laravel Reverb are the accepted realtime transport; Atlas does not build a custom WebSocket server or a second realtime architecture.

The expected deployment has roughly 400 users with reasonable future growth. This is not an artificial product limit.

## Availability, activation, and authorization

Chat is an optional Atlas module governed by the canonical module registry, Module Availability, ModuleGate, and Authorization foundations. Global deactivation hides and blocks Chat UI, HTTP/application actions, Search, and realtime channels while preserving historical data.

Permissions cover using Chat, starting direct conversations, creating groups, uploading attachments, sending voice messages, operational Admin access, and retention administration. Starter roles normally allow standard employees to use these capabilities unless project permissions restrict them.

No permission equivalent to `chat.admin.read` may grant Administrators general access to private Chat content.

## Conversation types and scope

Chat supports exactly:

- `direct` — a global conversation between two users;
- `group` — a global user-created conversation;
- `team` — a system-owned Team-scoped conversation.

Direct and group conversations belong to the Atlas user account context, not the active Team. Switching active Team does not duplicate, switch, or hide them. Every active user may start a direct conversation with every other active user regardless of Team membership. Each unordered user pair has exactly one canonical direct conversation, including under concurrent creation. There are no message requests, contact approval, friend requests, or user blocking.

A group has one owner, zero or more members, a name, an optional avatar, and historical membership. Its only roles are `owner` and `member`. The owner manages its name, avatar, membership, and ownership transfer. Adding a user requires no approval, and a newly added member can see available history from the beginning. There are no invite links, join requests, moderators, or custom conversation roles. An owner transfers ownership before leaving while another active member remains; a last remaining owner may leave and close the conversation without destroying its history.

Every Team with active Chat has exactly one idempotently created system Team conversation. Teams owns its active membership and Chat synchronizes that canonical membership. The conversation is system-owned, cannot be manually deleted or left, and does not permit Chat-side membership editing. Team deactivation preserves history and reactivation restores the same conversation. Team identity follows the Team; head-manager changes confer no Chat authority. Chat does not create automatic manager/direct-report conversations.

Conversation timelines include immutable system entries for relevant structural changes without turning them into Notifications records.

## Messages and personal state

Messages support plain and multiline text, Unicode emoji, safe links, safe Markdown-lite, replies/quotes, unlimited author editing with a visible edited state and participant-visible edit history, reactions, individual/`@everyone`/`@online` mentions, authorization-safe forwarding, participant pins, private bookmarks, and backend-backed per-user/per-conversation drafts. Raw untrusted HTML and nested threads are not supported.

Editing uses explicit stale-write protection. Sending is idempotent across double submission, retry, and reconnect.

The only normal user-facing removal action is `Delete for me`. It changes only that user's visibility, retains a tombstone for that user, and must also be respected by Search, exports, and content viewers. It is separate from lifecycle deletion by retention. Draft content is not stored in browser local or session storage.

## Files, content browsing, and voice messages

Every attachment uses the Files module's public contracts and canonical global size, MIME/content, validation, authorization, quarantine, ClamAV, retention, preview, and download policies. Chat does not own a parallel file store or bypass scanning. Files are unavailable to participants until the accepted scanner lifecycle permits access.

Uploads support file selection, drag and drop, clipboard paste, progress, quarantine/scanner status, failure, and allowed retries. Conversation details provide Media, Files, and Links browsing. Links are extracted only from messages visible to the participant; Atlas does not fetch third-party metadata or create external URL previews.

Voice messages are Files-owned attachments with a maximum recording length of 15 minutes. The explicit browser flow is record, stop, preview/listen, then send or discard and re-record. Stopping never sends automatically. Voice messages require an intentional microphone action, pass through Files and ClamAV, and use an accessible audio player. Calls, video calls, and screen sharing are out of scope.

## Realtime, presence, and unread state

Phase 31 extends the existing realtime foundation with canonical Laravel broadcasting and Laravel Reverb. WebSockets deliver events but are not the source of truth. Persisted application state remains authoritative, reconnect reconciles missed state, and retry cannot lose or duplicate persisted messages or permanently corrupt read state.

Every private and presence channel requires explicit user/conversation/Team authorization. Guessing a conversation identifier grants no access.

Chat supports online/offline/last-seen presence, informational manual statuses, expiring ephemeral typing indicators, direct sent/delivered/read state, group and Team read visibility, unread counts, a new-message separator, last-read cursors, and mark-as-unread. Presence and typing avoid excessive persistent writes; typing is neither persisted nor audited. `Do not disturb` is informational and does not mute delivery.

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

Chat does not implement end-to-end encryption, application-level message encryption, per-message envelopes, or conversation/device key management. Infrastructure-level encryption at rest protects Atlas persistent production data as a whole in Phase 32. It does not replace Chat application authorization or claim protection from authorized root access while storage is unlocked.

## Explicitly out of scope

Phase 31 excludes public/external users, public Internet Chat, requests or blocking, friend/contact workflows, invite links, join requests, moderator/admin group roles, archive, conversation muting or schedules, scheduled messages, nested threads, calls, screen sharing, bots, webhooks, slash commands, GIF services, generic business-object contexts, business-module event delivery, external URL previews, multiple floating windows, a primary dedicated Chat page, email delivery, Notifications persistence, E2EE, and Chat-specific application encryption.

The complete binding implementation and acceptance checklist remains in [Phase 31](../roadmap/phase-31-chat.md).
