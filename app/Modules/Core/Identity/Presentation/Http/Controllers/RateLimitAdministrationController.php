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

        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor(AdminTableDefinitions::RATE_LIMITS, $userId, $teamId));

        return Inertia::render('Admin/RateLimits/Index', [
            'policies' => $result->rows,
            'table' => $result->tableMeta(AdminTableDefinitions::RATE_LIMITS, AdminDataTableExportMeta::defaults()),
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
