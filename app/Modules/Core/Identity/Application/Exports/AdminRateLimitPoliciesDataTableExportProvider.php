<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicy;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicyCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminRateLimitPoliciesDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::RATE_LIMITS;
    }

    public function tableName(): string
    {
        return 'Rate-limit policies';
    }

    public function owningModuleKey(): string
    {
        return 'identity';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-rate-limits-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'policy' => 'Policy',
            'policyFamily' => 'Policy family',
            'maxAttempts' => 'Max attempts',
            'decaySeconds' => 'Decay seconds',
            'keyParts' => 'Key parts',
            'progressiveDelays' => 'Progressive delays',
            'temporaryLockSeconds' => 'Temporary lock seconds',
            'hasProgressiveDelay' => 'Has progressive delay',
            'hasTemporaryLock' => 'Has temporary lock',
            'rejections' => 'Rejections',
            'distinctKeys' => 'Distinct keys',
            'lastRejectedAt' => 'Last rejected at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $stats = $this->stats();
        $rows = array_map(fn (RateLimitPolicy $policy): array => $this->row($policy, $stats[$policy->name] ?? null), $this->catalog()->all());

        foreach ($this->sorted($this->filtered($this->filteredByControls($rows, $request), $request), $request) as $row) {
            yield $row;
        }
    }

    private function catalog(): RateLimitPolicyCatalog
    {
        return RateLimitPolicyCatalog::fromConfiguredValue(config('atlas.security.rate_limits.policies'));
    }

    /**
     * @return array<string, array{rejections: int, distinctKeys: int, lastRejectedAt: string|null}>
     */
    private function stats(): array
    {
        $stats = [];

        foreach (DB::table(DatabaseTable::RATE_LIMIT_REJECTIONS)
            ->selectRaw('policy, sum(rejections_count) as rejections, count(*) as distinct_keys, max(last_rejected_at) as last_rejected_at')
            ->groupBy('policy')
            ->get() as $row) {
            $values = get_object_vars($row);
            $policy = self::stringValue($values['policy'] ?? '');

            if ($policy === '') {
                continue;
            }

            $stats[$policy] = [
                'rejections' => is_numeric($values['rejections'] ?? null) ? (int) $values['rejections'] : 0,
                'distinctKeys' => is_numeric($values['distinct_keys'] ?? null) ? (int) $values['distinct_keys'] : 0,
                'lastRejectedAt' => is_scalar($values['last_rejected_at'] ?? null) ? (string) $values['last_rejected_at'] : null,
            ];
        }

        return $stats;
    }

    /**
     * @param  array{rejections: int, distinctKeys: int, lastRejectedAt: string|null}|null  $stats
     * @return array<string, scalar|\Stringable|null>
     */
    private function row(RateLimitPolicy $policy, ?array $stats): array
    {
        return [
            'publicId' => $policy->name,
            'policy' => $policy->name,
            'policyFamily' => $this->policyFamily($policy->name),
            'maxAttempts' => $policy->maxAttempts,
            'decaySeconds' => $policy->decaySeconds,
            'keyParts' => implode(', ', array_map(static fn ($part): string => $part->value, $policy->keyParts)),
            'progressiveDelays' => implode(', ', $policy->progressiveDelaySeconds),
            'temporaryLockSeconds' => $policy->temporaryLockSeconds,
            'hasProgressiveDelay' => $policy->supportsProgressiveDelay(),
            'hasTemporaryLock' => $policy->supportsTemporaryLock(),
            'rejections' => $stats['rejections'] ?? 0,
            'distinctKeys' => $stats['distinctKeys'] ?? 0,
            'lastRejectedAt' => $stats['lastRejectedAt'] ?? null,
        ];
    }

    /**
     * @param  list<array<string, scalar|\Stringable|null>>  $rows
     * @return list<array<string, scalar|\Stringable|null>>
     */
    private function filteredByControls(array $rows, ReportExportGenerationRequest $request): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($request): bool {
            $family = self::filterValue($request, 'family');
            $activity = self::filterValue($request, 'activity');
            $keyPart = self::filterValue($request, 'key_part');
            $progressiveDelay = self::filterValue($request, 'progressive_delay');
            $temporaryLock = self::filterValue($request, 'temporary_lock');

            if ($family !== 'all' && $row['policyFamily'] !== $family) {
                return false;
            }

            if ($activity === 'with_rejections' && self::intValue($row['rejections'] ?? 0) <= 0) {
                return false;
            }

            if ($activity === 'without_rejections' && self::intValue($row['rejections'] ?? 0) > 0) {
                return false;
            }

            if ($keyPart !== 'all' && ! str_contains((string) ($row['keyParts'] ?? ''), $keyPart)) {
                return false;
            }

            if ($progressiveDelay === 'enabled' && $row['hasProgressiveDelay'] !== true) {
                return false;
            }

            if ($progressiveDelay === 'disabled' && $row['hasProgressiveDelay'] !== false) {
                return false;
            }

            if ($temporaryLock === 'enabled' && $row['hasTemporaryLock'] !== true) {
                return false;
            }

            if ($temporaryLock === 'disabled' && $row['hasTemporaryLock'] !== false) {
                return false;
            }

            return true;
        }));
    }

    private function policyFamily(string $policy): string
    {
        $position = strpos($policy, '.');

        return $position === false ? $policy : substr($policy, 0, $position);
    }

    private static function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
