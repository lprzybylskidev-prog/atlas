## Phase 18 — Administrative mode and impersonation

**Status:** `not started`

## Objective

Implement Admin mode, high-risk reauthentication, account sensitivity, and impersonation after sessions, audit, settings, active-team/module enforcement, shared UI, and manager scope are available.

## Dependencies

- [Phase 9 — Shared UI components](phase-09-shared-ui.md)
- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Phase 13 — Sessions and active team](phase-13-sessions-active-team.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 17 — Manager hierarchy](phase-17-manager-hierarchy.md)
- [Administrative mode and impersonation](../architecture/admin-mode-and-impersonation.md)
- [Security baseline](../architecture/security-baseline.md)

## Implementation contract

- Administrators use ordinary user accounts plus an explicit administrative mode.
- Entering Admin mode requires reauthentication.
- Reauthentication requires the current password and MFA when MFA is active or required.
- Administrative mode has two simultaneous validity limits:
  - inactivity timeout: 30 minutes since the last administrative action;
  - absolute lifetime: 4 hours since entering administrative mode.
- Only activity performed inside administrative context refreshes the 30-minute inactivity timer.
- Ordinary application activity outside Admin does not extend administrative mode.
- Administrative timeout values are globally configurable security settings, not per-user preferences.
- When administrative mode expires:
  - terminate active impersonation immediately;
  - return the actor to ordinary user mode;
  - require full reauthentication before entering Admin again.
- Logout, account lock, account deactivation, password change, MFA reset, or session invalidation terminate administrative mode immediately.
- High-risk operations require a separate fresh reauthentication even while administrative mode remains active.
- Fresh high-risk authorization is valid for 5 minutes and is scoped to high-risk administrative operations.
- High-risk operations include at least:
  - hard delete;
  - irreversible anonymization;
  - MFA reset;
  - changing administrator permissions;
  - overriding an impersonation block;
  - closed-period TimeTracking corrections;
  - any later operation explicitly classified as high risk.
- Expiry of the 5-minute high-risk authorization does not end ordinary administrative mode; it only requires reauthentication before another high-risk operation.
- While the Admin session remains valid, the administrator may start multiple impersonations without repeating password/MFA every time.
- High-risk operations may independently require fresh reauthentication.
- Admin has functional access to everything through permissions, normal use cases, validations, module checks, and audit. There is no hidden bypass.
- Admin can search/select a system user and impersonate them.
- Impersonation supports ordinary users and managers, but not:
  - the same administrator;
  - another administrator;
  - technical/service/integration accounts.
- Impersonation uses the explicit account sensitivity classification defined below. Technical/service/integration accounts are never impersonable; sensitive human accounts require the dedicated override permission, fresh high-risk reauthentication, mandatory reason, and enhanced audit.
- Starting impersonation requires dedicated permission and a mandatory reason.
- If the selected user has one team, enter it automatically. With multiple teams, Admin selects one of that user's teams.
- During impersonation the system behaves exactly as for the selected user: same teams, active team, permissions, manager relationships, menu, modules, limits, and restrictions.
- A real active user session is not interrupted or taken over. Admin sees a warning that the user is active.
- Impersonation has no artificial time limit.
- It ends on explicit exit, logout, session expiry/invalidation, permission loss, Admin block/deactivation, impersonated-user deactivation, active-team access loss, or critical Admin security changes such as MFA reset.
- After a new login, Admin always starts in normal mode. Impersonation is never restored.
- Business actions during impersonation are real production actions.
- Audit stores both the actual administrator and the impersonated user context plus an impersonation session ID.
- Actions that cause external effects—email, external API, external export, financial processing—show an additional real-effect warning.
- Impersonation is not a global sandbox.
- TimeTracking behavior may be simulated for UI/permission testing but must be fully isolated from official time records, live employee status, manager tasks, and reports.
- During impersonation backend blocks password change, MFA setup/reset, email change, session management, role/permission/team changes, deactivation, deletion, nested impersonation, and Admin-panel entry as the impersonated user.
- These operations require exiting impersonation.
- Every page has an unavoidable banner showing impersonated user, active team, reason, and `Exit impersonation`.
- Header or favicon may also change.
- Destructive dialogs repeat that impersonation is active.
- Users are not notified in real time by default, but may see impersonation events in their security history.
- Audit supports a session detail page with start, end, reason, Admin, user, team changes, successful operations, and rejected attempts.
- Account sensitivity is an explicit classification independent of role/team assignment.
- Technical/service/integration accounts are never impersonable.
- A sensitive human account requires a dedicated override permission, fresh high-risk reauthentication, reason, and enhanced audit.
- A target with effective administrator-level access in any team/global context is globally non-impersonable.
- TimeTracking simulation during impersonation never writes official TimeTracking tables/events, settlements, manager feeds, or reports.
- Any simulation state is stored only in an impersonation-session-scoped ephemeral namespace and is deleted when impersonation ends.

