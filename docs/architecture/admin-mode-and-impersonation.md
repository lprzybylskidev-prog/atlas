# Administrative mode and impersonation

Canonical security and behavior contract for Admin mode, high-risk reauthentication, account sensitivity, impersonation, prohibited actions, TimeTracking simulation, visual indication, and audit.

## Administrative Mode and Impersonation

Administrators use ordinary user accounts with an explicit administrative mode.

Entering administrative mode requires reauthentication. Current Admin panel routes require Laravel password confirmation before access to `/admin...` pages and actions.

While the administrative session remains valid, multiple impersonations may be started without repeating password and MFA each time. High-risk operations may still require separate reauthentication.

### Impersonation

Administrators may impersonate ordinary users and managers.

They may not impersonate:

- other administrators;
- technical accounts;
- integration accounts;
- service accounts;
- themselves.

Account sensitivity is an explicit security classification stored independently from roles and team assignments, for example `normal`, `sensitive`, or `technical`.

Technical, service, and integration accounts are always non-impersonable. A `sensitive` human account blocks impersonation by default. An exceptional override requires a separate high-level permission, fresh high-risk reauthentication, an explicit reason, and enhanced audit.

The rule prohibiting impersonation of administrators is evaluated globally: if the target currently holds effective administrator-level access in any team or global context, the target cannot be impersonated.

Starting impersonation requires:

- active administrative mode;
- dedicated permission;
- mandatory reason;
- audit entry.

Impersonation has no artificial time limit.

It ends when:

- administrator exits manually;
- administrator logs out;
- session expires;
- session is invalidated;
- administrator loses impersonation permission;
- administrator is blocked or deactivated;
- impersonated user is deactivated;
- impersonated user loses access to active team;
- critical security state changes.

After a new login the administrator always starts in normal mode. Impersonation is never restored automatically.

### Impersonation behavior

The application behaves as it would for the impersonated user:

- same teams;
- same active team;
- same roles and permissions;
- same manager relationships;
- same menu;
- same available modules;
- same limits;
- same UI restrictions.

If one team exists, select it automatically. If several exist, require selection from teams available to that user.

The real user session is not interrupted even if the user is currently active.

Business actions during impersonation are real and production-effective.

Audit must store:

- actual administrator;
- impersonated user context;
- impersonation session ID;
- reason;
- team;
- operation;
- result.

External-effect actions such as email, API calls, external exports, and financial operations must show an additional warning before execution.

### Time tracking during impersonation

Time tracking UI, limits, breaks, and flows may be simulated for testing, but impersonation must not:

- alter official time records;
- create reportable work time;
- affect the real user's active session;
- notify the real user's manager as if the user performed the action;
- appear in live activity reports.

Simulation state must never be written to official TimeTracking tables, official event streams, manager live feeds, settlements, or reports.

When UI-flow simulation is needed, store it only in a dedicated impersonation-scoped ephemeral namespace, such as Redis, keyed by impersonation session ID and automatically deleted when impersonation ends or expires. It is never published as business events and never notifies managers.

The impersonated UI otherwise follows the target user's permissions and visibility, while the persistent impersonation banner and simulation markers remain visible to the administrator.

### Prohibited operations during impersonation

Backend must block:

- password changes;
- MFA configuration or reset;
- email address change;
- active-session management;
- role changes;
- permission changes;
- team membership changes;
- account deactivation;
- account deletion;
- nested impersonation;
- entering the admin panel as the impersonated user.

These operations require exiting impersonation and returning to administrative mode.

### Visual indication

Every page in impersonation mode must show a persistent, unavoidable banner containing:

- impersonated user;
- active team;
- impersonation reason;
- `Exit impersonation`.

The header or favicon may also change.

Destructive actions must remind the administrator that impersonation is active.

### Impersonation audit

Support filtering by:

- administrator;
- impersonated user;
- date/time;
- team;
- module;
- operation type;
- result;
- impersonation session ID;
- reason.

Every impersonation session has a detail view containing start, end, actor, user, reason, successful operations, and rejected attempts.

The impersonated user is not notified in real time by default, but may see the security-history record.

---
