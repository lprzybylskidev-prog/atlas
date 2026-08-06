# Identity, authentication, users, and sessions

Canonical module behavior for user lifecycle, passwords, login protection, MFA, sessions, activation, and authentication-related security.

## Authentication and Users

Use Fortify as backend authentication with Inertia/Vue UI.

Current technical ownership:

- `App\Modules\Core\Identity\IdentityModule` owns authentication entrypoints and Fortify integration.
- `App\Modules\Core\Users\UsersModule` is the Core module root for user lifecycle use cases that are not authentication mechanics.
- `App\Modules\Core\Users\Application\CreateUserAccount` creates administrator-managed user accounts and issues first-password links.
- Fortify action implementations live under `App\Modules\Core\Identity\Presentation\Fortify\Actions`.
- The Fortify service provider lives under `App\Modules\Core\Identity\Presentation\Providers`.
- The current user Eloquent persistence model lives under `App\Modules\Core\Identity\Infrastructure\Persistence`.
- `App\Models\User` is intentionally not used.
- Authentication and access-related Markdown emails use the Atlas-owned light email layout under `resources/views/vendor/mail` with Atlas branding and logo; fixed layout elements are localized through the application locale, while message body content remains owned by the notification/mailable.

Rules:

- public registration disabled;
- administrator creates users;
- administrator assigns teams;
- user receives a one-time first-password link;
- setting the first password verifies email;
- generated passwords are never sent;
- account must be active to log in;
- deactivation does not delete the account.

## Privacy Lifecycle

The Users module registers `UserAccountDataLifecycleParticipant` for `user` subjects. The participant treats the Identity user row as the stable technical anchor for audit and related foreign-key references, so hard-delete and anonymization execution redacts the account instead of physically deleting it.

Execution removes database-backed sessions, password reset tokens, password history rows, and WebAuthn credentials for the user. It clears MFA secrets/recovery codes, remember token, login-lock state, email verification, and first-password state, marks the account inactive, and replaces name/email with neutral redacted values. Producers must use public contracts rather than deleting `users` directly.

Current persistence baseline:

- `users.id` is the internal BIGINT identifier;
- `users.public_id` is the public ULID identifier for exposed references;
- `App\Modules\Core\Identity\Domain\ValueObjects\UserPublicId` is the typed domain identifier for user public IDs;
- Identity persistence maps Eloquent user records to public user DTOs through `UserCredentialAccountMapper`;
- `users.first_password_set_at` records whether the account completed first-password setup;
- `users.is_active` and `users.deactivated_at` control whether an account may authenticate;
- `users.failed_login_attempts`, `users.login_lock_count`, and `users.login_locked_until` track persistent login locks;
- session inactivity and maximum lifetime are resolved from global security defaults, then optional team overrides, then optional user-team assignment overrides. They are intentionally not stored directly on `users`, because the same user can work in several teams with different session policies;
- `users.account_sensitivity` stores the impersonation/account-sensitivity classification independently from role and team assignments. Supported values are `normal`, `sensitive`, `technical`, `service`, and `integration`;
- `user_password_histories` keeps the last 10 recorded password hashes per user;
- accounts without `first_password_set_at` cannot authenticate, even when active;
- inactive, locked, and invalid users receive the same generic failed-login behavior as invalid credentials.

Administrator-created accounts are stored active but awaiting first-password setup. The system stores only an internal random password hash during account creation and never sends that generated value to the user. The user receives a reset-token-backed first-password link. When the first password is set, `first_password_set_at` and `email_verified_at` are populated together.

Administrators may require email re-verification for an existing user. This clears `email_verified_at` and sends an Atlas-owned email verification link only; it does not clear `first_password_set_at`, reset the password, send a first-password link, or require the user to choose a new password.

User lifecycle activation and deactivation are exposed through `App\Modules\Core\Users\Application\ActivateUserAccount` and `App\Modules\Core\Users\Application\DeactivateUserAccount`. Deactivation sets `users.is_active` to false and records `users.deactivated_at`; activation sets `users.is_active` to true and clears `users.deactivated_at`. Deactivated users receive the same generic failed-login behavior as invalid credentials.

