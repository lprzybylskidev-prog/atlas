<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Fixtures;

use App\Modules\Core\Identity\Application\Public\Contracts\VerifiedUserFixtureBuilder;
use App\Modules\Core\Identity\Application\Public\DTOs\VerifiedUserFixture;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Optional\TimeTracking\Application\Contracts\TimeTrackingFixtureBuilder;
use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Infrastructure\Persistence\TableNames\TimeTrackingDatabaseTable;
use App\Shared\Application\Authorization\Contracts\UserTeamAuthorizationManager;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Application\Notifications\Permissions\NotificationPermissionNames;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipProvisioner;
use App\Shared\Application\Teams\Contracts\UserTeamSessionLimitSettings;
use App\Shared\Application\TimeTracking\Contracts\UserBreakPolicySettings;
use App\Shared\Application\Users\Permissions\UserPermissionNames;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

final class TimeTrackingDevelopmentFixtureBuilder implements TimeTrackingFixtureBuilder
{
    public const E2E_MAINTENANCE_WORK_SESSION_PUBLIC_ID = '01K00000000000000000000001';

    public const E2E_OUT_OF_SCOPE_WORK_SESSION_PUBLIC_ID = '01K00000000000000000000002';

    private const TEAMS = [
        'north' => 'TT Demo Team North',
        'south' => 'TT Demo Team South',
    ];

    private const HEAD_MANAGERS = [
        ['team' => 'north', 'email' => 'tt.head.manager.01.north@example.test', 'name' => 'TT Head Manager 01 - North'],
        ['team' => 'south', 'email' => 'tt.head.manager.02.south@example.test', 'name' => 'TT Head Manager 02 - South'],
    ];

    private const MANAGERS = [
        ['team' => 'north', 'email' => 'tt.manager.01.north@example.test', 'name' => 'TT Manager 01 - North'],
        ['team' => 'north', 'email' => 'tt.manager.02.north@example.test', 'name' => 'TT Manager 02 - North'],
        ['team' => 'south', 'email' => 'tt.manager.03.south@example.test', 'name' => 'TT Manager 03 - South'],
    ];

    private const ONE_MINUTE_POLICY_EMAIL = 'tt.one.minute.policy.north@example.test';

    private const DEMO_MAINTENANCE_REASON = 'Development demo maintenance break.';

    public function __construct(
        private readonly VerifiedUserFixtureBuilder $users,
        private readonly BootstrapTeamProvider $teams,
        private readonly TeamLookup $teamLookup,
        private readonly UserTeamMembershipProvisioner $memberships,
        private readonly ManagerHierarchy $hierarchy,
        private readonly UserTeamAuthorizationManager $authorization,
        private readonly UserTeamSessionLimitSettings $sessionLimits,
        private readonly UserBreakPolicySettings $breakPolicies,
        private readonly UserTeamTrackingSettings $trackingSettings,
        private readonly ModuleActivationService $activation,
    ) {}

    public function available(): bool
    {
        return Schema::hasTable(TimeTrackingDatabaseTable::WORK_SESSIONS);
    }

    public function seed(): void
    {
        DB::transaction(function (): void {
            $teams = [
                'north' => $this->team(self::TEAMS['north']),
                'south' => $this->team(self::TEAMS['south']),
            ];

            foreach ($teams as $team) {
                $this->activateTimeTracking($team);
                $this->seedCategories($team);
            }

            $headManagers = $this->users(self::HEAD_MANAGERS);
            $managers = $this->users(self::MANAGERS);
            $regularUsers = $this->regularUsers();
            $allUsers = [...array_values($headManagers), ...array_values($managers), ...$regularUsers];

            foreach ($allUsers as $user) {
                $teamKey = $this->teamKeyForEmail((string) $user->email);
                $team = $teams[$teamKey];
                $headManager = str_starts_with($user->email, 'tt.head.manager.');

                $this->assignToTeam($user, $team);
                $assignmentId = $this->enableTracking($user, $team);
                $this->configureSpecialPolicy($user, $team);
                $this->assignPermissions($user, $team, $headManager || str_starts_with($user->email, 'tt.manager.'));
            }

            $this->seedHierarchy($teams, $headManagers, $managers, $regularUsers);

            if (! DB::table(TimeTrackingDatabaseTable::MAINTENANCE_WINDOWS)->where('reason', self::DEMO_MAINTENANCE_REASON)->exists()) {
                foreach ($teams as $teamKey => $team) {
                    $teamUsers = array_values(array_filter(
                        $allUsers,
                        fn (VerifiedUserFixture $user): bool => $this->teamKeyForEmail($user->email) === $teamKey,
                    ));

                    $this->clearTimeTrackingRows($team, $teamUsers);
                }

                $this->seedTimeTrackingRows($teams, $allUsers);
                $this->seedMaintenanceRows();
            }
        });
    }

