<?php

declare(strict_types=1);

namespace Tests\Feature\Privacy;

use App\Modules\Core\Authorization\Application\Lifecycle\UserAuthorizationDataLifecycleParticipant;
use App\Modules\Core\Authorization\Application\Roles\InstallStarterRoles;
use App\Modules\Core\Authorization\Application\Roles\StarterRoleName;
use App\Modules\Core\Authorization\Infrastructure\Persistence\TableNames\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Lifecycle\UserAccountDataLifecycleParticipant;
use App\Modules\Core\Identity\Infrastructure\Persistence\TableNames\IdentityDatabaseTable;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Modules\Core\Teams\Application\Lifecycle\TeamUserDataLifecycleParticipant;
use App\Modules\Core\Teams\Infrastructure\Persistence\TableNames\TeamsDatabaseTable;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

final class CoreRelatedDataLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_related_core_tables_preview_and_execute_privacy_lifecycle(): void
    {
        $this->app->make(InstallStarterRoles::class)->handle();

        $user = User::factory()->create([
            'name' => 'Private Person',
            'email' => 'private.person@example.test',
            'two_factor_secret' => 'encrypted-secret',
            'two_factor_recovery_codes' => 'encrypted-codes',
            'two_factor_confirmed_at' => now(),
            'remember_token' => 'remember-me',
        ]);
        $manager = User::factory()->create();
        $team = Team::query()->create([
            'public_id' => '01J0000000000000000000REL1',
            'name' => 'Privacy Related',
            'slug' => 'privacy-related',
            'is_active' => true,
        ]);
        $role = Role::query()->where('name', StarterRoleName::Administrator->value)->firstOrFail();
        $permissionId = DB::table(AuthorizationDatabaseTable::PERMISSIONS)->value('id');

        DB::table(IdentityDatabaseTable::USER_PASSWORD_HISTORIES)->insert([
            'user_id' => $user->id,
            'password_hash' => 'hash',
            'created_at' => now(),
        ]);
        DB::table(IdentityDatabaseTable::PASSWORD_RESET_TOKENS)->insert([
            'email' => $user->email,
            'token' => 'reset-token',
            'created_at' => now(),
        ]);
        DB::table(IdentityDatabaseTable::SESSIONS)->insert([
            'id' => 'privacy-related-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Atlas test',
            'payload' => 'payload',
            'last_activity' => now()->unix(),
        ]);
        DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->insert([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'structural_role' => 'employee',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)->insert([
            'public_id' => '01J0000000000000000000MGR1',
            'team_id' => $team->id,
            'manager_user_id' => $manager->id,
            'report_user_id' => $user->id,
            'valid_from' => now()->subDay(),
            'reason' => 'Operational hierarchy',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)->insert([
            'role_id' => $role->id,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
        DB::table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)->insert([
            'permission_id' => $permissionId,
            'model_type' => config('auth.providers.users.model'),
            'model_id' => $user->id,
            'team_id' => $team->id,
        ]);
        DB::table(AuthorizationDatabaseTable::USER_ONBOARDING_PACKAGES)->insert([
            'user_id' => $user->id,
            'team_id' => $team->id,
            'package_name' => 'starter',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $subject = new DataLifecycleSubject('user', (string) $user->public_id);
        $participants = [
            $this->app->make(UserAccountDataLifecycleParticipant::class),
            $this->app->make(TeamUserDataLifecycleParticipant::class),
            $this->app->make(UserAuthorizationDataLifecycleParticipant::class),
        ];

        $previewImpacts = collect($participants)
            ->flatMap(static fn ($participant) => $participant->preview($subject, DataLifecycleOperation::Anonymize)->impacts);
        $impacts = $previewImpacts->pluck('dataSet')->all();

        self::assertContains('identity.users', $impacts);
        self::assertContains('teams.user_assignments', $impacts);
        self::assertContains('teams.manager_relationships', $impacts);
        self::assertContains('authorization.user_roles', $impacts);
        self::assertContains('authorization.user_direct_permissions', $impacts);
        self::assertContains('authorization.user_onboarding_packages', $impacts);
        $roleImpact = $previewImpacts->firstWhere('dataSet', 'authorization.user_roles');

        self::assertNotNull($roleImpact);
        self::assertSame(
            [
                'role_id' => $role->id,
                'model_type' => config('auth.providers.users.model'),
                'model_id' => $user->id,
                'team_id' => $team->id,
            ],
            $roleImpact->details[0],
        );

        foreach ($participants as $participant) {
            $participant->execute($subject, DataLifecycleOperation::Anonymize, 'privacy-related-test');
        }

        $user->refresh();

        self::assertSame('Redacted user', $user->name);
        self::assertSame('redacted-'.strtolower((string) $user->public_id).'@redacted.atlas.invalid', $user->email);
        self::assertFalse((bool) $user->is_active);
        self::assertNull($user->two_factor_secret);
        self::assertNull(DB::table(IdentityDatabaseTable::USERS)->where('id', $user->id)->value('remember_token'));
        self::assertDatabaseMissing(IdentityDatabaseTable::USER_PASSWORD_HISTORIES, ['user_id' => $user->id]);
        self::assertDatabaseMissing(IdentityDatabaseTable::PASSWORD_RESET_TOKENS, ['email' => 'private.person@example.test']);
        self::assertDatabaseMissing(IdentityDatabaseTable::SESSIONS, ['user_id' => $user->id]);
        self::assertDatabaseMissing(AuthorizationDatabaseTable::MODEL_HAS_ROLES, ['model_id' => $user->id]);
        self::assertDatabaseMissing(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS, ['model_id' => $user->id]);
        self::assertDatabaseMissing(AuthorizationDatabaseTable::USER_ONBOARDING_PACKAGES, ['user_id' => $user->id]);
        self::assertDatabaseHas(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS, [
            'user_id' => $user->id,
            'structural_role' => 'employee',
        ]);

        self::assertNotNull(DB::table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)->where('user_id', $user->id)->value('valid_to'));
        self::assertNotNull(DB::table(TeamsDatabaseTable::TEAM_MANAGER_RELATIONSHIPS)->where('report_user_id', $user->id)->value('valid_to'));
    }
}
