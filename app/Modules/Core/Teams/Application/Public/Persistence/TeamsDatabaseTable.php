<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class TeamsDatabaseTable
{
    public const TEAMS = DatabaseSchema::CORE_TEAMS.'.teams';

    public const TEAM_USER_ASSIGNMENTS = DatabaseSchema::CORE_TEAMS.'.team_user_assignments';

    public const TEAM_MANAGER_RELATIONSHIPS = DatabaseSchema::CORE_TEAMS.'.team_manager_relationships';
}
