# Calendar

Canonical current boundary for the shared Core Calendar capability introduced in Phase 31.

## Ownership and availability

Calendar is a non-activatable Core module with key `calendar`. It owns Calendar persistence in the `core_calendar` PostgreSQL schema. Chat does not own Calendar tables and future modules must not create separate calendar engines or query Calendar persistence directly.

The W01 persistence catalog reserves owner-local tables for personal events, contributed events, reminders, and recurrence exceptions. Their executable persistence and behavior belong to P31-W02.

## Public boundary

Calendar exposes only the cross-module operations already required by accepted Phase 31 behavior:

- `CalendarEventPublisher` lets an owning module upsert or remove its event projection using a stable source module and source event identifier;
- `FreeBusyLookup` returns only user/time windows and cannot expose titles, descriptions, locations, reminders, or other private event content.

`CalendarEventContribution`, `FreeBusyQuery`, and `FreeBusyWindow` are immutable boundary DTOs. Meeting definitions and invitations remain Chat-owned; Chat publishes authorized Meeting events through `CalendarEventPublisher` instead of writing Calendar tables.

## Authorization and privacy

The module catalog owns `calendar.index` and route-aligned personal-event create, update, and delete permissions. Permission checks do not replace ownership checks: a user may mutate only their own personal events, and Administrator status does not grant access to another user's private event details.

The ordinary workspace and `communication.access` starter bundles include standard Calendar use and personal-event mutation. Full personal-event behavior, recurrence, reminders, views, and privacy-safe scheduling are the next sequential workstream, P31-W02.