The guest reset password page is owned by Atlas at `/reset-password/{token}` and posts to Fortify's reset endpoint. Reset tokens are short-lived according to `config/auth.php`; Laravel's password broker deletes older tokens when a new token is created and deletes the used token after a successful reset.

Password reset link requests always return the same generic success response for existing and missing accounts. This prevents account-existence disclosure while still sending a reset email when an eligible account exists.

### Password policy

- minimum 12 characters;
- at least 1 uppercase letter;
- at least 2 digits;
- at least 1 special character;
- no 3 identical consecutive characters;
- reject known breached passwords;
- reject passwords based on user data;
- prevent reuse of the previous 10 passwords;
- short-lived one-time reset links;
- a new reset invalidates previous reset tokens;
- no arbitrary periodic password rotation.

The current Fortify password rule enforces length, mixed case, digits, symbols, uncompromised-password verification, rejection of three identical consecutive characters, and rejection of passwords based on available account data.

Password changes and resets reject reuse of the current password and the last 10 recorded password hashes. Successful changes record the previous password hash and the new password hash, then prune older history entries beyond the 10-entry limit. First-password setup records only the user-selected first password, not the internal account-creation secret.

### Login protection

- rate limiting by user and IP;
- lock after 10 failed attempts;
- escalating lock durations;
- notify on suspicious attempts;
- administrator may unlock with permission;
- successful login resets failed-attempt count;
- generic messages must not reveal account existence;
- protect against brute force and credential stuffing.

Rate limiting is defined through stable named code policies:

- `auth.login`;
- `auth.password-reset`;
- `auth.mfa`;
- `api.default`;
- `api.sensitive`;
- `exports.create`;
- `imports.create`;
- `admin.high-risk`.

Policy thresholds live in `config/atlas.php` and environment variables. They are not editable through Admin UI and there is no global disable switch. Missing mandatory authentication or MFA policies fail application boot.

Policy keys may combine IP, user, active team, API client, or an explicit combination. The policy model supports progressive delays and temporary locks; concrete lock counters for failed login escalation are implemented later in this phase.

Admin rate-limit visibility is exposed at `/admin/rate-limits`. The screen shows named policy definitions and aggregated rejection statistics through a shared DataTable with metrics, filters, saved views, and exports, but it cannot modify thresholds or disable policies. Administrators with the reset permission may clear exactly one limiter key for one selected policy after providing a reason. Resets are security-audited as `rate_limit.counter_reset` with the policy, limiter key, actor, reason, and correlation ID.

Persistent login protection locks an account after 10 failed password attempts. Lock durations escalate according to `atlas.security.login_lock.durations_seconds`; defaults are 15 minutes, 30 minutes, and 60 minutes, with later locks using the last configured duration. A successful login resets `failed_login_attempts` and clears `login_locked_until`. When a persistent login lock is created, Atlas sends the user a suspicious-login notification.

Administrative login unlock is exposed through `App\Modules\Core\Users\Application\UnlockUserAccount`. It requires an actor, target account, and reason, clears `failed_login_attempts` and `login_locked_until`, and records an `audit_events` entry with action `user.login_unlock`. Rejected unlock attempts for missing target accounts are audited as `rejected`.

### MFA

Support:

- TOTP;
- WebAuthn/passkeys;
- FIDO2 hardware keys;
- one-time recovery codes.

TOTP MFA uses Laravel Fortify's two-factor backend with encrypted `two_factor_secret` and encrypted recovery codes on the user record. MFA must be confirmed before it is treated as enabled. Confirmed TOTP users receive a second-factor login challenge instead of a complete session after password validation. Recovery codes are generated as one-time fallback codes by Fortify and are hidden from serialized user output.

The user profile panel at `/user` exposes TOTP MFA enable, confirmation, QR/recovery-code display, and disable actions using the existing Fortify backend routes. It shows only user-relevant account state and does not expose roles, permissions, or low-level security event history.

