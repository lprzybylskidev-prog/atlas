<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Application\Public\Permissions;

final class TeamPermissionNames
{
    public const TEAMS_VIEW = 'teams.view';

    public const ADMIN_TEAMS_INDEX = 'admin.teams.index';

    public const TEAMS_CREATE = 'teams.create';

    public const TEAMS_UPDATE = 'teams.update';

    public const TEAMS_DELETE = 'teams.delete';

    public const MANAGERS_VIEW = 'teams.managers.view';

    public const MANAGERS_CREATE = 'teams.managers.create';

    public const MANAGERS_UPDATE = 'teams.managers.update';

    public const MANAGERS_TERMINATE = 'teams.managers.terminate';

    public const MANAGERS_TREE = 'teams.managers.tree';

    public const MANAGERS_HISTORY = 'teams.managers.history';

    public const MANAGERS_HEAD_UPDATE = 'teams.managers.head.update';
}
