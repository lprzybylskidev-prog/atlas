<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class FeatureFlagsDatabaseTable
{
    public const GLOBAL_VALUES = DatabaseSchema::OPTIONAL_FEATURE_FLAGS.'.feature_flag_global_values';

    public const TEAM_VALUES = DatabaseSchema::OPTIONAL_FEATURE_FLAGS.'.feature_flag_team_values';

    public const HISTORY = DatabaseSchema::OPTIONAL_FEATURE_FLAGS.'.feature_flag_history';
}
