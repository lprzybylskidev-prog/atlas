## Phase 13 — Sessions and active team

**Status:** `not started`

## Objective

Implement Redis-backed sessions, active-team context, user/admin session management, and frontend network handling after settings, audit, UI, and table primitives are ready.

## Dependencies

- [Phase 9 — Shared UI components](phase-09-shared-ui.md)
- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 12 — Settings and localization](phase-12-settings-localization.md)
- [Identity, authentication, users, and sessions](../modules/identity-authentication-and-sessions.md)
- [Teams and manager hierarchy](../modules/teams-and-manager-hierarchy.md)
- [Realtime, network, and browser operations](../operations/realtime-network-and-browser.md)

## Implementation contract

- Sessions are stored in Redis.
- Every user may have individual `inactivity_timeout_minutes` and `session_max_lifetime_minutes`; do not mutate global framework configuration per request.
- Session state stores creation time and last activity.
- Activity resets only inactivity timeout, never the maximum lifetime.
- Users can view active sessions with device, browser, approximate IP location, login time, last activity, and team.
- Users can terminate one session or all other sessions.
- Admin can invalidate all sessions for a user.
- Password change, deactivation, MFA reset, manual lock, and security revocation invalidate sessions.
- Removing team access invalidates sessions operating in that team.
- After login, select the only available team automatically or require an explicit team choice when several exist.
- Active team is stored in session.
- Team switch reloads permissions, menu, module availability, and data, clears team-scoped frontend state, and is audited.
- A user may have many browser tabs in one session, synchronized as one logical session.
- TimeTracking later permits only one real active working device/session. A second-device login warns about the existing active session and requires confirmation before terminating it.
- Central frontend network handling covers offline/online, 401, 403, 419, 422, 429, and 500.
- Preserve only non-sensitive form state where safe.
- Retry only safe idempotent requests. Never automatically retry unsafe mutations or create CSRF retry loops.
- Browser local/session storage may contain only non-sensitive UI preferences, never tokens, PII, or business data.

## Tasks

- [ ] Store sessions in Redis.
- [ ] Add per-user inactivity timeout.
- [ ] Add per-user maximum session lifetime.
- [ ] Store session creation and last activity.
- [ ] Store device/browser metadata.
- [ ] Store approximate IP location.
- [ ] Store active team.
- [ ] Build user active-session list.
- [ ] Allow terminating one session.
- [ ] Allow terminating all other sessions.
- [ ] Allow admin to invalidate all sessions for a user.
- [ ] Invalidate sessions after password change.
- [ ] Invalidate sessions after deactivation.
- [ ] Invalidate sessions after MFA reset.
- [ ] Invalidate sessions after manual lock.
- [ ] Invalidate team-specific sessions after team access removal.
- [ ] Auto-select the only team.
- [ ] Require team choice when several teams exist.
- [ ] Implement explicit team switching.
- [ ] Reload permissions, menu, and data after switch.
- [ ] Clear team-scoped frontend state after switch.
- [ ] Audit team switching.
- [ ] Add shared frontend session-expiry handling.
- [ ] Add centralized handling for 401, 403, 419, 422, 429, and 500.
- [ ] Add offline/online handling and safe retry rules.
- [ ] Commit session and team context foundation.

## Completion criteria

- [ ] Session lifetime, invalidation, team selection, and team switching are backend-authoritative and audited.
- [ ] Later module activation, impersonation, notifications, manager, and TimeTracking phases can rely on one active-team/session contract.
- [ ] Frontend network/session expiry handling is centralized and does not store sensitive data.
- [ ] Relevant tests and documentation are current.
