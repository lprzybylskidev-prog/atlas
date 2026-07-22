<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessDenialReason;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Database\Seeders\E2eVisibilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class ModuleGateRuntimeTest extends TestCase
{
    use RefreshDatabase;

    public function test_runtime_module_gate_uses_registry_active_team_and_permission_state(): void
    {
        $this->seed(E2eVisibilitySeeder::class);

        $gate = app(ModuleGate::class);
        $admin = DB::table(DatabaseTable::USERS)->where('email', E2eVisibilitySeeder::ADMIN_EMAIL)->first();
        $limited = DB::table(DatabaseTable::USERS)->where('email', E2eVisibilitySeeder::LIMITED_EMAIL)->first();
        $team = DB::table(DatabaseTable::TEAMS)->first();

        self::assertIsObject($admin);
        self::assertIsObject($limited);
        self::assertIsObject($team);
        self::assertIsString($admin->public_id);
        self::assertIsString($limited->public_id);
        self::assertIsString($team->public_id);

        $allowed = $gate->inspect(new ModuleAccessRequest(
            moduleKey: 'identity',
            activeTeamPublicId: $team->public_id,
            userPublicId: $admin->public_id,
            requiredPermission: 'admin.system-status',
        ));

        $missingPermission = $gate->inspect(new ModuleAccessRequest(
            moduleKey: 'identity',
            activeTeamPublicId: $team->public_id,
            userPublicId: $limited->public_id,
            requiredPermission: 'admin.system-status',
        ));

        $notDeployed = $gate->inspect(new ModuleAccessRequest(
            moduleKey: 'not_deployed_test_module',
            activeTeamPublicId: $team->public_id,
            userPublicId: $admin->public_id,
            requiredPermission: 'admin.system-status',
        ));

        self::assertTrue($allowed->allowed);
        self::assertFalse($missingPermission->allowed);
        self::assertSame(ModuleAccessDenialReason::PermissionDenied, $missingPermission->denialReason);
        self::assertFalse($notDeployed->allowed);
        self::assertSame(ModuleAccessDenialReason::NotDeployed, $notDeployed->denialReason);
    }
}
