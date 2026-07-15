<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules;

enum ModuleAccessDenialReason: string
{
    case NotDeployed = 'not_deployed';
    case MissingRequiredDependency = 'missing_required_dependency';
    case TechnicallyUnavailable = 'technically_unavailable';
    case GloballyInactive = 'globally_inactive';
    case TeamInactive = 'team_inactive';
    case InvalidActiveTeam = 'invalid_active_team';
    case PermissionDenied = 'permission_denied';
}
