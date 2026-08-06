<?php

declare(strict_types=1);

namespace App\Modules\Core\Users\Application\Permissions;

use App\Modules\Core\Users\Application\Public\Permissions\UserPermissionNames;
use App\Shared\Application\Modules\Contributions\Contracts\ModulePermissionContribution;
use App\Shared\Application\Modules\Contributions\ModulePermissionDefinition;

final class UserPermissionCatalog implements ModulePermissionContribution
{
    public const USERS_VIEW = 'users.view';

    public const USERS_PROFILE = UserPermissionNames::USERS_PROFILE;

    public const USERS_PROFILE_AVATAR_IMAGE = UserPermissionNames::USERS_PROFILE_AVATAR_IMAGE;

    public const USERS_PROFILE_AVATAR_UPDATE = UserPermissionNames::USERS_PROFILE_AVATAR_UPDATE;

    public const USERS_PROFILE_PASSWORD_UPDATE = UserPermissionNames::USERS_PROFILE_PASSWORD_UPDATE;

    public const USERS_PROFILE_NOTIFICATION_EMAILS_STORE = UserPermissionNames::USERS_PROFILE_NOTIFICATION_EMAILS_STORE;

    public const USERS_PROFILE_NOTIFICATION_EMAILS_UPDATE = UserPermissionNames::USERS_PROFILE_NOTIFICATION_EMAILS_UPDATE;

    public const ADMIN_USERS_INDEX = 'admin.users.index';

    public const ADMIN_USERS_CREATE = 'admin.users.create';

    public const ADMIN_USERS_STORE = 'admin.users.store';

    public const ADMIN_USERS_EDIT = 'admin.users.edit';

    public const ADMIN_USERS_UPDATE = 'admin.users.update';

    public const ADMIN_USERS_ACTIVATE = 'admin.users.activate';

    public const ADMIN_USERS_DEACTIVATE = 'admin.users.deactivate';

    public const ADMIN_USERS_VERIFY_EMAIL = 'admin.users.verify-email';

    public const ADMIN_USERS_REQUIRE_EMAIL_VERIFICATION = 'admin.users.require-email-verification';

    public const ADMIN_USERS_RESEND_FIRST_PASSWORD = 'admin.users.resend-first-password';

    public const ADMIN_USERS_UNLOCK = 'admin.users.unlock';

    public const ADMIN_USERS_RESET_MFA = 'admin.users.reset-mfa';

    public const ADMIN_USERS_INVALIDATE_SESSIONS = 'admin.users.invalidate-sessions';

    public const ADMIN_USERS_TEAMS_STORE = 'admin.users.teams.store';

    public const ADMIN_USERS_TEAMS_DESTROY = 'admin.users.teams.destroy';

    public const ADMIN_USERS_TEAMS_AUTHORIZATION_UPDATE = 'admin.users.teams.authorization.update';

    public const USERS_CREATE = 'users.create';

    public const USERS_UPDATE = 'users.update';

    public const USERS_ACTIVATE = 'users.activate';

    public const USERS_DEACTIVATE = 'users.deactivate';

    public const USERS_UNLOCK = 'users.unlock';

    public const USERS_MFA_RESET = 'users.mfa-reset';

    public const TEAM_SWITCH = 'team.switch';

    public function permissions(): array
    {
        return [
            new ModulePermissionDefinition(self::USERS_VIEW, 'View users.'),
            new ModulePermissionDefinition(self::USERS_PROFILE, 'View own user profile panel.'),
            new ModulePermissionDefinition(self::USERS_PROFILE_AVATAR_IMAGE, 'View own user profile avatar image.'),
            new ModulePermissionDefinition(self::USERS_PROFILE_AVATAR_UPDATE, 'Update own user profile avatar.'),
            new ModulePermissionDefinition(self::USERS_PROFILE_PASSWORD_UPDATE, 'Update own user profile password.'),
            new ModulePermissionDefinition(self::USERS_PROFILE_NOTIFICATION_EMAILS_STORE, 'Add own notification email addresses.'),
            new ModulePermissionDefinition(self::USERS_PROFILE_NOTIFICATION_EMAILS_UPDATE, 'Update own notification email preferences.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_INDEX, 'View user administration.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_CREATE, 'Open user creation.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_STORE, 'Create users through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_EDIT, 'Open user editing.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_UPDATE, 'Update users through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_ACTIVATE, 'Activate users through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_DEACTIVATE, 'Deactivate users through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_VERIFY_EMAIL, 'Verify user email through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_REQUIRE_EMAIL_VERIFICATION, 'Require user email verification again through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_RESEND_FIRST_PASSWORD, 'Resend first-password links through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_UNLOCK, 'Unlock users through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_RESET_MFA, 'Reset user MFA through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_INVALIDATE_SESSIONS, 'Invalidate all user sessions through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_TEAMS_STORE, 'Add user team access through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_TEAMS_DESTROY, 'Remove user team access through Admin UI.'),
            new ModulePermissionDefinition(self::ADMIN_USERS_TEAMS_AUTHORIZATION_UPDATE, 'Update user roles and direct permissions in a team through Admin UI.'),
            new ModulePermissionDefinition(self::USERS_CREATE, 'Create users.'),
            new ModulePermissionDefinition(self::USERS_UPDATE, 'Update users.'),
            new ModulePermissionDefinition(self::USERS_ACTIVATE, 'Activate users.'),
            new ModulePermissionDefinition(self::USERS_DEACTIVATE, 'Deactivate users.'),
            new ModulePermissionDefinition(self::USERS_UNLOCK, 'Unlock user login.'),
            new ModulePermissionDefinition(self::USERS_MFA_RESET, 'Reset user MFA.'),
            new ModulePermissionDefinition(self::TEAM_SWITCH, 'Switch own active team.'),
        ];
    }
}
