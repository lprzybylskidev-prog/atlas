<?php

declare(strict_types=1);

namespace App\Modules\Optional\ManagedProcesses\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class ManagedProcessesDatabaseTable
{
    public const DEFINITIONS = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_definitions';

    public const RUNS = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_runs';

    public const RUN_ACKNOWLEDGEMENTS = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_run_acknowledgements';

    public const LOG_EVENTS = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_log_events';

    public const SCHEDULES = DatabaseSchema::OPTIONAL_MANAGED_PROCESSES.'.process_schedules';
}
