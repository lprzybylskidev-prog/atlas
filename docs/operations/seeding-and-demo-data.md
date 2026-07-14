# Seeding and demo data

Canonical operational rules for mandatory technical seeders and development-only demo data.

## Technical seeders

Technical seeders create only system records required for Atlas to operate safely.

Allowed technical seed data includes:

- starter roles;
- registered permission catalogs;
- required bootstrap reference records;
- module-owned reference values explicitly required by accepted contracts.

Technical seeders must be safe for production and idempotent.

## Demo seeders

Demo seeders are development-only tooling.

Demo seeders may create example:

- teams;
- users;
- manager relationships;
- notifications;
- TimeTracking scenarios;
- imports and process statuses;
- later neutral or debt-collection demonstration scenarios.

Demo users use reserved non-production addresses such as `example.test`.

Known demo credentials are permitted only in local or development environments, must be documented clearly, and must never be reusable in production.

## Demo reset

Atlas provides one explicit command to recreate or reset the complete demo environment after the application foundation exists.

The demo reset command must refuse to run outside approved local or development environments.

Production deployment commands must never invoke demo seeders.

Automated tests use factories and explicit fixtures, never demo seeders or demo account credentials.

## Current development demo account

The default database seeder currently creates one local development preview account so the first frontend shell can be reviewed through the real Fortify login flow:

- email: `atlas@example.test`;
- password: `password`.

This account is for local development review only. It must not be reused as a production bootstrap credential.