## Tasks

- [ ] Implement explicit account sensitivity classification and management/audit rules.
- [ ] Evaluate target administrator status globally across all effective assignments before impersonation.
- [ ] Implement dedicated high-risk sensitive-account override.
- [ ] Implement ephemeral impersonation-scoped TimeTracking simulation storage.
- [ ] Prove simulation never reaches official records, events, manager feeds, settlements, or reports.
- [ ] Delete simulation state when impersonation ends or expires.
- [ ] Implement explicit administrative mode.
- [ ] Require reauthentication when entering administrative mode.
- [ ] Require current password and MFA when active or required.
- [ ] Implement a 30-minute administrative inactivity timeout based only on administrative activity.
- [ ] Implement a 4-hour absolute administrative-mode lifetime.
- [ ] Make both administrative timeout values globally configurable security settings.
- [ ] Ensure ordinary application activity does not refresh administrative-mode inactivity.
- [ ] End active impersonation when administrative mode expires.
- [ ] Return the actor to ordinary user mode after administrative expiry.
- [ ] Terminate administrative mode on logout, lock, deactivation, password change, MFA reset, and session invalidation.
- [ ] Implement a separate 5-minute fresh-reauthentication window for high-risk operations.
- [ ] Classify hard delete, anonymization, MFA reset, administrator permission changes, impersonation-block override, and closed-period corrections as high risk.
- [ ] Add tests for inactivity expiry, absolute expiry, immediate security invalidation, impersonation termination, and high-risk reauthentication expiry.
- [ ] Define administrative-session validity.
- [ ] Allow multiple impersonations while admin session remains valid.
- [ ] Require dedicated impersonation permission.
- [ ] Require impersonation reason.
- [ ] Prevent impersonating self.
- [ ] Prevent impersonating administrators.
- [ ] Prevent impersonating technical, service, and integration accounts.
- [ ] Add sensitive-account impersonation block.
- [ ] Add exceptional override with high-level permission and reauthentication.
- [ ] Support team choice for multi-team users.
- [ ] Preserve the real user's active session.
- [ ] Implement production-effective business actions during impersonation.
- [ ] Record actual administrator and impersonated context in audit.
- [ ] Add extra warning for external-effect operations.
- [ ] Isolate simulated TimeTracking state from official records.
- [ ] Block password, MFA, email, session, role, permission, team, deactivation, deletion, nested impersonation, and admin-panel access during impersonation.
- [ ] Add persistent impersonation banner.
- [ ] Add optional header or favicon visual change.
- [ ] Add `Exit impersonation`.
- [ ] End impersonation on logout or session expiry.
- [ ] End impersonation on permission loss, blocking, deactivation, user deactivation, team loss, session invalidation, or critical security change.
- [ ] Never restore impersonation after login.
- [ ] Add impersonation session ID.
- [ ] Build impersonation audit filters and detail view.
- [ ] Add user-visible security history entry without real-time notification by default.
- [ ] Commit administrative mode and impersonation.

## Completion criteria

- [ ] Admin mode and high-risk reauthentication are session-bound, setting-driven, audited, and invalidated by security changes.
- [ ] Impersonation follows the target user's permissions, teams, modules, manager scope, and restrictions without a hidden bypass.
- [ ] TimeTracking simulation is isolated before TimeTracking is implemented.
- [ ] Relevant tests and documentation are current.