    private function team(string $name): TimeTrackingFixtureTeam
    {
        $team = $this->teams->provide($name);
        $teamId = $this->teamLookup->internalIdForPublicId($team->publicId);

        if ($teamId === null) {
            throw new \RuntimeException(sprintf('Fixture team [%s] could not be resolved.', $name));
        }

        return new TimeTrackingFixtureTeam($teamId, $team->publicId, $team->name);
    }

    /**
     * @param  list<array{team: string, email: string, name: string}>  $definitions
     * @return array<string, VerifiedUserFixture>
     */
    private function users(array $definitions): array
    {
        $users = [];

        foreach ($definitions as $definition) {
            $users[$definition['email']] = $this->user($definition['email'], $definition['name']);
        }

        return $users;
    }

    /**
     * @return list<VerifiedUserFixture>
     */
    private function regularUsers(): array
    {
        $users = [];

        for ($index = 1; $index <= 50; $index++) {
            $team = $index <= 25 ? 'North' : 'South';
            $emailTeam = strtolower($team);
            $users[] = $this->user(
                sprintf('tt.user.%03d.%s@example.test', $index, $emailTeam),
                sprintf('TT User %03d - %s', $index, $team),
            );
        }

        $users[] = $this->user(
            self::ONE_MINUTE_POLICY_EMAIL,
            'TT One Minute Policy Test User - North',
        );

        return $users;
    }

    private function user(string $email, string $name): VerifiedUserFixture
    {
        return $this->users->provide($name, $email, 'password');
    }

    private function teamKeyForEmail(string $email): string
    {
        return str_contains($email, '.south@') || str_contains($email, '.south@example.') ? 'south' : 'north';
    }

    private function assignToTeam(VerifiedUserFixture $user, TimeTrackingFixtureTeam $team): int
    {
        $this->memberships->ensureUserTeamMembership($user->publicId, $team->publicId);
        $assignmentId = $this->teamLookup->activeAssignmentInternalIdForUserTeam($user->internalId, $team->internalId);

        if ($assignmentId === null) {
            throw new \RuntimeException(sprintf('Fixture membership [%s/%s] could not be resolved.', $user->email, $team->name));
        }

        return $assignmentId;
    }

    private function activateTimeTracking(TimeTrackingFixtureTeam $team): void
    {
        $state = $this->activation->effectiveState('time_tracking', $team->internalId);

        if (! $state->globallyEnabled) {
            $this->activation->change(new ModuleActivationChange(
                moduleKey: 'time_tracking',
                scope: ModuleActivationScope::Global,
                enabled: true,
                reason: 'Development demo TimeTracking scenario.',
                source: ModuleActivationSource::System,
            ));
        }

        if (! $state->teamEnabled) {
            $this->activation->change(new ModuleActivationChange(
                moduleKey: 'time_tracking',
                scope: ModuleActivationScope::Team,
                enabled: true,
                reason: 'Development demo TimeTracking scenario.',
                teamId: $team->internalId,
                source: ModuleActivationSource::System,
            ));
        }
    }

