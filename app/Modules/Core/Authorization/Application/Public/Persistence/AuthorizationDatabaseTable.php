<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class AuthorizationDatabaseTable
{
    public const PERMISSIONS = DatabaseSchema::CORE_AUTHORIZATION.'.permissions';

    public const ROLES = DatabaseSchema::CORE_AUTHORIZATION.'.roles';

    public const MODEL_HAS_PERMISSIONS = DatabaseSchema::CORE_AUTHORIZATION.'.model_has_permissions';

    public const MODEL_HAS_ROLES = DatabaseSchema::CORE_AUTHORIZATION.'.model_has_roles';

    public const ROLE_HAS_PERMISSIONS = DatabaseSchema::CORE_AUTHORIZATION.'.role_has_permissions';

    public const AUTHORIZATION_ONBOARDING_PACKAGES = DatabaseSchema::CORE_AUTHORIZATION.'.authorization_onboarding_packages';

    public const USER_ONBOARDING_PACKAGES = DatabaseSchema::CORE_AUTHORIZATION.'.user_onboarding_packages';
}
