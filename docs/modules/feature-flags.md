# Feature flags

Canonical current behavior for typed feature flags, rollout evaluation, scoping, history, audit, and Admin management.

## Scope

Atlas has an optional `FeatureFlags` module registered as `feature_flags`.

Feature flags are rollout controls only. They must not:

- grant permissions;
- activate modules;
- bypass `ModuleGate`;
- bypass backend authorization;
- replace lifecycle controls owned by a module.

Later modules may use feature flags only after their normal module activation and authorization checks already pass.

## Typed definitions

Feature flag definitions are explicit code-owned definitions under `App\Modules\Optional\FeatureFlags\Application`.

Current typed keys are:

- `reports.preview`;
- `privacy.workflow_preview`;
- `time_tracking.preview`.

Current flags are boolean, default disabled, team-scoped rollout flags for later roadmap phases.

## Values and precedence

Values are stored in the `optional_feature_flags` PostgreSQL schema:

- `feature_flag_global_values`;
- `feature_flag_team_values`;
- `feature_flag_history`.

Effective value precedence is:

1. selected team override when the flag supports team scope;
2. global value;
3. code definition default.

The public evaluator is `App\Modules\Optional\FeatureFlags\Application\Public\Contracts\FeatureFlagEvaluator`.

## Admin management

Admin management is available at `/admin/feature-flags`.

The screen shows registered definitions in the shared Admin DataTable with selected-team effective values, global/team sources, owner, lifecycle, filters, saved views, pagination, and export support. It also shows compact recent history. Administrators may update global values, update selected-team overrides, and clear selected-team overrides from row actions. Every mutation requires a reason.

Permissions:

- `admin.feature-flags.index`;
- `admin.feature-flags.global.update`;
- `admin.feature-flags.team.update`;
- `admin.feature-flags.team.clear`;
- `feature-flags.evaluate`.

The Admin route is protected by authentication, active team context, Admin mode, route permission, and module activation.

## Audit and history

Every global/team change stores append-only feature flag history with:

- flag key;
- scope;
- optional team;
- action;
- before value;
- after value;
- actor public ID;
- reason;
- timestamp.

Every change also records an Audit module event under module `feature_flags`.

Feature flag history is operational configuration evidence. It does not contain secrets.
