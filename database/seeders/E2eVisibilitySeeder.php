<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditEventLookup;
use App\Modules\Core\Authorization\Application\Public\Contracts\AdministratorAccessManager;
use App\Modules\Core\Authorization\Application\Public\Contracts\AuthorizationFixtureBuilder;
use App\Modules\Core\Identity\Application\Public\Contracts\VerifiedUserFixtureBuilder;
use App\Modules\Core\Identity\Application\Public\DTOs\VerifiedUserFixture;
use App\Modules\Core\Teams\Application\Public\Contracts\BootstrapTeamProvider;
use App\Modules\Core\Teams\Application\Public\Contracts\ManagerHierarchy;
use App\Modules\Optional\Imports\Application\Contracts\ImportFixtureBuilder;
use App\Modules\Optional\TimeTracking\Application\Contracts\UserTeamTrackingSettings;
use App\Shared\Application\Audit\Contracts\AuditRecorder;
use App\Shared\Application\Audit\DTOs\AuditEvent;
use App\Shared\Application\Audit\Enums\SecurityAuditCategory;
use App\Shared\Application\Modules\Activation\Contracts\ModuleActivationService;
use App\Shared\Application\Modules\Activation\ModuleActivationChange;
use App\Shared\Application\Modules\Activation\ModuleActivationScope;
use App\Shared\Application\Modules\Activation\ModuleActivationSource;
use App\Shared\Application\Teams\Contracts\TeamLookup;
use App\Shared\Application\Teams\Contracts\UserTeamMembershipProvisioner;
use Illuminate\Database\Seeder;

final class E2eVisibilitySeeder extends Seeder
{
    public const ADMIN_EMAIL = 'admin@example.test';

    public const LIMITED_EMAIL = 'limited@example.test';

    public const STRUCTURE_CANDIDATE_EMAIL = 'structure.candidate@example.test';

    public const PASSWORD = 'password';

    public const TEAM_NAME = 'E2E Visibility Team';

    public function run(): void
    {
        if (app()->isProduction()) {
            return;
        }

        $this->call(DatabaseSeeder::class);

        $team = app(BootstrapTeamProvider::class)->provide(self::TEAM_NAME);
        $admin = $this->user(self::ADMIN_EMAIL, 'Visibility Admin', 'sensitive');
        $limited = $this->user(self::LIMITED_EMAIL, 'Visibility User');
        $this->user(self::STRUCTURE_CANDIDATE_EMAIL, 'Structure Candidate');
        $memberships = app(UserTeamMembershipProvisioner::class);
        $memberships->ensureUserTeamMembership($admin->publicId, $team->publicId);
        $memberships->ensureUserTeamMembership($limited->publicId, $team->publicId);

        app(AdministratorAccessManager::class)->assignAdministrator(
            userPublicId: $admin->publicId,
            teamPublicId: $team->publicId,
        );
        $this->activateModules($team->publicId);
        app(ImportFixtureBuilder::class)->provideVisibilityImport($admin->publicId, $team->publicId);

        app(AuthorizationFixtureBuilder::class)->assignWorkspaceAccess($admin->publicId, $limited->publicId, $team->publicId);
        $this->seedManagerScope($admin, $limited, $team->publicId);
        $this->enableTimeTracking($admin, $team->publicId);
        $this->enableTimeTracking($limited, $team->publicId);

        $this->auditEvents($admin->publicId, $team->publicId);
    }

    private function activateModules(string $teamPublicId): void
    {
        $teamId = app(TeamLookup::class)->internalIdForPublicId($teamPublicId);

        if ($teamId === null) {
            return;
        }

        $activation = app(ModuleActivationService::class);

        foreach (['feature_flags', 'integrations', 'managed_processes', 'imports', 'search', 'time_tracking'] as $moduleKey) {
            $state = $activation->effectiveState($moduleKey, $teamId);
            if (! $state->globallyEnabled) {
                $activation->change(new ModuleActivationChange(
                    moduleKey: $moduleKey,
                    scope: ModuleActivationScope::Global,
                    enabled: true,
                    reason: 'E2E visibility setup.',
                    source: ModuleActivationSource::Manual,
                ));
            }
            if (! $state->teamEnabled) {
                $activation->change(new ModuleActivationChange(
                    moduleKey: $moduleKey,
                    scope: ModuleActivationScope::Team,
                    enabled: true,
                    reason: 'E2E visibility setup.',
                    teamId: $teamId,
                    source: ModuleActivationSource::Manual,
                ));
            }
        }
    }

    private function seedManagerScope(VerifiedUserFixture $manager, VerifiedUserFixture $report, string $teamPublicId): void
    {
        $hierarchy = app(ManagerHierarchy::class);

        foreach ($hierarchy->activeRelationships($teamPublicId) as $relationship) {
            if ($relationship->managerUserPublicId === $manager->publicId && $relationship->reportUserPublicId === $report->publicId) {
                return;
            }
        }

        $hierarchy->assign(
            actorUserPublicId: $manager->publicId,
            teamPublicId: $teamPublicId,
            managerUserPublicId: $manager->publicId,
            reportUserPublicId: $report->publicId,
            validFrom: '2026-08-01 00:00:00+00',
            reason: 'E2E visibility fixture.',
        );
    }

    private function enableTimeTracking(VerifiedUserFixture $user, string $teamPublicId): void
    {
        $teamId = app(TeamLookup::class)->internalIdForPublicId($teamPublicId);
        $assignmentId = $teamId === null
            ? null
            : app(TeamLookup::class)->activeAssignmentInternalIdForUserTeam($user->internalId, $teamId);

        if ($assignmentId !== null) {
            app(UserTeamTrackingSettings::class)->setEnabledForAssignment($assignmentId, true);
        }
    }

    private function user(string $email, string $name, string $accountSensitivity = 'normal'): VerifiedUserFixture
    {
        return app(VerifiedUserFixtureBuilder::class)->provide($name, $email, self::PASSWORD, $accountSensitivity);
    }

    private function auditEvents(string $adminPublicId, string $teamPublicId): void
    {
        $lookup = app(AuditEventLookup::class);
        $recorder = app(AuditRecorder::class);

        if (! $this->hasAuditAction($lookup, 'identity', $adminPublicId, 'e2e.audit.alpha')) {
            $recorder->record(new AuditEvent(
                module: 'identity',
                action: 'e2e.audit.alpha',
                result: 'succeeded',
                source: 'e2e',
                actorPublicId: $adminPublicId,
                targetType: 'user',
                targetPublicId: $adminPublicId,
                teamPublicId: $teamPublicId,
                correlationId: 'e2e-alpha',
                security: true,
                securityCategory: SecurityAuditCategory::Authentication,
            ));
        }
        if (! $this->hasAuditAction($lookup, 'shared', $adminPublicId, 'e2e.audit.beta')) {
            $recorder->record(new AuditEvent(
                module: 'shared',
                action: 'e2e.audit.beta',
                result: 'failed',
                source: 'admin-ui',
                actorPublicId: $adminPublicId,
                targetType: 'table_view',
                targetPublicId: $adminPublicId,
                teamPublicId: $teamPublicId,
                correlationId: 'e2e-beta',
                security: false,
            ));
        }
    }

    private function hasAuditAction(AuditEventLookup $lookup, string $module, string $targetPublicId, string $action): bool
    {
        foreach ($lookup->recentForModuleAggregateOrTarget($module, $targetPublicId) as $event) {
            if ($event->action === $action) {
                return true;
            }
        }

        return false;
    }
}
