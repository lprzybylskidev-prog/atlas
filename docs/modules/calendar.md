# Calendar

Canonical current boundary for the shared Core Calendar capability introduced in Phase 31.

## Ownership and availability

Calendar is a non-activatable Core module with key `calendar`. It owns Calendar persistence in the `core_calendar` PostgreSQL schema. Chat does not own Calendar tables and future modules must not create separate calendar engines or query Calendar persistence directly.

Calendar remains usable without Chat. It provides private personal events and accepts projections from future owning modules without importing their persistence or domain internals.

## Personal events

A personal event belongs to exactly one user and supports title, description, start, end, all-day mode, text location, `Busy`/`Free` availability, multiple reminders, and daily, weekly, or monthly recurrence. Weekly recurrence may select ISO weekdays; all recurrence may end by date or occurrence count.

Recurring mutations explicitly target one occurrence, that occurrence and the future series, or the whole series. Recurrence is expanded in `Europe/Warsaw` local time so the intended wall-clock time survives DST transitions. Personal events never contain participants, invitations, public sharing, task completion, colors, or categories.

Users own a default reminder lead time, initially 15 minutes, and an email-delivery preference. An event may replace the default with zero or more event-specific reminder offsets. Notification dispatch remains owned by the later Phase 31 notification/reminder workstream; Calendar owns the persisted schedule and preference.

The regular Calendar surface is available at `/calendar` and provides Month, Week, Day, and Agenda views with localized create, edit, scoped recurring mutation, delete, and reminder-preference workflows.

## Public boundary

Calendar exposes only the cross-module operations already required by accepted Phase 31 behavior:

- `CalendarEventPublisher` lets an owning module upsert or remove its event projection using a stable source module and source event identifier;
- `FreeBusyLookup` returns only user/time windows and cannot expose titles, descriptions, locations, reminders, or other private event content.

`CalendarEventContribution`, `FreeBusyQuery`, `FreeBusyWindow`, and `FreeBusyConflict` are immutable boundary DTOs. `FreeBusyLookup` exposes busy windows and per-user conflict counts only. A conflict is advisory and never blocks event creation. Meeting definitions and invitations remain Chat-owned; Chat publishes authorized Meeting events through `CalendarEventPublisher` instead of writing Calendar tables.

## Authorization and privacy

The module catalog owns `calendar.index` and route-aligned personal-event create, update, and delete permissions. Permission checks do not replace ownership checks: a user may mutate only their own personal events, and Administrator status does not grant access to another user's private event details.

The ordinary workspace and `communication.access` starter bundles include standard Calendar use, personal-event mutation, and reminder-preference management. Backend ownership checks remain mandatory even when UI actions are hidden by route availability.

Calendar persistence lives exclusively in `core_calendar`: personal events, reminders, recurrence exceptions, user preferences, and contributed event projections. Free/Busy responses intentionally omit event content. Administrator status and another user's Calendar permissions do not grant private-event access.

## Verification

Unit tests cover recurrence rules and Warsaw DST behavior. Feature tests cover persistence, reminders, preferences, owner-only access, privacy-safe Free/Busy, and non-blocking overlaps. Playwright covers the four views and visible personal-event and preference workflows using deterministic non-production fixtures.
