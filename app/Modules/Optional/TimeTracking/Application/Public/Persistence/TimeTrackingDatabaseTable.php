<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class TimeTrackingDatabaseTable
{
    public const USER_TEAM_SETTINGS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.user_team_settings';

    public const WORK_SESSIONS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.work_sessions';

    public const MODULE_CONTEXT_SEGMENTS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.module_context_segments';

    public const BREAKS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.breaks';

    public const BREAK_REMINDERS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.break_reminders';

    public const OTHER_WORK_CATEGORIES = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.other_work_categories';

    public const OTHER_WORK = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.other_work';

    public const MAINTENANCE_WINDOWS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.maintenance_windows';

    public const MAINTENANCE_AFFECTED_SESSIONS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.maintenance_affected_sessions';

    public const CORRECTION_REQUESTS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.correction_requests';

    public const CORRECTION_PROPOSALS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.correction_proposals';

    public const CORRECTION_HISTORY = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.correction_history';

    public const CLOSED_PERIOD_OVERRIDES = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.closed_period_overrides';

    public const SETTLEMENT_SETTINGS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.settlement_settings';

    public const SETTLEMENT_PERIODS = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.settlement_periods';

    public const BREAK_POLICIES = DatabaseSchema::OPTIONAL_TIME_TRACKING.'.break_policies';
}
