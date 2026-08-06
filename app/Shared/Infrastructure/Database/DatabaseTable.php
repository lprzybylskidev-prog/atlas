<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Database;

final class DatabaseTable
{
    public const CACHE = DatabaseSchema::SHARED.'.cache';

    public const CACHE_LOCKS = DatabaseSchema::SHARED.'.cache_locks';

    public const JOBS = DatabaseSchema::SHARED.'.jobs';

    public const JOB_BATCHES = DatabaseSchema::SHARED.'.job_batches';

    public const FAILED_JOBS = DatabaseSchema::SHARED.'.failed_jobs';

    public const FAILED_JOB_ACKNOWLEDGEMENTS = DatabaseSchema::SHARED.'.failed_job_acknowledgements';

    public const OUTBOX_EVENTS = DatabaseSchema::SHARED.'.outbox_events';

    public const OUTBOX_CONSUMED_EVENTS = DatabaseSchema::SHARED.'.outbox_consumed_events';

    public const TABLE_SAVED_VIEWS = DatabaseSchema::SHARED.'.table_saved_views';

    public const TABLE_SAVED_VIEW_DEFAULTS = DatabaseSchema::SHARED.'.table_saved_view_defaults';

    public const MODULE_GLOBAL_STATES = DatabaseSchema::SHARED.'.module_global_states';

    public const MODULE_TEAM_STATES = DatabaseSchema::SHARED.'.module_team_states';

    public const MODULE_ACTIVATION_SCHEDULES = DatabaseSchema::SHARED.'.module_activation_schedules';

    public const MODULE_ACTIVATION_HISTORY = DatabaseSchema::SHARED.'.module_activation_history';

    public const SCHEDULER_HEARTBEATS = DatabaseSchema::SHARED.'.scheduler_heartbeats';

    public static function unqualified(string $table): string
    {
        $parts = explode('.', $table);

        return end($parts) ?: $table;
    }
}
