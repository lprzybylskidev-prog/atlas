<?php

declare(strict_types=1);

use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Spatie\Permission\DefaultTeamResolver;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return [
    'models' => [
        'permission' => Permission::class,
        'role' => Role::class,
        'team' => Team::class,
        'default_model' => null,
    ],

    'table_names' => [
        'roles' => DatabaseTable::ROLES,
        'permissions' => DatabaseTable::PERMISSIONS,
        'model_has_permissions' => DatabaseTable::MODEL_HAS_PERMISSIONS,
        'model_has_roles' => DatabaseTable::MODEL_HAS_ROLES,
        'role_has_permissions' => DatabaseTable::ROLE_HAS_PERMISSIONS,
    ],

    'column_names' => [
        'role_pivot_key' => null,
        'permission_pivot_key' => null,
        'model_morph_key' => 'model_id',
        'team_foreign_key' => 'team_id',
    ],

    'register_permission_check_method' => true,
    'register_octane_reset_listener' => false,
    'events_enabled' => false,
    'teams' => true,
    'team_resolver' => DefaultTeamResolver::class,
    'use_passport_client_credentials' => false,
    'display_permission_in_exception' => false,
    'display_role_in_exception' => false,
    'enable_wildcard_permission' => false,

    'cache' => [
        'expiration_time' => DateInterval::createFromDateString('24 hours'),
        'key' => 'spatie.permission.cache',
        'store' => 'default',
    ],
];
