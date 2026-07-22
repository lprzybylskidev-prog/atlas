# Settings module

Canonical current behavior for typed settings, scopes, precedence, validation, caching, administration, and audit.

## Settings

Atlas has a typed Core `Settings` module registered as `settings`.

Settings are stored in separate PostgreSQL tables by scope:

- `settings_global_values`;
- `settings_team_values`;
- `settings_user_values`;
- `settings_security_values`.

The tables use JSONB for the physical value column, but callers do not write uncontrolled universal JSON blobs. All supported settings are addressed through explicit typed keys under `App\Modules\Core\Settings\Application\Enums` and validated before persistence.

Current typed key groups:

- global settings: default locale and default theme;
- team settings: default locale and default theme;
- user settings: UI language, theme, notification preferences, default team, table preferences, dashboard preferences, and accessibility preferences;
- security settings: idle session timeout, password-confirmation timeout, and MFA requirement.

## Defaults And Precedence

Defaults are explicit in `SettingsDefaults`.

Effective locale precedence is:

1. authenticated user language preference;
2. active team default locale;
3. safe temporary guest locale cookie;
4. global default locale;
5. Polish fallback.

Effective theme precedence is:

1. authenticated user theme preference;
2. active team default theme;
3. safe temporary guest theme cookie;
4. global default theme.

Polish is the default regular UI language.

## Caching And Validation

Settings reads are cached by exact scope and typed key. Writes validate the typed value and invalidate the matching cache entry.

Invalid values are rejected explicitly. Unsupported locales are rejected; supported regular UI locales are `pl` and `en`.

## Localization Preference

The `/locale` route stores the selected language as a typed user setting for authenticated users and also keeps the temporary `atlas_locale` cookie for guest/login flows. Inertia shared props expose the effective `locale` and supported locale list. Auth, regular application, and Admin shells use this same preference; Admin no longer forces English for user-facing interface text.

The `/theme` route stores the selected light/dark theme as a typed user setting for authenticated users and also keeps the temporary `atlas_theme` cookie for guest/login flows. Inertia shared props expose `preferences.theme` so frontend shells can initialize from the effective backend preference.

## Security Audit

Security-setting changes are recorded through the Audit module as security audit events with before and after values. Audit entries must not contain secrets or unnecessary personal data.

---

## Configuration

- no secrets in repository;
- `.env.example` must remain complete;
- developer `.env` must be updated in lockstep when configuration changes;
- use `env()` only in configuration files;
- production secrets come from Docker secrets or protected environment;
- validate critical configuration at startup;
- no silent fallback for critical values;
- use typed configuration for complex structures.

---