    private function assignPermissions(VerifiedUserFixture $user, TimeTrackingFixtureTeam $team, bool $manager): void
    {
        $permissions = [
            'dashboard',
            NotificationPermissionNames::NOTIFICATIONS_INDEX,
            NotificationPermissionNames::NOTIFICATIONS_READ,
            NotificationPermissionNames::NOTIFICATIONS_READ_BULK,
            NotificationPermissionNames::REALTIME_EVENTS,
            UserPermissionNames::USERS_PROFILE,
            UserPermissionNames::USERS_PROFILE_AVATAR_IMAGE,
            UserPermissionNames::USERS_PROFILE_AVATAR_UPDATE,
            UserPermissionNames::USERS_PROFILE_PASSWORD_UPDATE,
            UserPermissionNames::USERS_PROFILE_NOTIFICATION_EMAILS_STORE,
            UserPermissionNames::USERS_PROFILE_NOTIFICATION_EMAILS_UPDATE,
            TimeTrackingPermissionCatalog::USER_REPORT,
            TimeTrackingPermissionCatalog::USER_CORRECTION_REQUEST_STORE,
            TimeTrackingPermissionCatalog::ACTIVITY_RECORD,
            TimeTrackingPermissionCatalog::BREAK_START,
            TimeTrackingPermissionCatalog::BREAK_SHOW,
            TimeTrackingPermissionCatalog::BREAK_END,
            TimeTrackingPermissionCatalog::OTHER_WORK_CREATE,
            TimeTrackingPermissionCatalog::OTHER_WORK_START,
            TimeTrackingPermissionCatalog::OTHER_WORK_SHOW,
            TimeTrackingPermissionCatalog::OTHER_WORK_END,
        ];

        if ($manager) {
            $permissions = [...$permissions,
                TimeTrackingPermissionCatalog::MANAGER_PANEL,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_SUMMARY,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_OTHER_WORK,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSIONS,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_BREAKS,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_CORRECTIONS,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_WORK_SESSION_SHOW,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_BREAK_SHOW,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_OTHER_WORK_SHOW,
                TimeTrackingPermissionCatalog::MANAGER_WORK_TIME_CORRECTION_SHOW,
                TimeTrackingPermissionCatalog::MANAGER_TERMINATE_SESSION,
                TimeTrackingPermissionCatalog::MANAGER_BREAK_FORCE_CLOSE,
                TimeTrackingPermissionCatalog::MANAGER_BREAK_CONVERT_EXCESS,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_FORCE_CLOSE,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_DECIDE,
                TimeTrackingPermissionCatalog::MANAGER_CORRECTION_DECIDE,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_INDEX,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_CREATE,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_STORE,
                TimeTrackingPermissionCatalog::MANAGER_OTHER_WORK_CATEGORY_DEACTIVATE,
            ];
        }

        $current = $this->authorization->assignmentsForUserTeam($user->publicId, $team->publicId);
        $currentPermissions = $current->directPermissionNames;
        sort($currentPermissions);
        sort($permissions);

        if ($current->roleNames === [] && $currentPermissions === $permissions) {
            return;
        }

        $this->authorization->replaceAssignmentsForUserTeam(
            actorPublicId: $user->publicId,
            userPublicId: $user->publicId,
            teamPublicId: $team->publicId,
            roleNames: [],
            directPermissionNames: $permissions,
            reason: 'Development TimeTracking fixture.',
            sourceType: 'manual',
            expectedVersion: $current->version,
        );
    }

    /**
     * @param  array<string, TimeTrackingFixtureTeam>  $teams
     * @param  array<string, VerifiedUserFixture>  $headManagers
     * @param  array<string, VerifiedUserFixture>  $managers
     * @param  list<VerifiedUserFixture>  $regularUsers
     */
    private function seedHierarchy(array $teams, array $headManagers, array $managers, array $regularUsers): void
    {
        $northHead = $headManagers['tt.head.manager.01.north@example.test'];
        $southHead = $headManagers['tt.head.manager.02.south@example.test'];
        $northManagers = [
            $managers['tt.manager.01.north@example.test'],
            $managers['tt.manager.02.north@example.test'],
        ];
        $southManagers = [$managers['tt.manager.03.south@example.test']];

        if (! $this->hierarchy->scopeFor($teams['north']->publicId, $northHead->publicId)->headManager) {
            $this->hierarchy->setHeadManager($northHead->publicId, $teams['north']->publicId, $northHead->publicId, true, 'Development TimeTracking fixture.');
        }
        if (! $this->hierarchy->scopeFor($teams['south']->publicId, $southHead->publicId)->headManager) {
            $this->hierarchy->setHeadManager($southHead->publicId, $teams['south']->publicId, $southHead->publicId, true, 'Development TimeTracking fixture.');
        }

        foreach ($northManagers as $manager) {
            $this->createManagerRelationship($northHead, $manager, $teams['north']);
        }

        foreach ($southManagers as $manager) {
            $this->createManagerRelationship($southHead, $manager, $teams['south']);
        }

        foreach ($regularUsers as $index => $user) {
            $teamKey = $this->teamKeyForEmail((string) $user->email);
            $pool = $teamKey === 'north' ? $northManagers : $southManagers;
            $manager = $pool[$index % count($pool)];

            $this->createManagerRelationship($manager, $user, $teams[$teamKey]);
        }
    }

