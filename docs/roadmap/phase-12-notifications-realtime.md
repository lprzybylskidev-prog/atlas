## Phase 12 — Notifications and realtime foundation

### Implementation contract

- Use one notification module for the entire platform.
- Notifications are typed and may target a user and team.
- Support in-app and email channels.
- Support preferences per user, type, and channel.
- Delivery is queued.
- Store read state and a deep link.
- Apply retention and cleanup.
- Do not put unnecessary sensitive data in email.
- Reports, imports, integrations, jobs, security events, and system alerts reuse this module.
- WebSockets are used only for genuine server push:
  - notifications;
  - report/import/export progress;
  - session invalidation;
  - system alerts;
  - live TimeTracking status;
  - shared live updates with a demonstrated need.
- Ordinary CRUD, forms, filters, pagination, and user-triggered requests remain HTTP/Inertia.

- [ ] Create `Notifications` module.
- [ ] Define typed notification events.
- [ ] Support user and team recipients.
- [ ] Support in-app notifications.
- [ ] Support email notifications.
- [ ] Add per-user, per-type, and per-channel preferences.
- [ ] Queue delivery.
- [ ] Add read/unread state.
- [ ] Add deep links.
- [ ] Add retention cleanup.
- [ ] Avoid sensitive email content.
- [ ] Add unread-count UI.
- [ ] Add notification center.
- [ ] Add WebSocket/realtime infrastructure only for real server-push needs.
- [ ] Add live notification delivery.
- [ ] Add session invalidation events.
- [ ] Add system alerts.
- [ ] Add progress events for queued operations.
- [ ] Commit notifications and realtime foundation.
