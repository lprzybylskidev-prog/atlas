<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Database\Seeders\E2eVisibilitySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class ModuleActivationAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_module_activation_and_team_module_states(): void
    {
        $this->seed(E2eVisibilitySeeder::class);
        $admin = User::query()->where('email', E2eVisibilitySeeder::ADMIN_EMAIL)->firstOrFail();
        $team = DB::table(DatabaseTable::TEAMS)->first();

        self::assertIsObject($team);
        $teamPublicId = is_string($team->public_id) ? $team->public_id : '';
        self::assertNotSame('', $teamPublicId);

        $session = [
            'active_team_public_id' => $team->public_id,
            'auth.password_confirmed_at' => now()->unix(),
        ];

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/modules')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Index')
                ->where('table.key', 'admin.modules')
                ->has('modules')
            );

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/modules/identity')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Show')
                ->where('module.moduleKey', 'identity')
                ->where('module.readOnly', true)
                ->has('teams')
            );

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/teams/'.$teamPublicId.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Edit')
                ->has('moduleStates')
            );
    }

    public function test_direct_module_activation_endpoint_requires_permission(): void
    {
        $this->seed(E2eVisibilitySeeder::class);
        $limited = User::query()->where('email', E2eVisibilitySeeder::LIMITED_EMAIL)->firstOrFail();
        $team = DB::table(DatabaseTable::TEAMS)->first();

        self::assertIsObject($team);

        $this->actingAs($limited)
            ->withSession([
                'active_team_public_id' => $team->public_id,
                'auth.password_confirmed_at' => now()->unix(),
            ])
            ->get('/admin/modules')
            ->assertForbidden();
    }
}
