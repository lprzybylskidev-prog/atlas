# Realtime, network, and browser behavior

Canonical runtime behavior for realtime updates, WebSockets, reconnects, network failures, browser tabs, and browser storage.

## Realtime and WebSockets

Use WebSockets only for genuine server push:

- notifications;
- report/import/export progress;
- session invalidation;
- system alerts;
- live worker status;
- status-change feed;
- shared updates where truly required.

Do not use WebSockets for ordinary CRUD, filters, forms, pagination, or user-triggered HTTP actions.

---

## Network and Browser Behavior

Use centralized handling for:

- offline/online;
- 401;
- 403;
- 419;
- 422;
- 429;
- 500\.

Current implementation foundation:

- `resources/js/Services/networkHandling.ts` registers browser online/offline handling and centralizes messages for 401, 403, 419, 422, 429, and 500-class failures;
- backend Inertia flashes carry a unique transport ID and `ToastViewport` consumes each ID once, so partial reloads, preserved state, and shell remounts cannot replay a completed mutation message;
- automatic retry is allowed only for safe idempotent HTTP methods (`GET`, `HEAD`, `OPTIONS`);
- unsafe mutations are never retried automatically and CSRF failures surface as a refresh/sign-in problem instead of entering retry loops;
- `resources/js/Services/teamScopedState.ts` clears Atlas-owned team-scoped browser storage prefixes when the active team changes.
- `core_notifications.realtime_events` stores the minimal realtime event buffer for notifications, queued progress, session invalidation, and system-alert delivery;
- `/realtime/events` is the authorized active-team-aware browser feed used by the initial notification foundation;
- browser WebSocket channel wiring is implemented only when a genuine server-push workflow needs it beyond the current minimal feed.

### Chat messaging realtime

Phase 31 P31-W06 implements full browser WebSocket delivery for Chat with Laravel broadcasting, Reverb, Laravel Echo, and the Pusher protocol client. It does not replace Notifications ownership, turn Chat messages into Notifications records, build a custom WebSocket server, or introduce a competing application-event architecture. The buffered `/realtime/events` Notifications feed remains available independently.

The browser subscribes only to the private `chat.user.{userPublicId}` channel and authorized `chat.conversation.{conversationPublicId}` presence channels. `/broadcasting/auth` authenticates through the normal web session, and channel callbacks reapply ModuleGate, Chat permission, active-Team rules for Team conversations, and participant scope. A guessed user or conversation identifier returns a denial and never exposes channel membership.

WebSocket delivery remains a transport rather than the source of truth. `GET /chat/conversations/{conversation}/realtime` reconciles persisted messages, per-membership delivery/read cursors, first-unread position, unread count, participant cursors, and presence after initial connect or reconnect. Clients merge by message public identifier so an event received both through Reverb and reconciliation is not duplicated. Sending remains idempotent through the author/client-message key contract.

Presence heartbeats are coalesced to at most one persistence update per 45 seconds and are considered online for 90 seconds. Manual `available`, `busy`, `do_not_disturb`, and `out_of_office` statuses plus bounded optional text/emoji are informational; DND does not suppress message delivery. Typing is an authorized presence-channel client event, is never stored or audited, and expires after five seconds. Delivery, read, and mark-unread use monotonic per-membership cursors rather than user-by-message receipt rows.

Ordinary CRUD, filters, forms, pagination, and user-triggered mutations continue through HTTP/Inertia. Reverb carries the resulting application events only.

### Reverb runtime

The committed development and production Compose stacks run one non-root `reverb` service from the same locked Atlas runtime image as PHP-FPM, Horizon, and the scheduler. Nginx proxies `/app/` WebSocket upgrades and `/apps/` Reverb application requests; Reverb itself stays on the internal network. Production uses Redis-backed Reverb scaling and a hostname-only `REVERB_ALLOWED_ORIGINS` allowlist. Never use `*` in production.

The browser receives only the public Reverb application key and public client endpoint through server-rendered meta tags. `REVERB_APP_SECRET` is server-side, uses the standard `_FILE` secret mechanism in production, and must not enter Vite assets, HTML, logs, or source control. Internal publishing uses `REVERB_HOST=reverb`, while `REVERB_CLIENT_*` describes the reverse-proxy endpoint visible to browsers.

Local process development starts Reverb through `composer dev`. Compose development uses the `reverb` service and `http://localhost:8000` as the browser endpoint. Relevant configuration is:

