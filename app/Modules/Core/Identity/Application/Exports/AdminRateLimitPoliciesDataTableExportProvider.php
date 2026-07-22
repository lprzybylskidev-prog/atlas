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
            'maxAttempts' => 'Max attempts',
            'decaySeconds' => 'Decay seconds',
            'keyParts' => 'Key parts',
            'progressiveDelays' => 'Progressive delays',
            'temporaryLockSeconds' => 'Temporary lock seconds',
            'rejections' => 'Rejections',
            'distinctKeys' => 'Distinct keys',
            'lastRejectedAt' => 'Last rejected at',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $stats = $this->stats();
        $rows = array_map(fn (RateLimitPolicy $policy): array => $this->row($policy, $stats[$policy->name] ?? null), $this->catalog()->all());

        foreach ($this->sorted($this->filtered($rows, $request), $request) as $row) {
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
            'maxAttempts' => $policy->maxAttempts,
            'decaySeconds' => $policy->decaySeconds,
            'keyParts' => implode(', ', array_map(static fn ($part): string => $part->value, $policy->keyParts)),
            'progressiveDelays' => implode(', ', $policy->progressiveDelaySeconds),
            'temporaryLockSeconds' => $policy->temporaryLockSeconds,
            'rejections' => $stats['rejections'] ?? 0,
            'distinctKeys' => $stats['distinctKeys'] ?? 0,
            'lastRejectedAt' => $stats['lastRejectedAt'] ?? null,
        ];
    }
}
