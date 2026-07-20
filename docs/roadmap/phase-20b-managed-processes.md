# Phase 20b — Managed processes, process logs, and scheduler

**Status:** `complete`

## Objective

Implement a shared managed-process foundation before Imports and later long-running operational workflows create their own runners, logs, schedules, progress, and retry mechanisms.

## Dependencies

- [Phase 11 — Audit and security audit](phase-11-audit-security.md)
- [Phase 13 — Sessions and active team](phase-13-sessions-active-team.md)
- [Phase 14 — Module availability and activation](phase-14-module-activation.md)
- [Phase 15 — Notifications and realtime foundation](phase-15-notifications-realtime.md)
- [Phase 16 — Admin operations and health](phase-16-admin-health.md)
- [Phase 20 — Integrations](phase-20-integrations.md)
- [Phase 20a — Audit context and security category hardening](phase-20a-audit-hardening.md)
- [Managed processes module documentation](../modules/managed-processes.md)
- [Modular monolith architecture](../architecture/modular-monolith.md)

## Implementation contract

- Atlas uses one shared managed-process mechanism for long-running or scheduled operational work, whether the process reads external input or only system-owned data.
- Managed processes include imports, internal recalculations, end-of-day style runs, integration synchronizations, rebuilds, report/export generation, maintenance workflows, and later business batch operations.
- A process definition is declared explicitly by the owning module and includes:
  - stable process key;
  - owning module;
  - human label and description;
  - input schema or explicit no-input declaration;
  - permissions required to view, run, retry, cancel, and schedule;
  - supported scopes: global, team, or user/team;
  - queue name or execution lane;
  - concurrency policy;
  - retry policy;
  - cancellation policy;
  - schedule support;
  - ModuleGate participation;
  - deactivation-guard behavior for unsafe active runs.
- The shared foundation owns process definitions, run lifecycle, scheduling, process-log persistence, progress, queue dispatch, retry/cancel orchestration, Admin visibility, and notification integration.
- Owning modules retain all business rules and execution logic. The shared foundation must not contain debt-collection business decisions such as how interest is calculated, how EOD closes data, or how imported rows mutate business records.
- A process run stores:
  - public ID;
  - process key;
  - owning module;
  - scope and active team when applicable;
  - actor;
  - source type such as manual, schedule, CLI, internal API, external API, file import, integration, maintenance, or system;
  - input snapshot or no-input marker;
  - queue/job identifiers when queued;
  - status;
  - current stage;
  - progress current/total and progress label;
  - counters for success, info, warning, error, failed, skipped, retried, and processed items where meaningful;
  - correlation ID and optional causation ID;
  - started, queued, finished, cancelled, failed, and retried timestamps;
  - retry lineage;
  - result summary and safe error summary.
- Process statuses are explicit and stable, including at least `draft`, `queued`, `running`, `waiting`, `succeeded`, `succeeded_with_warnings`, `failed`, `cancelled`, and `expired`.
- Process runs are append-only for historical facts. Mutable columns may track current status/progress, but final history must remain auditable.
- Large and configured processes run through Redis queues and Horizon. Synchronous execution is allowed only for explicitly safe, short, low-volume processes.
- Queue selection, concurrency limits, and backpressure are explicit. If configured concurrency is exhausted, new runs remain queued instead of starting uncontrolled parallel work.
- Concurrency policies support at least:
  - one active run globally;
  - one active run per team;
  - one active run per actor;
  - configurable parallelism per process;
  - no overlap for scheduled runs when the previous run is still active.
- Every queued process job restores correlation, actor, team, module, and process-run context before execution.
- Process execution is idempotent according to the owning process definition. Retry behavior must be explicit and must not create duplicate visible business effects.
- Cancellation is cooperative. A process may be non-cancellable, cancellable before start only, or cancellable at safe checkpoints.
- A process that has external effects or irreversible effects must declare that risk and require appropriate confirmation, Admin mode/high-risk guard where applicable, reason, and audit.
- Process logs are structured data, not raw Laravel log lines.
- A process log event stores:
  - process run ID;
  - timestamp;
  - severity: `debug`, `info`, `success`, `warning`, or `error`;
  - event type: `message`, `stage`, `progress`, `checkpoint`, `row`, `entity`, `external_effect`, or `exception`;
  - stage;
  - message;
  - safe context JSON;
  - row number, entity public ID, external reference, or source reference when applicable;
  - error code and exception class when applicable;
  - retryable flag where meaningful;
  - correlation ID.
