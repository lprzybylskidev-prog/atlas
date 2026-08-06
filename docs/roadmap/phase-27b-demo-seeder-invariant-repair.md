# Phase 27b — Demo and test seeder invariant repair

**Status:** `not started`

## Objective

Repair development demo, e2e, and bootstrap seed data so they create application data through the same public use cases, contracts, and invariant-preserving helpers as normal Atlas workflows wherever feasible.

## Dependencies

- [Phase 6 — Core identity and authentication](phase-06-identity-authentication.md)
- [Phase 7 — Authorization and teams](phase-07-authorization-teams.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 19 — Files](phase-19-files.md)
- [Phase 27 — Optional TimeTracking module](phase-27-time-tracking.md)
- [Identity, authentication, and sessions module documentation](../modules/identity-authentication-and-sessions.md)
- [Teams and manager hierarchy module documentation](../modules/teams-and-manager-hierarchy.md)
- [Seeding and demo data operations documentation](../operations/seeding-and-demo-data.md)

## Implementation contract

- Demo, e2e, and bootstrap seeders must not silently bypass application invariants for user accounts, teams, authorization, notifications, files, TimeTracking, or manager hierarchy.
- Seeders should use public Application contracts or dedicated invariant-preserving seed helpers when creating data that ordinary workflows also create.
- Direct Eloquent model writes are allowed only for simple persistence setup that has no business invariant beyond the model itself, or where the owning Application contract is not available yet.
- Direct query builder writes are allowed for high-volume deterministic fixture rows only after all required invariants are centralized in a named helper and the seeder documents why bulk insertion is acceptable.
- First administrator and demo user creation must preserve all current identity invariants, including public IDs, first-password state, password lifecycle timestamps, account sensitivity, email verification state, avatar defaults, MFA-related fields, and session/security defaults.
- Demo/e2e seeders must not duplicate permission, role, manager hierarchy, module activation, notification preference, file scanning, or TimeTracking business rules when a public contract exists.
- Seeder idempotency must be preserved. Running the same seeder repeatedly must not create duplicate demo users, teams, manager relationships, notification email rows, TimeTracking settings, or operational fixtures.
- Demo data may remain deterministic, but determinism must be achieved through explicit fixture definitions and stable lookup keys, not by bypassing application behavior.
- Any new application invariant introduced in later phases must include a check that seeders either use the normal creation path or explicitly set the invariant through a shared helper.

## Tasks

- [ ] Audit `DevelopmentBootstrapSeeder`, `DevelopmentDemoSeeder`, `E2eVisibilitySeeder`, and other seeders for direct model/query writes that bypass current Application contracts.
- [ ] Extract shared invariant-preserving helpers for demo/bootstrap user creation instead of duplicating `forceFill` user setup in multiple seeders.
- [ ] Route user creation through existing public account-creation contracts where feasible, while preserving deterministic verified demo login accounts where explicitly required.
- [ ] Repair team assignment, manager hierarchy, module activation, notification preference, file, and TimeTracking fixture creation to use public contracts or clearly named fixture builders.
- [ ] Add regression tests proving demo/e2e/bootstrap users receive current identity invariants and that seeders remain idempotent.
- [ ] Update operations documentation with the rule that seeders must preserve application invariants and document justified direct writes.
- [ ] Review `AGENTS.md` and add only a permanent repository-wide seeding rule if the operational documentation alone is insufficient.

## Completion criteria

- [ ] Seeder paths no longer bypass current user/account invariants.
- [ ] Direct writes that remain are justified, isolated, and covered by tests.
- [ ] Demo and e2e seeders remain deterministic and idempotent.
- [ ] Relevant documentation is current.
- [ ] The `WORKROAD.md` status is updated when the phase is completed.
