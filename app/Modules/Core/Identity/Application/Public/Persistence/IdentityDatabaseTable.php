<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Persistence;

use App\Shared\Infrastructure\Database\DatabaseSchema;

final class IdentityDatabaseTable
{
    public const USERS = DatabaseSchema::CORE_IDENTITY.'.users';

    public const PASSWORD_RESET_TOKENS = DatabaseSchema::CORE_IDENTITY.'.password_reset_tokens';

    public const USER_PASSWORD_HISTORIES = DatabaseSchema::CORE_IDENTITY.'.user_password_histories';

    public const USER_WEBAUTHN_CREDENTIALS = DatabaseSchema::CORE_IDENTITY.'.user_webauthn_credentials';

    public const RATE_LIMIT_REJECTIONS = DatabaseSchema::CORE_IDENTITY.'.rate_limit_rejections';

    public const SESSIONS = DatabaseSchema::CORE_IDENTITY.'.sessions';
}