- Process logs must be safe for Admin UI. They must not store secrets, credentials, raw tokens, full sensitive payloads, or unnecessary personal data.
- Debug-level events are hidden by default and may be disabled or restricted by environment/configuration.
- The process log manager supports filtering by process, status, severity, stage, date/time, team, actor, source, entity reference, correlation ID, and retry lineage.
- Admin can view process definitions, runs, details, timeline/logs, progress, counters, input summary, result summary, queue state, retry/cancel availability, and schedule history.
- Admin can run only processes for which the actor is authorized, the owning module is active, the scope is valid, and the process definition permits manual starts.
- Admin can retry or cancel only when the definition allows it, the current run status supports it, and backend authorization passes.
- Admin can manage schedules only for process definitions that explicitly support scheduling.
- Schedule definitions store:
  - process key;
  - scope and team where applicable;
  - timezone;
  - validated five-field cron expression;
  - input snapshot;
  - enabled state;
  - next due time;
  - last run;
  - overlap policy;
  - creator/updater;
  - reason;
  - audit history.
- Schedule management does not expose arbitrary shell cron, command execution, filesystem access, or raw Artisan command text.
- Scheduler dispatch creates normal managed process runs so scheduled, manual, CLI, API, and import starts appear in the same run history.
- Process run creation, start, completion, failure, cancellation, retry, schedule creation/update/disable, and rejected attempts are audited.
- Process progress and terminal states publish notifications/realtime progress through the Notifications foundation.
- Process runs contribute Admin dashboard/system-status signals for active runs, failed runs, warnings, queue backlog, schedule failures, and modules blocked by unsafe runs.
- Module deactivation guards use process definitions and active process runs to block unsafe deactivation without inspecting foreign tables.
- Processes respect active-team context, ModuleGate, backend authorization, impersonation context, maintenance mode, and operational health constraints.
- TimeTracking impersonation simulation must never become official managed-process execution or official process time data.

## Tasks

- [x] Create the shared managed-process module or shared foundation according to the module-boundary decision made during implementation.
- [x] Define process definition contracts.
- [x] Define process run lifecycle contracts and stable status enum.
- [x] Define structured process log event contracts and severity enum.
- [x] Add PostgreSQL persistence for process definitions where needed, process runs, process log events, process schedules, retry lineage, and safe summaries.
- [x] Implement process run creation for manual, scheduled, CLI, API, integration, import, and system starts.
- [x] Implement queue dispatch and execution wrapper with context restoration.
- [x] Implement explicit concurrency policies and queue backpressure behavior.
- [x] Implement cooperative cancellation policies and safe checkpoints.
- [x] Implement retry policies and idempotency hooks.
- [x] Implement structured process logger for `debug`, `info`, `success`, `warning`, `error`, `stage`, `progress`, and `checkpoint` events.
- [x] Implement progress, counters, current stage, and terminal summary updates.
- [x] Integrate notifications and realtime progress.
- [x] Integrate audit for lifecycle, schedule, retry, cancel, rejected, and high-risk process actions.
- [x] Enforce ModuleGate, active-team context, permissions, Admin mode/high-risk requirements where applicable, and impersonation/external-effect safeguards.
- [x] Register module deactivation guards for unsafe active process runs.
- [x] Add schedule definition management and scheduler dispatch.
- [x] Prevent arbitrary shell cron, raw Artisan command execution, filesystem browsing, or unregistered process execution through Admin.
- [x] Build Admin process definitions list.
- [x] Build Admin process runs list with filters.
- [x] Build Admin process run detail with timeline/log manager, progress, counters, input summary, queue state, retry/cancel actions, and result summary.
- [x] Build Admin process schedule management.
- [x] Add Admin dashboard/system-status signals for active/failed/warning process runs, backlog, schedule failures, and deactivation blockers.
- [x] Add development-only demo process definitions, schedules, and run/log fixtures.
- [x] Remove development-only demo process definitions, schedules, run/log fixtures, seeded file/log records, and artificial Admin panel data before clean-app handoff.
- [x] Update Imports, Search, Reports/Exports, privacy, and TimeTracking roadmap contracts to consume managed processes where applicable.
- [x] Update module, architecture, operations, and README documentation where current behavior or setup changes.
- [x] Add unit, integration, feature, and Playwright coverage for lifecycle, logs, queue execution, concurrency, schedules, Admin UI, authorization, ModuleGate, audit, notifications, and deactivation guards.
- [x] Commit managed processes, process logs, scheduler foundation, Admin frontend SPA polish, and clean-app handoff after final owner confirmation.

## Completion criteria

- [x] Long-running and scheduled operational work has one shared run, queue, log, progress, retry, cancel, schedule, audit, notification, and Admin visibility foundation.
- [x] External-input workflows and internal-only workflows use the same process-run/log manager while retaining module-owned business logic.
- [x] Process logs expose useful `info`, `success`, `warning`, `error`, stage, checkpoint, and progress events without leaking secrets or unnecessary personal data.
- [x] Admin can safely inspect, filter, retry, cancel, and schedule only registered authorized processes.
- [x] Queue backpressure and concurrency limits prevent uncontrolled parallel process execution.
- [x] Later Imports, Search rebuilds, Reports/Exports, EOD, interest recalculation, privacy/anonymization, integrations, and TimeTracking-adjacent workflows can rely on this foundation instead of inventing local runners.
- [x] Relevant tests and documentation are current.
