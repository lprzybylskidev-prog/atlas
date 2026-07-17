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
- 500.

Current implementation foundation:

- `resources/js/Services/networkHandling.ts` registers browser online/offline handling and centralizes messages for 401, 403, 419, 422, 429, and 500-class failures;
- automatic retry is allowed only for safe idempotent HTTP methods (`GET`, `HEAD`, `OPTIONS`);
- unsafe mutations are never retried automatically and CSRF failures surface as a refresh/sign-in problem instead of entering retry loops;
- `resources/js/Services/teamScopedState.ts` clears Atlas-owned team-scoped browser storage prefixes when the active team changes.
- `core_notifications.realtime_events` stores the minimal realtime event buffer for notifications, queued progress, session invalidation, and system-alert delivery;
- `/realtime/events` is the authorized active-team-aware browser feed used by the initial notification foundation;
- browser WebSocket channel wiring is implemented only when a genuine server-push workflow needs it beyond the current minimal feed.

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