The Admin team create and edit forms expose team session overrides for inactivity logout minutes and maximum session lifetime minutes. Admin user create/edit assignment workflows expose the same values as user-team overrides. Empty user-team values inherit the effective team policy, and empty team values inherit global security defaults. Admin validation compares effective values after inheritance and rejects an inactivity timeout longer than the maximum session lifetime. Runtime session resolution also clamps inactivity to the effective maximum lifetime as a defensive safeguard.

The regular user profile panel shows only the effective inactivity logout time for the active team. It intentionally does not show the maximum session lifetime because that value is an administrative security bound rather than a user-facing setting.

MFA requirements are evaluated by `App\Modules\Core\Identity\Application\Mfa\MfaRequirementEvaluator`. Requirements are configurable through `atlas.security.mfa.requirements` and can require MFA globally, for specific user public IDs, team public IDs, permissions, or operation keys. Later authorization/team phases connect this evaluator to concrete UI and permission workflows.

WebAuthn/passkey and FIDO2 hardware-key backend support uses `web-auth/webauthn-lib`. Credential storage is owned by `user_webauthn_credentials` and exposed through `App\Modules\Core\Identity\Application\WebAuthn\Contracts\WebAuthnCredentialRepository`. `WebAuthnOptionsFactory` generates WebAuthn registration options for platform passkeys and cross-platform hardware keys, plus authentication options from stored credentials. Browser-facing passkey screens and ceremonies are connected in later UI/authentication workflow work.

MFA may be required:

- globally;
- per team;
- per user;
- per permission;
- per sensitive operation.

MFA reset is a separate audited administrative flow exposed through `App\Modules\Core\Users\Application\ResetUserMfa`. It requires an actor, target account, and reason, clears TOTP secret, recovery codes, and confirmation timestamp, and records an `audit_events` entry with action `user.mfa_reset`.

### Password Expiry

Atlas stores `password_changed_at` on user accounts and calculates password expiration through `App\Modules\Core\Identity\Application\PasswordExpiryPolicy`. The default lifetime is configured by `ATLAS_PASSWORD_EXPIRES_AFTER_DAYS` / `atlas.security.passwords.expires_after_days` and is 90 days.

Users can change their password from `/user` before expiry. The change uses the same password policy, password history checks, audit event, and session invalidation behavior as the Fortify password-update action. Expired passwords are rejected during login after password validation and before a session is established.

The user profile panel shows the password-valid-until date, MFA controls, cropped avatar image upload/removal, fallback avatar color controls, and notification email preferences. Uploaded avatar images are stored through the Core Files module, remain unavailable while pending/scanning/blocked, and take precedence over color initials only after Files marks them clean. Removing the image restores the color-based initials avatar and deletes the avatar file through the Files lifecycle workflow. The shared Inertia auth payload exposes the current clean avatar presentation so global navigation and later user-facing surfaces can render the same avatar consistently. The panel intentionally does not show role names, permission names, email verification status, first-password setup dates, active sessions, team lists, or detailed security logs.

### Sessions

Sessions use Redis.

Current implementation foundation:

- Laravel session payloads use the Redis session driver;
- `App\Modules\Core\Identity\Application\Public\Contracts\UserSessionRegistry` owns the session metadata index used by session administration and online/offline status;
- session metadata is stored in Redis under Atlas-owned keys and includes the Laravel session ID, user, creation time, last activity time, browser/device summary, approximate IP location, and active team;
- `App\Http\Middleware\EnforceUserSessionSecurity` enforces inactivity and maximum lifetime without mutating framework session configuration per request;
- `App\Http\Middleware\EnsureActiveTeamSelected` auto-selects a single available team and redirects multi-team users without a valid active team to the explicit team-selection route before they enter the application shell;
- second-device logins are stopped after successful password validation and before a new session is established unless the user explicitly confirms that Atlas should terminate the previous active session and continue on the current device.

Support individual:

- inactivity timeout;
- maximum session lifetime.