    private function createManagerRelationship(VerifiedUserFixture $manager, VerifiedUserFixture $report, TimeTrackingFixtureTeam $team): void
    {
        foreach ($this->hierarchy->activeRelationships($team->publicId) as $relationship) {
            if ($relationship->managerUserPublicId === $manager->publicId && $relationship->reportUserPublicId === $report->publicId) {
                return;
            }
        }

        $this->hierarchy->assign(
            actorUserPublicId: $manager->publicId,
            teamPublicId: $team->publicId,
            managerUserPublicId: $manager->publicId,
            reportUserPublicId: $report->publicId,
            validFrom: '2026-08-01 00:00:00+00',
            reason: 'Development TimeTracking fixture.',
        );
    }

    private function enableTracking(VerifiedUserFixture $user, TimeTrackingFixtureTeam $team): int
    {
        $assignmentId = $this->assignToTeam($user, $team);
        $this->trackingSettings->setEnabledForAssignment($assignmentId, true);

        return $assignmentId;
    }

    private function configureSpecialPolicy(VerifiedUserFixture $user, TimeTrackingFixtureTeam $team): void
    {
        if ($user->email !== self::ONE_MINUTE_POLICY_EMAIL) {
            return;
        }

        $this->sessionLimits->setUserTeamOverrides($user->publicId, $team->publicId, 1, null);
        $this->breakPolicies->setUserTeamOverrides($user->publicId, $team->publicId, 1, 1);
    }

