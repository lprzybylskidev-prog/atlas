# Calendar

Canonical current boundary for the shared Core Calendar capability introduced in Phase 31.

The current Phase 31 implementation uses the documented `Europe/Warsaw` recurrence behavior. Phase 36 is the explicit future migration owner for Team IANA business timezones, recurrence-timezone pinning, and separate named Business Calendars. This current-state document must not present those later capabilities as implemented before Phase 36 closes.

## Ownership and availability

Calendar is a non-activatable Core module with key `calendar`. It requires Identity for ownership and Notifications for reminder delivery, and owns Calendar persistence in the `core_calendar` PostgreSQL schema. Chat does not own Calendar tables and future modules must not create separate calendar engines or query Calendar persistence directly.

Calendar remains usable without Chat. It provides private personal events and accepts projections from owning modules without importing their persistence or domain internals. Chat currently contributes invitee-authorized Meeting projections.

## Personal events

A personal event belongs to exactly one user and supports title, description, start, end, all-day mode, text location, `Busy`/`Free` availability, multiple reminders, and daily, weekly, or monthly recurrence. Weekly recurrence may select ISO weekdays; all recurrence may end by date or occurrence count.

Recurring mutations explicitly target one occurrence, that occurrence and the future series, or the whole series. Recurrence is expanded in `Europe/Warsaw` local time so the intended wall-clock time survives DST transitions. Personal events never contain participants, invitations, public sharing, task completion, colors, or categories.

Users own a default reminder lead time, initially 15 minutes, and an email-delivery preference. An event may replace the default with zero or more event-specific reminder offsets. The minute scheduler runs `calendar:dispatch-reminders`, claims each user/occurrence/offset delivery idempotently, and publishes `calendar.personal.reminder` through Notifications. In-app delivery remains available; the Calendar preference and the recipient's Notifications email preference both apply before email is sent.

The regular Calendar surface is available at `/calendar` and provides Month, Week, Day, and Agenda views with localized create, edit, scoped recurring mutation, delete, and reminder-preference workflows.

## Public boundary

Calendar exposes only the cross-module operations already required by accepted Phase 31 behavior:

- `CalendarEventPublisher` lets an owning module upsert or remove its event projection using a stable source module and source event identifier;
- `FreeBusyLookup` returns only user/time windows and cannot expose titles, descriptions, locations, reminders, or other private event content.

`CalendarEventContribution`, its recurrence/mutation DTOs, `FreeBusyQuery`, `FreeBusyWindow`, and `FreeBusyConflict` are immutable boundary DTOs. Contributed recurrence is expanded through the same Warsaw-time engine and supports occurrence/future mutation overlays. Meeting projections carry product-safe kind, mode, location, cancellation, and owning-module deep-link metadata. `FreeBusyLookup` exposes busy windows and per-user conflict counts only. A conflict is advisory and never blocks event creation. Meeting definitions and invitations remain Chat-owned; Chat publishes authorized Meeting events through `CalendarEventPublisher` instead of writing Calendar tables.

## Authorization and privacy

The module catalog owns `calendar.index` and route-aligned personal-event create, update, and delete permissions. Permission checks do not replace ownership checks: a user may mutate only their own personal events, and Administrator status does not grant access to another user's private event details.

The ordinary workspace and `communication.access` starter bundles include standard Calendar use, personal-event mutation, and reminder-preference management. Backend ownership checks remain mandatory even when UI actions are hidden by route availability.

Calendar persistence lives exclusively in `core_calendar`: personal events, reminders, recurrence exceptions, reminder-delivery claims, user preferences, and contributed event projections. Free/Busy responses intentionally omit event content. Administrator status and another user's Calendar permissions do not grant private-event access.

## Verification

Unit tests cover recurrence rules and Warsaw DST behavior. Feature tests cover persistence, reminders, preferences, owner-only access, privacy-safe Free/Busy, non-blocking overlaps, and contributed Meeting recurrence/mutations. Playwright covers the four views, personal-event/preferences workflows, and Meeting mode/location presentation using deterministic non-production fixtures.
