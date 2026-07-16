# Admin module

Canonical current scope of the Admin module and administrative operational interfaces. Security-sensitive Admin mode and impersonation are documented separately.

## Admin Panel

The admin panel is built from the beginning in parallel with the application foundation.

Rules:

- English only;
- own Presentation layer;
- own routes;
- own layout;
- own menu;
- own permissions;
- same use cases and domain rules;
- extra audit and confirmation;
- no hidden superadmin bypass;
- not a generic CRUD incubator.

Entering `/admin...` routes requires authenticated users to confirm their password through the shared Identity confirmation screen.

Current Admin tables use the shared `DataTable` wrapper. Their first data column is `public_id`, they keep the most important operational columns visible by default, and they expose remaining safe non-secret table columns through the Columns menu. Search, sorting, pagination, and column visibility persist per table across refreshes and Admin actions. When an Admin index exposes safe row actions, the same supported mutating actions are also available as selected-row bulk actions.

Initial areas:

- Users
- Roles
- Permissions
- Teams
- Managers
- Logs
- Storage
- System Status
- Queues
- Failed Jobs
- Imports
- Integrations
- Feature Flags
- Audit
- Module activation

System Status includes:

- PostgreSQL;
- Redis;
- Meilisearch;
- queues;
- scheduler;
- storage;
- last deploy;
- application version.

Failed jobs support safe retry and strong mass-action confirmation.

Audit browser supports filtering by user, entity, action, team, correlation ID, actual actor, and impersonated user.

Logs and storage browsing must be secure and must not allow arbitrary server manipulation.

---
