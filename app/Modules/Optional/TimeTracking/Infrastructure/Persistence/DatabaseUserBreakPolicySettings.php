<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Infrastructure\Persistence;

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Contracts\BreakPolicyStore;
use App\Modules\Optional\TimeTracking\Application\Public\Contracts\UserBreakPolicySettings;
use App\Modules\Optional\TimeTracking\Application\Public\Persistence\TimeTrackingDatabaseTable;
use Illuminate\Database\ConnectionInterface;

final readonly class DatabaseUserBreakPolicySettings implements UserBreakPolicySettings
{
    public function __construct(
        private ConnectionInterface $database,
        private BreakPolicyStore $policies,
    ) {}

    public function resolvedForUserTeam(string $userPublicId, string $teamPublicId): array
    {
        [$userId, $teamId] = $this->ids($userPublicId, $teamPublicId);

        if ($userId < 1 || $teamId < 1) {
            return [
                'dailyLimitMinutes' => 0,
                'maximumSingleBreakMinutes' => 0,
                'source' => 'missing',
            ];
        }

        $policy = $this->policies->policyForUserTeam($userId, $teamId);

        return [
            'dailyLimitMinutes' => intdiv($policy->dailyLimitSeconds, 60),
            'maximumSingleBreakMinutes' => intdiv($policy->maximumSingleBreakSeconds, 60),
            'source' => $policy->source,
        ];
    }

    public function isTrackedForUserTeam(string $userPublicId, string $teamPublicId): bool
    {
        [$userId, $teamId] = $this->ids($userPublicId, $teamPublicId);
        $assignmentId = $this->assignmentId($userId, $teamId);

        if ($assignmentId < 1) {
            return false;
        }

        return $this->database->table(TimeTrackingDatabaseTable::USER_TEAM_SETTINGS)
            ->where('team_user_assignment_id', $assignmentId)
            ->where('tracking_enabled', true)
            ->exists();
    }

    public function resolvedForTeam(string $teamPublicId): array
    {
        $teamId = $this->teamId($teamPublicId);

        if ($teamId < 1) {
            return [
                'dailyLimitMinutes' => 0,
                'maximumSingleBreakMinutes' => 0,
                'source' => 'missing',
            ];
        }

        $policy = $this->policies->policyForUserTeam(0, $teamId);
        $teamPolicy = $this->database->table(TimeTrackingDatabaseTable::BREAK_POLICIES)
            ->where('scope_type', 'team')
            ->where('scope_id', $teamId)
            ->first(['daily_limit_seconds', 'maximum_single_break_seconds']);

        if (is_object($teamPolicy)) {
            return [
                'dailyLimitMinutes' => intdiv($this->intValue($teamPolicy->daily_limit_seconds ?? null), 60),
                'maximumSingleBreakMinutes' => intdiv($this->intValue($teamPolicy->maximum_single_break_seconds ?? null), 60),
                'source' => 'team',
            ];
        }

        return [
            'dailyLimitMinutes' => intdiv($policy->dailyLimitSeconds, 60),
            'maximumSingleBreakMinutes' => intdiv($policy->maximumSingleBreakSeconds, 60),
            'source' => $policy->source,
        ];
    }

    public function setTeamOverrides(string $teamPublicId, ?int $dailyLimitMinutes, ?int $maximumSingleBreakMinutes): void
    {
        $teamId = $this->teamId($teamPublicId);

        if ($teamId < 1) {
            return;
        }

        if ($dailyLimitMinutes === null && $maximumSingleBreakMinutes === null) {
            $this->database->table(TimeTrackingDatabaseTable::BREAK_POLICIES)
                ->where('scope_type', 'team')
                ->where('scope_id', $teamId)
                ->delete();

            return;
        }

        $current = $this->policies->policyForUserTeam(0, $teamId);
        $maximumSingleBreakSeconds = $maximumSingleBreakMinutes === null
            ? $current->maximumSingleBreakSeconds
            : max(1, $maximumSingleBreakMinutes) * 60;

        $this->policies->setTeamPolicy(
            $teamId,
            ($dailyLimitMinutes === null ? intdiv($current->dailyLimitSeconds, 60) : max(1, $dailyLimitMinutes)) * 60,
            $maximumSingleBreakSeconds,
            min($current->warningBeforeMaximumSeconds, $maximumSingleBreakSeconds - 1),
        );
    }

    public function setUserTeamOverrides(
        string $userPublicId,
        string $teamPublicId,
        ?int $dailyLimitMinutes,
        ?int $maximumSingleBreakMinutes,
    ): void {
        [$userId, $teamId] = $this->ids($userPublicId, $teamPublicId);
        $assignmentId = $this->assignmentId($userId, $teamId);

        if ($assignmentId < 1) {
            return;
        }

        if ($dailyLimitMinutes === null && $maximumSingleBreakMinutes === null) {
            $this->policies->clearUserTeamPolicy($assignmentId);

            return;
        }

        $current = $this->policies->policyForUserTeam($userId, $teamId);
        $maximumSingleBreakSeconds = $maximumSingleBreakMinutes === null
            ? $current->maximumSingleBreakSeconds
            : max(1, $maximumSingleBreakMinutes) * 60;
        $this->policies->setUserTeamPolicy(
            $assignmentId,
            ($dailyLimitMinutes === null ? intdiv($current->dailyLimitSeconds, 60) : max(1, $dailyLimitMinutes)) * 60,
            $maximumSingleBreakSeconds,
            min($current->warningBeforeMaximumSeconds, $maximumSingleBreakSeconds - 1),
        );
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function ids(string $userPublicId, string $teamPublicId): array
    {
        $userId = $this->database->table(IdentityDatabaseTable::USERS)->where('public_id', $userPublicId)->value('id');
        $teamId = $this->database->table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        return [
            is_numeric($userId) ? (int) $userId : 0,
            is_numeric($teamId) ? (int) $teamId : 0,
        ];
    }

    private function assignmentId(int $userId, int $teamId): int
    {
        $id = $this->database->table(TeamsDatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->where('user_id', $userId)
            ->where('team_id', $teamId)
            ->whereNull('valid_to')
            ->value('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    private function teamId(string $teamPublicId): int
    {
        $id = $this->database->table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->value('id');

        return is_numeric($id) ? (int) $id : 0;
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