Session records include:

- creation time;
- last activity;
- device/browser;
- approximate IP location;
- team;
- login time.

Users do not manage active sessions through a self-service screen. Multiple browser tabs in one session are treated as one logical session.

A second-device login attempt lets the user either cancel login or continue on the new device and terminate the previous working session. Users resolve this only during login; they do not manage sessions from a separate self-service screen.

Administrators can invalidate all sessions for a user.

Invalidate sessions after:

- user logout;
- password change;
- deactivation;
- MFA reset;
- manual lock;
- forced email-change or email re-verification requirement;
- removal of team access.

Active team is stored in the session.

On login:

- auto-select when exactly one team is available;
- otherwise require selection on the authenticated team-selection screen before redirecting to `/`.

Team switching:

- is explicit;
- is an authenticated session action validated against the user's active team assignments rather than the route permission of the previous active team;
- reloads permissions, menu, and data;
- clears team-scoped frontend state;
- shows the same full-screen transition loader used by locale/theme changes;
- is audited.

The Admin users table includes a default-visible online/offline column. A user is considered online when the Redis session metadata index has recent activity within the current online window.

### Administrative mode and impersonation

Admin panel routes require explicit administrative mode rather than only ordinary route permission. Entering administrative mode uses the shared Laravel/Fortify-style `/user/confirm-password` flow, requires the current password, requires MFA when the account has confirmed MFA or MFA is globally required, and records security audit events. `/admin-mode` remains a compatibility entry route that marks administrative reauthentication as pending and redirects to `/user/confirm-password`; it no longer owns a separate form.

Administrative mode is stored in the current Laravel session with:

- a 30-minute inactivity timeout refreshed only by administrative routes;
- a 4-hour absolute lifetime;
- setting-driven timeout values under the security settings store;
- immediate termination when the Laravel session is invalidated.

High-risk administrative authorization is separate from administrative mode. It is confirmed through the same `/user/confirm-password` password+MFA flow, lasts 5 minutes by default, and is required for high-risk operations. Atlas classifies hard delete, irreversible anonymization, MFA reset, administrator permission changes, sensitive-account impersonation override, and closed-period TimeTracking corrections as high risk. Existing MFA reset, administrator role changes, and sensitive-account impersonation override enforce this guard now; future workflows attach to the same classified guard when implemented. Expiry of the high-risk window does not end administrative mode.

Impersonation is started from Admin user administration with a mandatory reason and selected target team. It requires active administrative mode, the `impersonation.start` permission, and target eligibility checks. Atlas blocks impersonation of self, administrators evaluated globally, inactive users, and accounts classified as `technical`, `service`, or `integration`. Accounts classified as `sensitive` require `impersonation.sensitive.override` plus fresh high-risk authorization.

During impersonation, ordinary application routes run as the impersonated user for active team, permissions, module visibility, menu, and restrictions. Admin routes are blocked until impersonation exits. The actual administrator, impersonated user, reason, team, and impersonation session ID are written to audit. A persistent application banner shows the impersonated user, active team, reason, and `Exit impersonation`; the application header also changes visual treatment.

Identity provides the web/session implementation of Audit's current actor context provider. It reads impersonation session state and exposes only the audit context needed by Audit persistence: actual administrator, impersonated user, impersonation session ID, and correlation ID. The Audit database recorder itself does not read HTTP requests or session keys.

Routes that perform external effects while impersonation is active must require an explicit real-effect acknowledgement before proceeding. The shared route middleware expects `impersonation_external_effect_acknowledged` for email, external API, external export, financial-processing, or equivalent future operations.

Administrators can view recent security events for all users, including impersonation events, at `/admin/audit/security-history` and filter the history by selected user. Atlas does not send real-time user notifications for impersonation by default.

TimeTracking UI simulation state for impersonation is stored only through `ImpersonationSimulationStore` under an impersonation-session-scoped ephemeral cache namespace. It is deleted when impersonation ends and is not written to official TimeTracking records, events, manager feeds, settlements, or reports.

---
