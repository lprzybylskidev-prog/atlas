<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Infrastructure\Persistence;

use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

final class SpatiePublicIdHooks
{
    public static function register(): void
    {
        Permission::creating(static function (Permission $permission): void {
            if (! is_string($permission->getAttribute('public_id')) || $permission->getAttribute('public_id') === '') {
                $permission->setAttribute('public_id', (string) Str::ulid());
            }
        });

        Role::creating(static function (Role $role): void {
            if (! is_string($role->getAttribute('public_id')) || $role->getAttribute('public_id') === '') {
                $role->setAttribute('public_id', (string) Str::ulid());
            }
        });
    }
}
