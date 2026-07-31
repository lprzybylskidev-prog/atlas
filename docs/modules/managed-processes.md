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
- source type such as manual, retry, schedule, CLI, internal API, external API, file import, integration, maintenance, or system;
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

Runs that represent completed operational problems (`failed`, `succeeded_with_warnings`, `cancelled`, or `expired`) may be marked as handled by an authorized Admin operator. Handling is stored as a separate acknowledgement record with actor, reason, and timestamp; it does not rewrite the historical process status.

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

Managed Processes supports long-running single jobs as a normal operational shape. A process may run for many hours when importing high volumes, calling slow external APIs, rebuilding data, or executing module-owned maintenance logic. Atlas does not require those workflows to be split into multiple visible process runs or multiple technical queue jobs. The owning process decides whether to implement internal checkpoints, cursors, batching, or idempotent resume behavior. Admin still shows one process run, one detail screen, one timeline, and one retry/cancel surface for the operator.

Queue worker timeouts for managed-process and import lanes must therefore be long enough for expected operational scripts. Redis `retry_after` must be greater than the worker timeout to avoid duplicate execution of a still-running long job. If a container restart, deployment, infrastructure failure, or external API failure interrupts a long process, retry safety is owned by the process implementation and, for imports, by the import idempotency contract.

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
- Admin subviews for combined runs, registered definitions, and schedules; imports do not have a separate Admin route;
- process run list in `/admin/managed-processes`, combining ordinary process runs and import-linked runs with import key, source, file, and idempotency columns where applicable;
- row and bulk actions for marking failed, warning, cancelled, or expired runs as handled; handled runs remain in history and can be shown through the handling-status filter;
- process schedule management in `/admin/managed-processes/schedules`, with schedule creation handled by `/admin/managed-processes/schedules/create` and validated five-field cron expressions;
- dashboard/system-status signals for active runs, failed runs, warnings, queue backlog, schedule failures, and module deactivation blockers.

Run, retry, cancel, acknowledge, and schedule actions require backend authorization, active-team scope, ModuleGate checks, Admin mode, and audit. Manual definition starts are launched from the definitions subview through a modal instead of a separate start page. External-effect or irreversible processes require explicit confirmation and Admin mode/high-risk controls where applicable.

Handled failed and warning runs stop contributing to the Admin dashboard's managed-process warning signals unless a new run fails or completes with warnings.

Retry starts a new run with `source_type=retry` and retry lineage. It does not require the process definition to permit manual starts. Long-running processes may retry from the beginning or use module-owned idempotency/checkpoint state; the shared Managed Processes foundation records and displays the retry but does not prescribe the resume strategy.

A clean installation starts with an empty run history until real module-owned work is executed. The foundation does not ship demo no-op process definitions after Phase 25 cleanup.

## Deactivation and Operations

Managed processes participate in module deactivation guards. Unsafe active runs block module deactivation with exact process identifiers, status, and safe completion/cancellation options.

Queued jobs restore correlation, actor, team, module, and process-run context before execution.

Progress and terminal states publish notifications/realtime progress through the Notifications foundation. Terminal managed-process notifications include a deep link to the Admin run detail only when the recipient's current module and permission context allows `admin.managed-processes.show`; otherwise the notification is delivered without a deep link so user notification surfaces do not lead to an avoidable 403.

## Privacy lifecycle

Managed Processes registers `ManagedProcessDataLifecycleParticipant` with the shared `atlas.data_lifecycle_participants` tag.

The participant covers process runs, structured process logs, process schedules, and queued work payloads that contain a lifecycle subject identifier. Privacy previews report matching controlled copies as:

- `managed_processes.process_runs`;
- `managed_processes.process_logs`;
- `managed_processes.process_schedules`;
- `managed_processes.queued_work`.

Active process runs (`draft`, `queued`, `running`, or `waiting`) add the `managed_process_active_run` blocker so destructive privacy execution cannot mutate or remove process context while work is in flight.

For completed records, privacy execution preserves operational history and redacts controlled subject references from process run snapshots/summaries, process log messages/context/entity references, and schedule input/reason fields. Matching queued jobs are removed because they are pending derived work under project control. The participant is idempotent and does not delete completed run or schedule records.
