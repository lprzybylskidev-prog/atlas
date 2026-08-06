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
- verified email-address notification-type preferences;
- queued delivery;
- read state;
- deep link;
- retention cleanup.

Persistence is owned by the `core_notifications` PostgreSQL schema:

- `notifications` stores typed notification payload metadata, title, body, severity, deep link, and non-sensitive structured data;
- `notification_recipients` stores user/team recipient scope, read state, in-app delivery state, and email delivery state;
- `notification_email_addresses` stores the user's primary notification email and additional notification emails scoped to the active team context;
- `notification_email_preferences` stores per-email, per-team notification-type presets;
- `realtime_events` stores the minimal server-push event buffer for notifications and later queued progress/system-alert delivery.

Email delivery:

- in-app notifications always remain available for the recipient;
- the account primary email is trusted from the identity email-verification flow and is inserted as the primary notification address on demand for the notification's team context;
- every additional notification email must be confirmed through a short-lived signed token link before Atlas may deliver company notifications to it;
- each notification email address has its own enabled/disabled preset for every registered notification type in the active team context;
- new notification email addresses default to all currently registered notification types enabled for that team context;
- `App\Modules\Core\Notifications\Application\NotificationTypeCatalog` is the canonical registry for user-configurable notification types, their localization keys, body-preview keys, and the permissions that make each type relevant to a user.

When adding a new notification type, update `NotificationTypeCatalog`, add Polish and English labels/descriptions, and make sure default email preferences are created for that type. Existing user addresses receive the new type as enabled the next time their notification-email preferences are loaded or delivery prepares an email payload.

Application UI:

- the top navigation avatar dropdown shows the latest 10 notifications near the logout action;
- truncated notification titles and bodies expose their full text through the shared tooltip pattern and remain selectable for normal browser copy operations;
- the avatar shows an unread-count badge;
- a notification sound is available at `/sounds/notification.wav` and is played when the browser receives a higher unread count after user interaction allows audio playback;
- the dropdown links to the full notification center;
- the full notification center uses the shared datatable foundation with page metrics, backend-applied status/severity/scope/type/link/date filters, saved views, row and bulk mark-as-read actions, and deep-link opening for notifications that carry a link;
- the user profile panel lets a user add verified notification email addresses and decide which concrete notification types should be emailed to each address for the currently active team;
- the user profile panel shows only notification types the current user can realistically receive through their current permissions and active team state, so Admin-only or module-specific notification types are hidden from users without the matching access;
- when the current user has no visible notification types, the whole email notifications card is hidden in the user profile panel;
- TimeTracking currently does not register regular-user email preference types; break reminder records remain module/audit facts without user email notifications, and Other work does not create reminder notifications.
- notification-center exports are intentionally not exposed through the Admin DataTable export endpoint because `/user/notifications` is a regular application surface for the current user's inbox rather than an Admin diagnostics table.

Operational command:

```bash
php artisan notifications:send --email=admin@example.test --severity=info --title-pl="Tytuł" --body-pl="Treść" --title-en="Title" --body-en="Body" --link=/user/notifications
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
