<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Public\Permissions;

final class UserPermissionNames
{
    public const USERS_PROFILE = 'users.profile';

    public const USERS_PROFILE_AVATAR_IMAGE = 'users.profile.avatar-image';

    public const USERS_PROFILE_AVATAR_UPDATE = 'users.profile.avatar.update';

    public const USERS_PROFILE_PASSWORD_UPDATE = 'users.profile.password.update';

    public const USERS_PROFILE_NOTIFICATION_EMAILS_STORE = 'users.profile.notification-emails.store';

    public const USERS_PROFILE_NOTIFICATION_EMAILS_UPDATE = 'users.profile.notification-emails.update';
}
