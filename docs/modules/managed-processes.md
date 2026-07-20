# Managed processes

Canonical current behavior for managed operational processes, process runs, structured process logs, queue execution, progress, retry/cancel, schedules, and Admin visibility.

## Managed Processes

Atlas uses one shared optional managed-process foundation for long-running, scheduled, retryable, or operationally important work.

This foundation covers both:

- external-input workflows such as CSV, XLSX, XML, internal API, external API, and integration-driven imports;
- internal-only workflows such as recalculations, end-of-day style processing, rebuilds, maintenance, privacy/anonymization orchestration, report generation, and later business batch processes.

The managed-process foundation owns execution lifecycle, queue dispatch, run history, structured process logs, progress, schedules, retry/cancel orchestration, notifications, audit integration, and Admin visibility.

Owning modules retain business logic. The shared foundation does not decide how interest is calculated, how imported rows mutate business records, how EOD closes work, or how a business module validates its own invariants.

## Process Definitions

Every managed process is explicitly declared by its owning module through the public typed `ProcessDefinition`.

A definition includes:

- stable process key;
- owning module;
- label and description for Admin UI;
- supported scope: global, team, or user/team;
- input schema or explicit no-input declaration;
- permissions for view, run, retry, cancel, and schedule;
- queue or execution lane;
- concurrency policy;
- retry policy;
- cancellation policy;
- schedule support;
- ModuleGate participation;
- deactivation-guard behavior.

Unregistered processes cannot be started from Admin. Admin schedule management never exposes arbitrary shell cron, raw Artisan command text, server filesystem access, or unregistered command execution.

The foundation does not ship demo process definitions. Process definitions are registered only by owning modules or explicit test fixtures.

Cross-module process definitions and handlers use only public ManagedProcesses contracts. Public `ProcessDefinition`, `ProcessPermissions`, and `RetryPolicy` DTOs describe the process. `ManagedProcessHandler` identifies and executes the registered process, while `ManagedProcessReporter` lets owning modules write safe info checkpoints and progress or success updates without importing ManagedProcesses internal DTOs or enums.

## Process Runs

Every execution creates a process run.

A run stores:

- public ID;
- process key and owning module;
- source type such as manual, schedule, CLI, internal API, external API, file import, integration, maintenance, or system;
- actor and active team where applicable;
- input snapshot or no-input marker;
- queue and job identifiers where queued;
- status;
- current stage;
- progress current/total and progress label;
- counters for processed, success, info, warning, error, failed, skipped, and retried items where meaningful;
- correlation ID and optional causation ID;
- queued, started, finished, failed, cancelled, and retried timestamps;
- retry lineage;
- safe result and error summaries.

Statuses are stable and include at least:

- `draft`;
- `queued`;
- `running`;
- `waiting`;
- `succeeded`;
- `succeeded_with_warnings`;
- `failed`;
- `cancelled`;
- `expired`.

Large and configured processes can run through Redis queues and Horizon via the shared execution job. Synchronous execution is reserved for explicitly short, safe, low-volume processes and isolated automated-test fixtures.

Concurrency policies support one active run globally, one active run per team, one active run per actor, configurable parallelism, and no-overlap scheduled runs.

## Process Logs

Process logs are structured timeline events, not raw Laravel log lines.

Each log event stores:

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

Logs must be safe for Admin UI and must not contain secrets, credentials, raw tokens, full sensitive payloads, or unnecessary personal data.

The process log manager filters by process, status, severity, stage, date/time, team, actor, source, entity reference, correlation ID, and retry lineage.

## Schedules

Process schedules are managed records with validated five-field cron expressions, not arbitrary shell cron entries.

A schedule stores:

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

Scheduler dispatch creates normal managed process runs, so scheduled, manual, CLI, API, import, and integration starts appear in one run history.

## Admin Operations

Admin provides:

- process definition list;
- process run list with filters;
- process run detail with progress, counters, timeline/logs, input summary, queue state, retry/cancel actions, and result summary;
- four Admin tabs for runs, import executions, registered definitions, and schedules;
- process schedule management in `/admin/managed-processes/schedules` with validated five-field cron expressions;
- dashboard/system-status signals for active runs, failed runs, warnings, queue backlog, schedule failures, and module deactivation blockers.

Run, retry, cancel, and schedule actions require backend authorization, active-team scope, ModuleGate checks, Admin mode, and audit. External-effect or irreversible processes require explicit confirmation and Admin mode/high-risk controls where applicable.

The development reset does not seed artificial process runs, logs, import executions, or schedules. A clean installation starts with an empty run history until real module-owned work is executed.

## Deactivation and Operations

Managed processes participate in module deactivation guards. Unsafe active runs block module deactivation with exact process identifiers, status, and safe completion/cancellation options.

Queued jobs restore correlation, actor, team, module, and process-run context before execution.

Progress and terminal states publish notifications/realtime progress through the Notifications foundation.
