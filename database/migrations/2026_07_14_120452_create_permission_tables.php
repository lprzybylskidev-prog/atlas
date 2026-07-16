<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $teams = config()->boolean('permission.teams');
        $permissionsTable = config()->string('permission.table_names.permissions');
        $rolesTable = config()->string('permission.table_names.roles');
        $modelHasPermissionsTable = config()->string('permission.table_names.model_has_permissions');
        $modelHasRolesTable = config()->string('permission.table_names.model_has_roles');
        $roleHasPermissionsTable = config()->string('permission.table_names.role_has_permissions');
        $modelMorphKey = config()->string('permission.column_names.model_morph_key');
        $teamForeignKey = config()->string('permission.column_names.team_foreign_key');

        $rolePivotConfig = config('permission.column_names.role_pivot_key');
        $permissionPivotConfig = config('permission.column_names.permission_pivot_key');
        $pivotRole = is_string($rolePivotConfig) ? $rolePivotConfig : 'role_id';
        $pivotPermission = is_string($permissionPivotConfig) ? $permissionPivotConfig : 'permission_id';

        Schema::create($permissionsTable, static function (Blueprint $table) {
            $table->id();
            $table->ulid('public_id')->unique();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($rolesTable, static function (Blueprint $table) use ($teamForeignKey, $teams) {
            $table->id();
            $table->ulid('public_id')->unique();
            if ($teams || config('permission.testing')) {
                $table->unsignedBigInteger($teamForeignKey)->nullable();
                $table->index($teamForeignKey, 'roles_team_foreign_key_index');
            }
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            if ($teams || config('permission.testing')) {
                $table->unique([$teamForeignKey, 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($modelHasPermissionsTable, static function (Blueprint $table) use ($modelMorphKey, $permissionsTable, $pivotPermission, $teamForeignKey, $teams) {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type');
            $table->unsignedBigInteger($modelMorphKey);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($permissionsTable)
                ->restrictOnDelete();
            if ($teams) {
                $table->unsignedBigInteger($teamForeignKey);
                $table->index($teamForeignKey, 'model_has_permissions_team_foreign_key_index');

                $table->primary([$teamForeignKey, $pivotPermission, $modelMorphKey, 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([$pivotPermission, $modelMorphKey, 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            }
        });

        Schema::create($modelHasRolesTable, static function (Blueprint $table) use ($modelMorphKey, $pivotRole, $rolesTable, $teamForeignKey, $teams) {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type');
            $table->unsignedBigInteger($modelMorphKey);
            $table->index([$modelMorphKey, 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id')
                ->on($rolesTable)
                ->restrictOnDelete();
            if ($teams) {
                $table->unsignedBigInteger($teamForeignKey);
                $table->index($teamForeignKey, 'model_has_roles_team_foreign_key_index');

                $table->primary([$teamForeignKey, $pivotRole, $modelMorphKey, 'model_type'],
                    'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([$pivotRole, $modelMorphKey, 'model_type'],
                    'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create($roleHasPermissionsTable, static function (Blueprint $table) use ($permissionsTable, $pivotPermission, $pivotRole, $rolesTable) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id')
                ->on($permissionsTable)
                ->restrictOnDelete();

            $table->foreign($pivotRole)
                ->references('id')
                ->on($rolesTable)
                ->restrictOnDelete();

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        $cacheStore = config()->string('permission.cache.store');
        $cacheStore = $cacheStore !== 'default' ? $cacheStore : null;
        $cacheKey = config()->string('permission.cache.key');

        app('cache')
            ->store($cacheStore)
            ->forget($cacheKey);
    }

    public function down(): void
    {
        $permissionsTable = config()->string('permission.table_names.permissions');
        $rolesTable = config()->string('permission.table_names.roles');
        $modelHasPermissionsTable = config()->string('permission.table_names.model_has_permissions');
        $modelHasRolesTable = config()->string('permission.table_names.model_has_roles');
        $roleHasPermissionsTable = config()->string('permission.table_names.role_has_permissions');

        Schema::dropIfExists($roleHasPermissionsTable);
        Schema::dropIfExists($modelHasRolesTable);
        Schema::dropIfExists($modelHasPermissionsTable);
        Schema::dropIfExists($rolesTable);
        Schema::dropIfExists($permissionsTable);
    }
};
