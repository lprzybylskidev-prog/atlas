<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Presentation\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

final class DestroyRoleController
{
    public function __invoke(string $role): RedirectResponse
    {
        $roleId = DB::table('roles')->where('name', $role)->where('guard_name', 'web')->value('id');

        if (is_int($roleId) && ! DB::table('model_has_roles')->where('role_id', $roleId)->exists()) {
            DB::table('role_has_permissions')->where('role_id', $roleId)->delete();
            DB::table('roles')->where('id', $roleId)->delete();
        }

        return redirect()->route('admin.authorization.roles.index')->with('success', 'Role delete was attempted.');
    }
}
