# Identity, authentication, users, and sessions

Canonical module behavior for user lifecycle, passwords, login protection, MFA, sessions, activation, and authentication-related security.

## Authentication and Users

Use Fortify as backend authentication with Inertia/Vue UI.

Current technical ownership:

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

### Login protection

- rate limiting by user and IP;
- lock after 10 failed attempts;
- escalating lock durations;
- notify on suspicious attempts;
- administrator may unlock with permission;
- successful login resets failed-attempt count;
- generic messages must not reveal account existence;
- protect against brute force and credential stuffing.

### MFA

Support:

- TOTP;
- WebAuthn/passkeys;
- FIDO2 hardware keys;
- one-time recovery codes.

MFA may be required:

- globally;
- per team;
- per user;
- per permission;
- per sensitive operation.

MFA reset is a separate audited administrative flow.

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
