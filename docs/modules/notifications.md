# Notifications module

Canonical current behavior for notification types, channels, preferences, delivery, queueing, realtime updates, and audit.

## Notifications

Use one notification module.

Support:

- assigned user;
- team;
- typed event;
- in-app channel;
- email channel;
- user/channel/type preferences;
- queued delivery;
- read state;
- deep link;
- retention cleanup.

Persistence is owned by the `core_notifications` PostgreSQL schema:

- `notifications` stores typed notification payload metadata, title, body, severity, deep link, and non-sensitive structured data;
- `notification_recipients` stores user/team recipient scope, read state, in-app delivery state, and email delivery state;
- `notification_preferences` stores per-user type/channel preferences;
- `realtime_events` stores the minimal server-push event buffer for notifications and later queued progress/system-alert delivery.

Application UI:

- the top navigation avatar dropdown shows the latest 10 notifications near the logout action;
- truncated notification titles and bodies expose their full text through the shared tooltip pattern and remain selectable for normal browser copy operations;
- the avatar shows an unread-count badge;
- a notification sound is available at `/sounds/notification.wav` and is played when the browser receives a higher unread count after user interaction allows audio playback;
- the dropdown links to the full notification center;
- the full notification center uses the shared datatable foundation with page metrics, backend-applied status/severity/scope/type/link/date filters, saved views, row and bulk mark-as-read actions, and deep-link opening for notifications that carry a link;
- notification-center exports are intentionally not exposed through the Admin DataTable export endpoint because `/notifications` is a regular application surface for the current user's inbox rather than an Admin diagnostics table.

Operational command:

```bash
php artisan notifications:send --email=admin@example.test --severity=info --title-pl="Tytuł" --body-pl="Treść" --title-en="Title" --body-en="Body" --link=/notifications
```

The command accepts either `--user=PUBLIC_ID` or `--email=EMAIL`. Locale-specific title/body options are selected from the configured default application locale, not from the current browser language. This keeps stored and emailed notification content stable after delivery.

Avoid sensitive email content.

Maintenance command:

```bash
php artisan notifications:prune --read-days=90 --realtime-hours=72
```

Realtime foundation:

- `/realtime/events` exposes an authorized, active-team-aware event feed for the current user;
- the browser polls the feed and refreshes notification props when `notification.created` is received;
- operation progress events may refresh or update owning workflow state, but must not create visible toast stacks;
- `realtime:publish` can publish `sessions`, `system-alerts`, `operation-progress`, or documented custom topics;
- session invalidation, system alerts, and operation progress events share the same `core_notifications.realtime_events` buffer;
- WebSocket channel wiring remains reserved for workflows that genuinely require server push beyond the current minimal feed.

Example realtime commands:

```bash
php artisan realtime:publish system-alerts --title="Maintenance" --severity=warning --body="Maintenance window starts soon."
php artisan realtime:publish operation-progress --operation-type=import --operation-id=demo --status=running --progress=50
php artisan realtime:publish sessions --user=USER_PUBLIC_ID --session=SESSION_ID
```

Use the module for:

- reports;
- imports;
- integrations;
- jobs;
- security events;
- system alerts.
