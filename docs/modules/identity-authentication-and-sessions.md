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

Current persistence baseline:

- `users.id` is the internal BIGINT identifier;
- `users.public_id` is the public ULID identifier for exposed references;
- `App\Modules\Core\Identity\Domain\ValueObjects\UserPublicId` is the typed domain identifier for user public IDs;
- Identity persistence maps Eloquent user records to public user DTOs through `UserCredentialAccountMapper`;
- `users.first_password_set_at` records whether the account completed first-password setup;
- `users.is_active` and `users.deactivated_at` control whether an account may authenticate;
- `users.failed_login_attempts`, `users.login_lock_count`, and `users.login_locked_until` track persistent login locks;
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

Persistent login protection locks an account after 10 failed password attempts. Lock durations escalate according to `atlas.security.login_lock.durations_seconds`; defaults are 15 minutes, 30 minutes, and 60 minutes, with later locks using the last configured duration. A successful login resets `failed_login_attempts` and clears `login_locked_until`. When a persistent login lock is created, Atlas sends the user a suspicious-login notification.

Administrative login unlock is exposed through `App\Modules\Core\Users\Application\UnlockUserAccount`. It requires an actor, target account, and reason, clears `failed_login_attempts` and `login_locked_until`, and records an `audit_events` entry with action `user.login_unlock`. Rejected unlock attempts for missing target accounts are audited as `rejected`.

### MFA

Support:

- TOTP;
- WebAuthn/passkeys;
- FIDO2 hardware keys;
- one-time recovery codes.

TOTP MFA uses Laravel Fortify's two-factor backend with encrypted `two_factor_secret` and encrypted recovery codes on the user record. MFA must be confirmed before it is treated as enabled. Confirmed TOTP users receive a second-factor login challenge instead of a complete session after password validation. Recovery codes are generated as one-time fallback codes by Fortify and are hidden from serialized user output.

MFA requirements are evaluated by `App\Modules\Core\Identity\Application\Mfa\MfaRequirementEvaluator`. Requirements are configurable through `atlas.security.mfa.requirements` and can require MFA globally, for specific user public IDs, team public IDs, permissions, or operation keys. Later authorization/team phases connect this evaluator to concrete UI and permission workflows.

WebAuthn/passkey and FIDO2 hardware-key backend support uses `web-auth/webauthn-lib`. Credential storage is owned by `user_webauthn_credentials` and exposed through `App\Modules\Core\Identity\Application\WebAuthn\Contracts\WebAuthnCredentialRepository`. `WebAuthnOptionsFactory` generates WebAuthn registration options for platform passkeys and cross-platform hardware keys, plus authentication options from stored credentials. Browser-facing passkey screens and ceremonies are connected in later UI/authentication workflow work.

MFA may be required:

- globally;
- per team;
- per user;
- per permission;
- per sensitive operation.

MFA reset is a separate audited administrative flow exposed through `App\Modules\Core\Users\Application\ResetUserMfa`. It requires an actor, target account, and reason, clears TOTP secret, recovery codes, and confirmation timestamp, and records an `audit_events` entry with action `user.mfa_reset`.

### Sessions

Sessions use Redis.

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

Users can:

- view active sessions;
- terminate one;
- terminate all other sessions.

Administrators can invalidate all sessions for a user.

Invalidate sessions after:

- password change;
- deactivation;
- MFA reset;
- manual lock;
- removal of team access.

Active team is stored in the session.

On login:

- auto-select when exactly one team is available;
- otherwise require selection.

Team switching:

- is explicit;
- reloads permissions, menu, and data;
- clears team-scoped frontend state;
- is audited.

---
