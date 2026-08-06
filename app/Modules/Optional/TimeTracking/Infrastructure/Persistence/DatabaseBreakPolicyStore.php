<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\DTOs\BreakPolicy;
use App\Modules\Optional\TimeTracking\Application\Enums\BreakPolicyScope;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Str;

final readonly class DatabaseBreakPolicyStore implements BreakPolicyStore
{
    private const DEFAULT_DAILY_LIMIT_SECONDS = 900;

    private const DEFAULT_MAXIMUM_SINGLE_BREAK_SECONDS = 14400;

    private const DEFAULT_WARNING_BEFORE_MAXIMUM_SECONDS = 900;

    public function __construct(private ConnectionInterface $database) {}

    public function policyForUserTeam(int $userId, int $teamId): BreakPolicy
    {
        $assignmentId = $this->activeAssignmentId($userId, $teamId);

        if ($assignmentId !== null) {
            $policy = $this->policy(BreakPolicyScope::UserTeam, $assignmentId);

            if ($policy !== null) {
                return $policy;
            }
        }

        $teamPolicy = $this->policy(BreakPolicyScope::Team, $teamId);

        if ($teamPolicy !== null) {
            return $teamPolicy;
        }

        return $this->policy(BreakPolicyScope::Global, 0)
            ?? new BreakPolicy(
                self::DEFAULT_DAILY_LIMIT_SECONDS,
                self::DEFAULT_MAXIMUM_SINGLE_BREAK_SECONDS,
                self::DEFAULT_WARNING_BEFORE_MAXIMUM_SECONDS,
                'default',
            );
    }

    public function setGlobalPolicy(int $dailyLimitSeconds, int $maximumSingleBreakSeconds, ?int $warningBeforeMaximumSeconds = null): void
    {
        $this->upsert(BreakPolicyScope::Global, 0, $dailyLimitSeconds, $maximumSingleBreakSeconds, $warningBeforeMaximumSeconds);
    }

    public function setTeamPolicy(int $teamId, int $dailyLimitSeconds, int $maximumSingleBreakSeconds, ?int $warningBeforeMaximumSeconds = null): void
    {
        $this->upsert(BreakPolicyScope::Team, $teamId, $dailyLimitSeconds, $maximumSingleBreakSeconds, $warningBeforeMaximumSeconds);
    }

    public function setUserTeamPolicy(int $teamUserAssignmentId, int $dailyLimitSeconds, int $maximumSingleBreakSeconds, ?int $warningBeforeMaximumSeconds = null): void
    {
        $this->upsert(BreakPolicyScope::UserTeam, $teamUserAssignmentId, $dailyLimitSeconds, $maximumSingleBreakSeconds, $warningBeforeMaximumSeconds);
    }

    public function clearUserTeamPolicy(int $teamUserAssignmentId): void
    {
        $this->database->table(TimeTrackingDatabaseTable::BREAK_POLICIES)
            ->where('scope_type', BreakPolicyScope::UserTeam->value)
            ->where('scope_id', $teamUserAssignmentId)
            ->delete();
    }

    private function activeAssignmentId(int $userId, int $teamId): ?int
    {
        $id = $this->database->table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereNull('valid_to')
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function policy(BreakPolicyScope $scope, int $scopeId): ?BreakPolicy
    {
        $row = $this->database->table(TimeTrackingDatabaseTable::BREAK_POLICIES)
            ->where('scope_type', $scope->value)
            ->where('scope_id', $scopeId)
            ->first(['daily_limit_seconds', 'maximum_single_break_seconds', 'warning_before_maximum_seconds']);

        if (! is_object($row)) {
            return null;
        }

        return new BreakPolicy(
            dailyLimitSeconds: $this->intValue($row->daily_limit_seconds ?? null),
            maximumSingleBreakSeconds: $this->intValue($row->maximum_single_break_seconds ?? null),
            warningBeforeMaximumSeconds: $this->intValue($row->warning_before_maximum_seconds ?? self::DEFAULT_WARNING_BEFORE_MAXIMUM_SECONDS),
            source: $scope->value,
        );
    }

    private function upsert(
        BreakPolicyScope $scope,
        int $scopeId,
        int $dailyLimitSeconds,
        int $maximumSingleBreakSeconds,
        ?int $warningBeforeMaximumSeconds,
    ): void {
        $policy = new BreakPolicy(
            $dailyLimitSeconds,
            $maximumSingleBreakSeconds,
            $warningBeforeMaximumSeconds ?? min(self::DEFAULT_WARNING_BEFORE_MAXIMUM_SECONDS, $maximumSingleBreakSeconds - 1),
            $scope->value,
        );
        $now = now();

        $this->database->table(TimeTrackingDatabaseTable::BREAK_POLICIES)->upsert([
            [
                'public_id' => (string) Str::ulid(),
                'scope_type' => $scope->value,
                'scope_id' => $scopeId,
                'daily_limit_seconds' => $policy->dailyLimitSeconds,
                'maximum_single_break_seconds' => $policy->maximumSingleBreakSeconds,
                'warning_before_maximum_seconds' => $policy->warningBeforeMaximumSeconds,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ], ['scope_type', 'scope_id'], ['daily_limit_seconds', 'maximum_single_break_seconds', 'warning_before_maximum_seconds', 'updated_at']);
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
