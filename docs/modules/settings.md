# Settings module

Canonical current behavior for typed settings, scopes, precedence, validation, caching, administration, and audit.

### Settings

Provide a typed settings system.

Do not use an uncontrolled JSON blob.

Separate:

- user settings;
- team settings;
- global settings;
- security settings.

User-controlled examples:

- UI language;
- theme;
- notification preferences;
- default team;
- table preferences;
- dashboard preferences;
- accessibility preferences.

Admin-controlled examples:

- timeouts;
- MFA requirements;
- team assignments;
- roles;
- permissions;
- activation.

Security-setting changes are audited.

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
