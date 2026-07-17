# Phase 13 — Sessions and active team

**Status:** `complete`

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
- Session metadata records device, browser, approximate IP location, login time, last activity, and team for security/admin use.
- Admin user tables show whether each user is currently online or offline in a default-visible column.
- Users do not receive a self-service active-session management screen in Phase 13.
- Later TimeTracking work depends on one real active working device/session per user; a login attempt from a second device shows a conflict screen with options to cancel login or continue here and terminate the previous working session.
- Multiple tabs in one browser session are one logical session and must not be treated as multiple working devices.
- Admin can invalidate all sessions for a user.
- Password change, deactivation, MFA reset, manual lock, forced email-change requirement, and security revocation invalidate sessions.
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

- [x] Store sessions in Redis.
- [x] Add per-user inactivity timeout.
- [x] Add per-user maximum session lifetime.
- [x] Store session creation and last activity.
- [x] Store device/browser metadata.
- [x] Store approximate IP location.
- [x] Store active team.
- [x] Build Redis-backed active-session metadata for security/admin use.
- [x] Add a default-visible online/offline column to the Admin users table.
- [x] Do not expose user self-service session termination UI.
- [x] Allow admin to invalidate all sessions for a user.
- [x] Invalidate sessions after password change.
- [x] Invalidate sessions after deactivation.
- [x] Invalidate sessions after MFA reset.
- [x] Invalidate sessions after manual lock.
- [x] Invalidate sessions after forcing a user email change.
- [x] Invalidate team-specific sessions after team access removal.
- [x] Auto-select the only team.
- [x] Require team choice when several teams exist.
- [x] Implement explicit team switching.
- [x] Reload permissions, menu, and data after switch.
- [x] Clear team-scoped frontend state after switch.
- [x] Audit team switching.
- [x] Add shared frontend session-expiry handling.
- [x] Add centralized handling for 401, 403, 419, 422, 429, and 500.
- [x] Add offline/online handling and safe retry rules.
- [x] Add second-device login conflict flow with cancel/continue-and-terminate choices before TimeTracking starts relying on single working sessions.
- [x] Commit session and team context foundation.

Notes:

- Phase 13 repaired the missing Phase 7 Admin user-team membership management flow so team access removal now invokes team-specific session invalidation.
- The user-facing active sessions screen was intentionally removed. Session management is an Admin/security operation; ordinary users resolve multi-device conflicts only at login.
- Phase 13 also repaired the Phase 7 starter-role naming model while integrating team-scoped user authorization. Ordinary starter roles now represent small functional permission bundles, not `user`/`manager` personae; the full-access bootstrap role is `system.administrator`.

## Completion criteria

- [x] Session lifetime, invalidation, team selection, and team switching are backend-authoritative and audited.
- [x] Later module activation, impersonation, notifications, manager, and TimeTracking phases can rely on one active-team/session contract.
- [x] Frontend network/session expiry handling is centralized and does not store sensitive data.
- [x] Relevant tests and documentation are current.
