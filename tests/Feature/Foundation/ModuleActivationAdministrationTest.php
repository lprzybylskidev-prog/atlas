<?php

declare(strict_types=1);

namespace Tests\Feature\Foundation;

use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Application\Modules\Activation\ModuleActivationScheduleStatus;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Database\Seeders\E2eVisibilitySeeder;
use Illuminate\Contracts\Support\Arrayable;
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
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
        ];

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/modules')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Index')
                ->where('table.key', 'admin.modules')
                ->where('auth.availableAdminRoutes', fn ($routes): bool => $this->stringListContains($routes, 'admin.modules.index'))
                ->has('modules')
                ->has('filterOptions.categories')
                ->has('filterOptions.sources')
            );

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/modules?category=application')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Index')
                ->where('table.state.filters.category', 'application')
                ->has('modules', 0)
            );

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/modules?category=core&availability=yes&global=yes&team=yes&effective=yes&globalSupport=no&teamSupport=no&scheduled=no')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Index')
                ->where('table.state.filters.category', 'core')
                ->where('table.state.filters.availability', 'yes')
                ->where('table.state.filters.global', 'yes')
                ->where('table.state.filters.team', 'yes')
                ->where('table.state.filters.effective', 'yes')
                ->where('table.state.filters.globalSupport', 'no')
                ->where('table.state.filters.teamSupport', 'no')
                ->where('table.state.filters.scheduled', 'no')
                ->where('modules', fn ($modules): bool => $this->nonEmptyEveryRow($modules,
                    fn (array $module): bool => ($module['category'] ?? null) === 'core'
                        && ($module['technicallyAvailable'] ?? false) === true
                        && ($module['globallyEnabled'] ?? false) === true
                        && ($module['teamEnabled'] ?? false) === true
                        && ($module['effectiveEnabled'] ?? false) === true
                        && ($module['supportsGlobalActivation'] ?? true) === false
                        && ($module['supportsTeamActivation'] ?? true) === false
                        && ($module['scheduledChangesCount'] ?? 1) === 0
                ))
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
                ->has('history')
                ->has('schedules')
                ->has('exports')
            );

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/modules/feature_flags')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Show')
                ->where('module.moduleKey', 'feature_flags')
                ->where('module.readOnly', false)
                ->where('module.supportsGlobalActivation', true)
                ->where('module.supportsTeamActivation', true)
                ->has('teams')
            );

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/modules/feature_flags/teams/create?team='.$teamPublicId)
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/TeamConfiguration')
                ->where('module.moduleKey', 'feature_flags')
                ->where('selectedTeamPublicId', $teamPublicId)
                ->has('teams')
                ->has('history')
                ->has('schedules')
            );

        self::assertDatabaseMissing(DatabaseTable::MODULE_GLOBAL_STATES, [
            'module_key' => 'demo',
        ]);

        self::assertDatabaseMissing(DatabaseTable::MODULE_TEAM_STATES, [
            'module_key' => 'demo',
            'team_id' => $team->id,
        ]);

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/teams/'.$teamPublicId.'/edit')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Teams/Edit')
                ->has('moduleStates')
            );
    }

    public function test_admin_can_change_and_schedule_optional_module_activation(): void
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
            'atlas_admin_mode_entered_at' => now()->toIso8601String(),
            'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
            'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
        ];

        $this->actingAs($admin)
            ->withSession($session)
            ->patch('/admin/modules/feature_flags/teams/'.$teamPublicId, [
                'enabled' => false,
                'reason' => 'Pilot disabled for this team.',
            ])
            ->assertRedirect();

        self::assertDatabaseHas(DatabaseTable::MODULE_TEAM_STATES, [
            'module_key' => 'feature_flags',
            'team_id' => $team->id,
            'enabled' => false,
        ]);

        $this->actingAs($admin)
            ->withSession($session)
            ->post('/admin/modules/feature_flags/global/schedules', [
                'enabled' => false,
                'effective_at' => now()->addDay()->toIso8601String(),
                'reason' => 'Disable after rollout window.',
            ])
            ->assertRedirect();

        $schedulePublicId = DB::table(DatabaseTable::MODULE_ACTIVATION_SCHEDULES)
            ->where('module_key', 'feature_flags')
            ->where('status', ModuleActivationScheduleStatus::Scheduled->value)
            ->value('public_id');

        self::assertIsString($schedulePublicId);

        $this->actingAs($admin)
            ->withSession($session)
            ->get('/admin/modules?scheduled=yes')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Modules/Index')
                ->where('table.state.filters.scheduled', 'yes')
                ->where('modules', fn ($modules): bool => $this->containsRow($modules,
                    fn (array $module): bool => ($module['moduleKey'] ?? null) === 'feature_flags'
                        && ($module['scheduledChangesCount'] ?? 0) > 0
                ))
            );

        $this->actingAs($admin)
            ->withSession($session)
            ->delete('/admin/modules/feature_flags/schedules/'.$schedulePublicId, [
                'reason' => 'Deployment window changed.',
            ])
            ->assertRedirect();

        self::assertDatabaseHas(DatabaseTable::MODULE_ACTIVATION_SCHEDULES, [
            'public_id' => $schedulePublicId,
            'status' => ModuleActivationScheduleStatus::Cancelled->value,
        ]);
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
                'atlas_admin_mode_entered_at' => now()->toIso8601String(),
                'atlas_admin_mode_last_activity_at' => now()->toIso8601String(),
                'atlas_admin_high_risk_confirmed_at' => now()->toIso8601String(),
            ])
            ->get('/admin/modules')
            ->assertForbidden();
    }

    private function stringListContains(mixed $values, string $value): bool
    {
        $values = self::arrayValue($values);

        foreach ($values as $key => $item) {
            if ($key === $value || $item === $value) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<mixed>
     */
    private static function arrayValue(mixed $value): array
    {
        if ($value instanceof Arrayable) {
            $value = $value->toArray();
        }

        if ($value instanceof \Traversable) {
            return iterator_to_array($value);
        }

        return is_array($value) ? $value : [];
    }

    /**
     * @param  callable(array<string, mixed>): bool  $predicate
     */
    private function containsRow(mixed $rows, callable $predicate): bool
    {
        foreach (self::listValue($rows) as $row) {
            if (is_array($row) && $predicate(self::stringKeyedArray($row))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  callable(array<string, mixed>): bool  $predicate
     */
    private function nonEmptyEveryRow(mixed $rows, callable $predicate): bool
    {
        $rows = self::listValue($rows);

        if ($rows === []) {
            return false;
        }

        foreach ($rows as $row) {
            if (! is_array($row) || ! $predicate(self::stringKeyedArray($row))) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<mixed>
     */
    private static function listValue(mixed $value): array
    {
        return array_values(self::arrayValue($value));
    }

    /**
     * @param  array<mixed>  $value
     * @return array<string, mixed>
     */
    private static function stringKeyedArray(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $result[$key] = $item;
            }
        }

        return $result;
    }
}