    /**
     * @param  list<VerifiedUserFixture>  $users
     */
    private function clearTimeTrackingRows(TimeTrackingFixtureTeam $team, array $users): void
    {
        $userIds = array_map(static fn (VerifiedUserFixture $user): int => $user->internalId, $users);
        $workSessionIds = DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)
            ->where('team_id', $team->internalId)
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->all();
        $correctionIds = DB::table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS)
            ->where('team_id', $team->internalId)
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->all();
        $breakIds = DB::table(TimeTrackingDatabaseTable::BREAKS)
            ->where('team_id', $team->internalId)
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->all();
        $otherWorkIds = DB::table(TimeTrackingDatabaseTable::OTHER_WORK)
            ->where('team_id', $team->internalId)
            ->whereIn('user_id', $userIds)
            ->pluck('id')
            ->all();

        DB::table(TimeTrackingDatabaseTable::CLOSED_PERIOD_OVERRIDES)->whereIn('correction_request_id', $correctionIds)->delete();
        DB::table(TimeTrackingDatabaseTable::CORRECTION_HISTORY)->whereIn('correction_request_id', $correctionIds)->delete();
        DB::table(TimeTrackingDatabaseTable::CORRECTION_PROPOSALS)->whereIn('correction_request_id', $correctionIds)->delete();
        DB::table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS)->whereIn('id', $correctionIds)->delete();
        DB::table(TimeTrackingDatabaseTable::OTHER_WORK)->whereIn('id', $otherWorkIds)->delete();
        DB::table(TimeTrackingDatabaseTable::BREAK_REMINDERS)->whereIn('break_id', $breakIds)->delete();
        DB::table(TimeTrackingDatabaseTable::BREAKS)->whereIn('id', $breakIds)->delete();
        DB::table(TimeTrackingDatabaseTable::MAINTENANCE_AFFECTED_SESSIONS)->whereIn('work_session_id', $workSessionIds)->delete();
        DB::table(TimeTrackingDatabaseTable::MODULE_CONTEXT_SEGMENTS)->whereIn('work_session_id', $workSessionIds)->delete();
        DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->whereIn('id', $workSessionIds)->delete();
    }

    private function seedCategories(TimeTrackingFixtureTeam $team): void
    {
        foreach ([
            ['field_visit', 'Wizyta terenowa', 'Field visit', false, true],
            ['court_call', 'Telefon do sądu', 'Court call', true, false],
            ['case_review', 'Przegląd spraw', 'Case review', false, true],
        ] as [$key, $labelPl, $labelEn, $requiresComment, $autoApproval]) {
            $identity = [
                'scope_type' => 'team',
                'scope_id' => $team->internalId,
                'category_key' => $key,
            ];
            $values = [
                'label_pl' => $labelPl,
                'label_en' => $labelEn,
                'requires_comment' => $requiresComment,
                'auto_approval_enabled' => $autoApproval,
                'is_active' => true,
                'updated_at' => now(),
            ];

            if (DB::table(TimeTrackingDatabaseTable::OTHER_WORK_CATEGORIES)->where($identity)->exists()) {
                DB::table(TimeTrackingDatabaseTable::OTHER_WORK_CATEGORIES)->where($identity)->update($values);
            } else {
                DB::table(TimeTrackingDatabaseTable::OTHER_WORK_CATEGORIES)->insert([
                    ...$identity,
                    ...$values,
                    'public_id' => (string) Str::ulid(),
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * @param  array<string, TimeTrackingFixtureTeam>  $teams
     * @param  list<VerifiedUserFixture>  $users
     */
    private function seedTimeTrackingRows(array $teams, array $users): void
    {
        foreach ($users as $index => $user) {
            $team = $teams[$this->teamKeyForEmail((string) $user->email)];
            $dayOffset = $index % 5;
            $startHour = 6 + ($index % 4);
            $activeMode = $index < 8 ? $index % 4 : null;

            for ($day = 0; $day < 4; $day++) {
                $date = sprintf('2026-07-%02d', 24 + $dayOffset + $day);
                $sessionId = $this->workSession(
                    $team,
                    $user,
                    sprintf('%s %02d:00:00+00', $date, $startHour),
                    sprintf('%s %02d:30:00+00', $date, $startHour + 7),
                    27000,
                    $day % 3 === 0 ? 'logout' : 'inactivity',
                    $this->deterministicWorkSessionPublicId($user, $day),
                );

                $this->moduleSegment($sessionId, sprintf('%s %02d:00:00+00', $date, $startHour), sprintf('%s %02d:00:00+00', $date, $startHour + 4), 14400);

                if (($index + $day) % 3 === 0) {
                    // Default demo breaks are 18 minutes, intentionally above the global 15-minute daily limit.
                    $this->breakSession($team, $user, $sessionId, sprintf('%s %02d:10:00+00', $date, $startHour + 2), sprintf('%s %02d:28:00+00', $date, $startHour + 2), 1080, 'normal', false);
                }

                if (($index + $day) % 4 === 0) {
                    $this->otherWork($team, $user, $sessionId, $index % 2 === 0 ? 'field_visit' : 'court_call', 'Development demo operational work.', 'Closed from demo seed.', $index % 2 === 0 ? 'approved' : 'under_review', sprintf('%s %02d:00:00+00', $date, $startHour + 5), sprintf('%s %02d:40:00+00', $date, $startHour + 5), 2400, $index % 2 !== 0);
                }

                if ($day === 2 && $index % 9 === 0) {
                    $this->correction($team, $user, $sessionId, $date, $startHour);
                }
            }

            if ($activeMode !== null) {
                $this->activeWorkState($team, $user, $activeMode);
            }
        }

        $this->seedSourceCorrectionExamples($teams);
    }

    /**
     * @param  array<string, TimeTrackingFixtureTeam>  $teams
     */
    private function seedSourceCorrectionExamples(array $teams): void
    {
        foreach ($teams as $team) {
            $workSession = DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)
                ->where('team_id', $team->internalId)
                ->whereNotNull('ended_at')
                ->orderBy('started_at')
                ->first(['id', 'user_id', 'started_at', 'ended_at', 'exact_seconds']);

            if ($workSession !== null) {
                $this->sourceCorrection($team, $workSession, 'work_session', 'Development demo correction for a work session.');
            }

            $break = DB::table(TimeTrackingDatabaseTable::BREAKS)
                ->where('team_id', $team->internalId)
                ->whereNotNull('ended_at')
                ->orderBy('started_at')
                ->first(['id', 'work_session_id', 'user_id', 'started_at', 'ended_at', 'exact_seconds']);

            if ($break !== null) {
                $this->sourceCorrection($team, $break, 'break', 'Development demo correction for a break.');
            }

            $otherWork = DB::table(TimeTrackingDatabaseTable::OTHER_WORK)
                ->where('team_id', $team->internalId)
                ->whereNotNull('ended_at')
                ->orderBy('started_at')
                ->first(['id', 'work_session_id', 'user_id', 'started_at', 'ended_at', 'exact_seconds']);

            if ($otherWork !== null) {
                $this->sourceCorrection($team, $otherWork, 'other_work', 'Development demo correction for work outside the computer.');
            }
        }
    }

    private function seedMaintenanceRows(): void
    {
        $windowId = (int) DB::table(TimeTrackingDatabaseTable::MAINTENANCE_WINDOWS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'kind' => 'scheduled',
            'status' => 'completed',
            'scheduled_start_at' => '2026-07-28 11:30:00+00',
            'started_at' => '2026-07-28 11:30:00+00',
            'completed_at' => '2026-07-28 11:48:00+00',
            'return_grace_seconds' => 600,
            'reason' => self::DEMO_MAINTENANCE_REASON,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach (DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)
            ->where('started_at', '<=', '2026-07-28 11:30:00+00')
            ->where('ended_at', '>=', '2026-07-28 11:30:00+00')
            ->get(['id', 'user_id', 'team_id']) as $session) {
            DB::table(TimeTrackingDatabaseTable::MAINTENANCE_AFFECTED_SESSIONS)->insert([
                'public_id' => (string) Str::ulid(),
                'maintenance_window_id' => $windowId,
                'work_session_id' => $session->id,
                'user_id' => $session->user_id,
                'team_id' => $session->team_id,
                'interrupted_at' => '2026-07-28 11:30:00+00',
                'return_deadline_at' => '2026-07-28 11:58:00+00',
                'returned_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function deterministicWorkSessionPublicId(VerifiedUserFixture $user, int $day): ?string
    {
        if ($user->email === 'tt.user.002.north@example.test' && $day === 3) {
            return self::E2E_MAINTENANCE_WORK_SESSION_PUBLIC_ID;
        }

        if ($user->email === 'tt.user.026.south@example.test' && $day === 0) {
            return self::E2E_OUT_OF_SCOPE_WORK_SESSION_PUBLIC_ID;
        }

        return null;
    }

    private function workSession(
        TimeTrackingFixtureTeam $team,
        VerifiedUserFixture $user,
        string $startedAt,
        ?string $endedAt,
        ?int $seconds,
        ?string $reason,
        ?string $publicId = null,
    ): int {
        return (int) DB::table(TimeTrackingDatabaseTable::WORK_SESSIONS)->insertGetId([
            'public_id' => $publicId ?? (string) Str::ulid(),
            'user_id' => $user->internalId,
            'team_id' => $team->internalId,
            'laravel_session_id' => 'demo-'.Str::lower((string) Str::ulid()),
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'exact_seconds' => $seconds,
            'closure_reason' => $reason,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function moduleSegment(int $workSessionId, string $startedAt, ?string $endedAt, ?int $seconds): void
    {
        DB::table(TimeTrackingDatabaseTable::MODULE_CONTEXT_SEGMENTS)->insert([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'module_key' => 'system',
            'context_key' => 'System',
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'exact_seconds' => $seconds,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function activeWorkState(TimeTrackingFixtureTeam $team, VerifiedUserFixture $user, int $mode): void
    {
        $workSessionId = $this->workSession($team, $user, '2026-08-01 08:00:00+00', null, null, null);
        $this->moduleSegment($workSessionId, '2026-08-01 08:00:00+00', null, null);

        if ($mode === 1) {
            $this->breakSession($team, $user, $workSessionId, '2026-08-01 10:15:00+00', null, null, null, true);
        }

        if ($mode === 2) {
            $this->otherWork($team, $user, $workSessionId, 'case_review', 'Active demo case review outside standard screen work.', null, 'under_review', '2026-08-01 09:45:00+00', null, null, true);
        }
    }

    private function breakSession(TimeTrackingFixtureTeam $team, VerifiedUserFixture $user, int $workSessionId, string $startedAt, ?string $endedAt, ?int $seconds, ?string $reason, bool $review): int
    {
        return (int) DB::table(TimeTrackingDatabaseTable::BREAKS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $user->internalId,
            'team_id' => $team->internalId,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'exact_seconds' => $seconds,
            'closure_reason' => $reason,
            'requires_manager_review' => $review,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function otherWork(TimeTrackingFixtureTeam $team, VerifiedUserFixture $user, int $workSessionId, string $category, string $description, ?string $endNote, string $status, string $startedAt, ?string $endedAt, ?int $seconds, bool $review): int
    {
        return (int) DB::table(TimeTrackingDatabaseTable::OTHER_WORK)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'work_session_id' => $workSessionId,
            'user_id' => $user->internalId,
            'team_id' => $team->internalId,
            'category_key' => $category,
            'description' => $description,
            'end_note' => $endNote,
            'approval_status' => $status,
            'started_at' => $startedAt,
            'ended_at' => $endedAt,
            'exact_seconds' => $seconds,
            'closure_reason' => $endedAt === null ? null : 'completed',
            'requires_manager_review' => $review,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function correction(TimeTrackingFixtureTeam $team, VerifiedUserFixture $user, int $workSessionId, string $date, int $startHour): void
    {
        $correctionId = (int) DB::table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->internalId,
            'team_id' => $team->internalId,
            'work_session_id' => $workSessionId,
            'source_type' => 'work_session',
            'source_id' => $workSessionId,
            'status' => 'pending',
            'request_type' => 'exact_change',
            'description' => 'Development demo correction request.',
            'requested_at' => sprintf('%s %02d:00:00+00', $date, $startHour + 8),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->exactCorrectionProposal(
            correctionId: $correctionId,
            originalStartedAt: sprintf('%s %02d:00:00+00', $date, $startHour),
            originalEndedAt: sprintf('%s %02d:30:00+00', $date, $startHour + 7),
            originalSeconds: 27000,
            proposedStartedAt: sprintf('%s %02d:45:00+00', $date, $startHour),
            proposedEndedAt: sprintf('%s %02d:00:00+00', $date, $startHour + 8),
            proposedSeconds: 26100,
        );
    }

    private function sourceCorrection(TimeTrackingFixtureTeam $team, object $source, string $sourceType, string $description): void
    {
        $startedAt = $this->stringValue(data_get($source, 'started_at'));
        $endedAt = $this->stringValue(data_get($source, 'ended_at'));
        $seconds = $this->intValue(data_get($source, 'exact_seconds'));

        if ($startedAt === '' || $endedAt === '' || $seconds < 1) {
            return;
        }

        $sourceId = $this->intValue(data_get($source, 'id'));
        $workSessionId = $sourceType === 'work_session' ? $sourceId : $this->intValue(data_get($source, 'work_session_id'));
        $requestedAt = (new \DateTimeImmutable($endedAt))->modify('+30 minutes');
        $proposedStartedAt = (new \DateTimeImmutable($startedAt))->modify('+5 minutes');
        $proposedEndedAt = (new \DateTimeImmutable($endedAt))->modify('+5 minutes');

        $correctionId = (int) DB::table(TimeTrackingDatabaseTable::CORRECTION_REQUESTS)->insertGetId([
            'public_id' => (string) Str::ulid(),
            'user_id' => $this->intValue(data_get($source, 'user_id')),
            'team_id' => $team->internalId,
            'work_session_id' => $sourceType === 'work_session' ? $workSessionId : null,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => 'pending',
            'request_type' => 'exact_change',
            'description' => $description,
            'requested_at' => $requestedAt->format('Y-m-d H:i:sP'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->exactCorrectionProposal(
            correctionId: $correctionId,
            originalStartedAt: $startedAt,
            originalEndedAt: $endedAt,
            originalSeconds: $seconds,
            proposedStartedAt: $proposedStartedAt->format('Y-m-d H:i:sP'),
            proposedEndedAt: $proposedEndedAt->format('Y-m-d H:i:sP'),
            proposedSeconds: $seconds,
        );
    }

    private function exactCorrectionProposal(
        int $correctionId,
        string $originalStartedAt,
        string $originalEndedAt,
        int $originalSeconds,
        string $proposedStartedAt,
        string $proposedEndedAt,
        int $proposedSeconds,
    ): void {
        DB::table(TimeTrackingDatabaseTable::CORRECTION_PROPOSALS)->insert([
            'public_id' => (string) Str::ulid(),
            'correction_request_id' => $correctionId,
            'original_started_at' => $originalStartedAt,
            'original_ended_at' => $originalEndedAt,
            'original_exact_seconds' => $originalSeconds,
            'proposed_started_at' => $proposedStartedAt,
            'proposed_ended_at' => $proposedEndedAt,
            'proposed_exact_seconds' => $proposedSeconds,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