```text
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=<non-secret application id>
REVERB_APP_KEY=<public client key>
REVERB_APP_SECRET=<external secret>
REVERB_HOST=<internal service host>
REVERB_PORT=8080
REVERB_ALLOWED_ORIGINS=<comma-separated hostnames only>
REVERB_CLIENT_HOST=<browser-visible host>
REVERB_CLIENT_PORT=<browser-visible proxy port>
REVERB_CLIENT_SCHEME=http|https
```

Readiness performs a bounded TCP check against the internal publishing endpoint. It is blocking in production through `ATLAS_HEALTH_REVERB_CRITICAL=true`. A failed Reverb check makes the Chat module technically unavailable while persisted data remains intact. Restart Reverb through the runtime service manager after deployment; do not expose its internal port directly.

For a manual Ubuntu/Debian-style installation, run `php artisan reverb:start --host=127.0.0.1 --port=8080` as a dedicated non-root systemd or Supervisor service with automatic restart and an adequate open-file limit. Put it behind the same TLS reverse proxy as Atlas, forward `/app/` with HTTP/1.1 Upgrade headers and `/apps/`, load the same external Reverb secret, and validate the internal TCP endpoint plus an authenticated browser connection after restart.

P31-W07 implements the separate, narrow media-transport foundation. Atlas-managed self-hosted LiveKit transports realtime audio, video, and screen-share media, while Atlas owns authorization and Call/Meeting state. The backend adapter prepares and ends rooms, removes participants, and issues five-minute room-scoped credentials only after ModuleGate and session admission approve the user. No generic browser-controlled room-token route exists. P31-W08 connects ad-hoc Call admission to active Call, joined-participant, and conversation-access state; Meeting admission remains deny-by-default until its later workstreams.

Ad-hoc Calls use Reverb private-user events for ringing and authoritative state refresh, while LiveKit handles media only. PostgreSQL arbiters enforce one active Call per conversation, one joined ad-hoc Call per user, and one screen share per Call. Refresh detects current authorized state and offers rejoin without automatically enabling media. The browser requests camera/microphone only after an explicit Call action; `Permissions-Policy` permits same-origin camera and microphone use while continuing to deny unrelated sensitive capabilities. Preferred devices fall back to browser defaults if unavailable.

Meeting identity and non-RTC lifecycle never depend on LiveKit room existence. In-person Meetings are rejected before room preparation and token issuance and remain usable during LiveKit/TURN/Egress outages. LiveKit data channels do not carry canonical Chat messages. Cross-Call/Meeting RTC occupancy and full Meeting room lifecycle are completed with the Meeting RTC workstream.

Self-hosted LiveKit Egress is the separate room-composite recording service for RTC-enabled online and hybrid Meetings. It has an independent non-blocking health result and is not part of Chat ModuleGate or live RTC authorization. Ad-hoc Calls and in-person Meetings cannot invoke it. Egress renders the Atlas-owned `/rtc/recording-template`, which uses a screen-share-first composition when screen media exists and a responsive participant grid otherwise. Pause/resume creates private internal MP4 segments; the scheduler runs `chat:meetings:finalize-recordings`, uses `ffmpeg` to produce one final MP4, imports that artifact through Files, and removes staging only after the Files reference commits. `LIVEKIT_EGRESS_OUTPUT_DIRECTORY` is the Egress-side volume path and `LIVEKIT_EGRESS_STAGING_DIRECTORY` is the matching Atlas-side mount. The future production topology permits trusted LAN/VPN clients to reach only the configured LiveKit WebRTC/TURN endpoints in addition to the Atlas reverse proxy; internal API/control, Egress, Redis, staging, the template credentials, and recording segments remain private.

The RTC infrastructure, ad-hoc Call persistence/admission, user-facing Call/Meeting media sessions, and Meeting recording orchestration are implemented. Production TURN/TLS deployment remains in Phase 52.

Preserve non-sensitive form data where appropriate.

Retry only idempotent safe requests.

Never automatically retry unsafe mutations.

Use timeouts.

Avoid CSRF retry loops.

Supported browsers:

- current stable Chrome;
- current stable Edge;
- current stable Firefox.

Safari only when business need appears.

No Internet Explorer.

Playwright covers Chromium and Firefox.

### Browser storage

Store only non-sensitive UI preferences in local/session storage.

Do not store:

- tokens;
- PII;
- business records;
- sensitive filters.

Important preferences belong in backend settings.

Version local caches and clear them on release or team change.

No PWA or offline business mode unless explicitly added later.

---
