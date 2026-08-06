<?php

declare(strict_types=1);

namespace App\Modules\Core\Authorization\Application\Lifecycle;

use App\Modules\Core\Authorization\Application\Public\Persistence\AuthorizationDatabaseTable;
use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Shared\Application\DataLifecycle\Contracts\DataLifecycleParticipant;
use App\Shared\Application\DataLifecycle\DataLifecycleImpact;
use App\Shared\Application\DataLifecycle\DataLifecycleOperation;
use App\Shared\Application\DataLifecycle\DataLifecyclePreview;
use App\Shared\Application\DataLifecycle\DataLifecycleResult;
use App\Shared\Application\DataLifecycle\DataLifecycleStepResult;
use App\Shared\Application\DataLifecycle\DataLifecycleSubject;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;

final readonly class UserAuthorizationDataLifecycleParticipant implements DataLifecycleParticipant
{
    public function __construct(
        private ConnectionInterface $db,
    ) {}

    public function preview(DataLifecycleSubject $subject, DataLifecycleOperation $operation): DataLifecyclePreview
    {
        $userId = $this->userId($subject);

        if ($userId === null) {
            return new DataLifecyclePreview([]);
        }

        return new DataLifecyclePreview([
            new DataLifecycleImpact(
                'authorization.user_roles',
                $this->roleAssignments($userId)->count(),
                true,
                $this->records($this->roleAssignments($userId), ['role_id', 'model_type', 'model_id', 'team_id']),
            ),
            new DataLifecycleImpact(
                'authorization.user_direct_permissions',
                $this->directPermissions($userId)->count(),
                true,
                $this->records($this->directPermissions($userId), ['permission_id', 'model_type', 'model_id', 'team_id']),
            ),
            new DataLifecycleImpact(
                'authorization.user_onboarding_packages',
                $this->onboardingAssignments($userId)->count(),
                true,
                $this->records($this->onboardingAssignments($userId), ['id', 'user_id', 'team_id', 'package_name', 'created_at']),
            ),
        ]);
    }

    public function execute(DataLifecycleSubject $subject, DataLifecycleOperation $operation, string $correlationId): DataLifecycleResult
    {
        $userId = $this->userId($subject);

        if ($userId === null) {
            return new DataLifecycleResult([]);
        }

        return new DataLifecycleResult([
            new DataLifecycleStepResult('authorization.user_roles_removed', $this->roleAssignments($userId)->delete(), true),
            new DataLifecycleStepResult('authorization.user_direct_permissions_removed', $this->directPermissions($userId)->delete(), true),
            new DataLifecycleStepResult('authorization.user_onboarding_packages_removed', $this->onboardingAssignments($userId)->delete(), true),
        ]);
    }

    private function userId(DataLifecycleSubject $subject): ?int
    {
        if ($subject->type !== 'user') {
            return null;
        }

        $id = $this->db->table(IdentityDatabaseTable::USERS)
            ->where('public_id', $subject->identifier)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function roleAssignments(int $userId): Builder
    {
        return $this->db->table(AuthorizationDatabaseTable::MODEL_HAS_ROLES)
            ->where('model_type', config()->string('auth.providers.users.model'))
            ->where('model_id', $userId);
    }

    private function directPermissions(int $userId): Builder
    {
        return $this->db->table(AuthorizationDatabaseTable::MODEL_HAS_PERMISSIONS)
            ->where('model_type', config()->string('auth.providers.users.model'))
            ->where('model_id', $userId);
    }

    private function onboardingAssignments(int $userId): Builder
    {
        return $this->db->table(AuthorizationDatabaseTable::USER_ONBOARDING_PACKAGES)->where('user_id', $userId);
    }

    /**
     * @param  list<string>  $columns
     * @return list<array<string, mixed>>
     */
    private function records(Builder $query, array $columns): array
    {
        $records = [];

        foreach ($query->get($columns) as $record) {
            $records[] = $this->recordToArray($record);
        }

        return $records;
    }

    /**
     * @return array<string, mixed>
     */
    private function recordToArray(object $record): array
    {
        $row = [];

        foreach ((array) $record as $key => $value) {
            if (is_string($key)) {
                $row[$key] = $value;
            }
        }

        return $row;
    }
}
