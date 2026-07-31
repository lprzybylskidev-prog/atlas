<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicy;
use App\Modules\Core\Identity\Application\RateLimiting\RateLimitPolicyCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final readonly class RateLimitAdministrationController
{
    public function __construct(
        private ArrayTableProcessor $tables,
        private TableRequestContext $context,
        private TableSavedViewService $views,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::RATE_LIMITS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $stats = $this->stats();
        $rows = array_map(fn (RateLimitPolicy $policy): array => $this->row($policy, $stats[$policy->name] ?? null), $this->catalog()->all());
        $filters = $this->filters($request, $rows);
        $filteredRows = $this->filteredRows($rows, $filters);

        $result = $this->tables->process($filteredRows, $definition, $state)
            ->withSavedViews($this->views->listFor(AdminTableDefinitions::RATE_LIMITS, $userId, $teamId));
        $table = $result->tableMeta(AdminTableDefinitions::RATE_LIMITS, AdminDataTableExportMeta::defaults());
        $table['state']['filters'] = $filters;

        return Inertia::render('Admin/RateLimits/Index', [
            'policies' => $result->rows,
            'summary' => [
                'registered' => count($rows),
                'visible' => $result->total,
                'rejections' => array_sum(array_map(fn (array $row): int => $this->intValue($row['rejections'] ?? 0), $rows)),
                'distinctKeys' => array_sum(array_map(fn (array $row): int => $this->intValue($row['distinctKeys'] ?? 0), $rows)),
                'withTemporaryLock' => count(array_filter($rows, static fn (array $row): bool => ($row['hasTemporaryLock'] ?? false) === true)),
                'withProgressiveDelay' => count(array_filter($rows, static fn (array $row): bool => ($row['hasProgressiveDelay'] ?? false) === true)),
            ],
            'filterOptions' => $this->filterOptions($rows),
            'table' => $table,
            'policyOptions' => array_map(
                static fn (RateLimitPolicy $policy): array => ['value' => $policy->name, 'label' => $policy->name],
                $this->catalog()->all(),
            ),
        ]);
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
            $policy = $this->stringValue($values['policy'] ?? '');

            if ($policy === '') {
                continue;
            }

            $stats[$policy] = [
                'rejections' => $this->intValue($values['rejections'] ?? null),
                'distinctKeys' => $this->intValue($values['distinct_keys'] ?? null),
                'lastRejectedAt' => $this->nullableStringValue($values['last_rejected_at'] ?? null),
            ];
        }

        return $stats;
    }

    /**
     * @param  array{rejections: int, distinctKeys: int, lastRejectedAt: string|null}|null  $stats
     * @return array<string, mixed>
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
     * @param  list<array<string, mixed>>  $rows
     * @return array{family: string, activity: string, key_part: string, progressive_delay: string, temporary_lock: string}
     */
    private function filters(Request $request, array $rows): array
    {
        return [
            'family' => $this->oneOf($request->query('family'), $this->allOr($this->uniqueValues($rows, 'policyFamily'))),
            'activity' => $this->oneOf($request->query('activity'), ['all', 'with_rejections', 'without_rejections']),
            'key_part' => $this->oneOf($request->query('key_part'), ['all', 'api_client', 'user', 'team', 'ip']),
            'progressive_delay' => $this->oneOf($request->query('progressive_delay'), ['all', 'enabled', 'disabled']),
            'temporary_lock' => $this->oneOf($request->query('temporary_lock'), ['all', 'enabled', 'disabled']),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{family: string, activity: string, key_part: string, progressive_delay: string, temporary_lock: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredRows(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, function (array $row) use ($filters): bool {
            if ($filters['family'] !== 'all' && ($row['policyFamily'] ?? null) !== $filters['family']) {
                return false;
            }

            if ($filters['activity'] === 'with_rejections' && $this->intValue($row['rejections'] ?? 0) <= 0) {
                return false;
            }

            if ($filters['activity'] === 'without_rejections' && $this->intValue($row['rejections'] ?? 0) > 0) {
                return false;
            }

            if ($filters['key_part'] !== 'all' && ! str_contains($this->stringValue($row['keyParts'] ?? ''), $filters['key_part'])) {
                return false;
            }

            if ($filters['progressive_delay'] === 'enabled' && ($row['hasProgressiveDelay'] ?? false) !== true) {
                return false;
            }

            if ($filters['progressive_delay'] === 'disabled' && ($row['hasProgressiveDelay'] ?? false) !== false) {
                return false;
            }

            if ($filters['temporary_lock'] === 'enabled' && ($row['hasTemporaryLock'] ?? false) !== true) {
                return false;
            }

            if ($filters['temporary_lock'] === 'disabled' && ($row['hasTemporaryLock'] ?? false) !== false) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{families: list<string>, keyParts: list<string>}
     */
    private function filterOptions(array $rows): array
    {
        return [
            'families' => $this->uniqueValues($rows, 'policyFamily'),
            'keyParts' => ['api_client', 'user', 'team', 'ip'],
        ];
    }

    private function policyFamily(string $policy): string
    {
        $position = strpos($policy, '.');

        return $position === false ? $policy : substr($policy, 0, $position);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<string>
     */
    private function uniqueValues(array $rows, string $key): array
    {
        $values = [];

        foreach ($rows as $row) {
            $value = $row[$key] ?? null;

            if (is_scalar($value) && $value !== '') {
                $values[] = (string) $value;
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private function allOr(array $values): array
    {
        return array_values(array_unique(['all', ...$values]));
    }

    /**
     * @param  list<string>  $allowed
     */
    private function oneOf(mixed $value, array $allowed): string
    {
        if (is_string($value) && in_array($value, $allowed, true)) {
            return $value;
        }

        return 'all';
    }

    private function stringValue(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }

    private function nullableStringValue(mixed $value): ?string
    {
        return is_scalar($value) ? (string) $value : null;
    }
}
