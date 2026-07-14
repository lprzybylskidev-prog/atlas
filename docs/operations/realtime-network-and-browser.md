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
