## Phase 15 — Notifications and realtime foundation

**Status:** `not started`

## Objective

Implement platform notifications and the minimal realtime foundation before reports, imports, integrations, sessions invalidation, system alerts, and TimeTracking live status depend on server push or progress delivery.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 13 — Sessions and active team](phase-13-sessions-active-team.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 14a — PostgreSQL module schemas](phase-14a-postgresql-module-schemas.md)
- [Notifications module documentation](../modules/notifications.md)
- [Realtime, network, and browser operations](../operations/realtime-network-and-browser.md)

## Implementation contract

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

## Tasks

- [ ] Create `Notifications` module.
- [ ] Create notification and realtime tables only in the `core_notifications` PostgreSQL schema.
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
- [ ] Enforce ModuleGate and active-team context on realtime channels and notification delivery routes.
- [ ] Add session invalidation events.
- [ ] Add system alerts.
- [ ] Add progress events for queued operations.
- [ ] Add development-only demo seeders for example notifications after real notification tables exist.
- [ ] Commit notifications and realtime foundation.

## Completion criteria

- [ ] Typed notifications support in-app/email delivery, preferences, read state, retention, and deep links.
- [ ] Realtime infrastructure exists only for documented server-push needs.
- [ ] Later queued operations can publish progress/failure/success through one notification contract.
- [ ] Relevant tests and documentation are current.
