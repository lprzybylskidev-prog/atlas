<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Roles;

enum StarterRoleName: string
{
    case User = 'user';
    case Manager = 'manager';
    case Administrator = 'administrator';
}
