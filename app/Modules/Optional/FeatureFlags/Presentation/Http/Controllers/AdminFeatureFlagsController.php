<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagRegistry;
use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagStore;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableDefinition;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableResult;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

final readonly class AdminFeatureFlagsController
{
    public function __construct(
        private FeatureFlagRegistry $registry,
        private FeatureFlagStore $store,
        private ArrayTableProcessor $tables,
        private TableSavedViewService $views,
        private TableRequestContext $context,
    ) {}

    public function index(Request $request): Response
    {
        $teams = $this->teams();
        $selectedTeamPublicId = $request->query('team');

        if (! is_string($selectedTeamPublicId) || ! $this->teamExists($selectedTeamPublicId)) {
            $selectedTeamPublicId = $this->activeTeamPublicId($request) ?? ($teams[0]['publicId'] ?? null);
        }
        $flags = array_map(fn ($definition): array => $this->flagRow($definition->key->value, $selectedTeamPublicId), $this->registry->all());
        $flagFilters = $this->flagFilters($request, $flags, $teams);
        $flagFilters['team'] = $selectedTeamPublicId ?? 'all';
        $filteredFlags = $this->filteredFlags($flags, $flagFilters);
        $flagDefinition = AdminTableDefinitions::get(AdminTableDefinitions::FEATURE_FLAGS);
        $flagResult = $this->tableResult($request, $flagDefinition, $filteredFlags);
        $flagTable = $flagResult->tableMeta($flagDefinition->key, AdminDataTableExportMeta::defaults());
        $flagTable['state']['filters'] = $flagFilters;
        $history = $this->historyRows();

        return Inertia::render('Admin/FeatureFlags/Index', [
            'flags' => $flagResult->rows,
            'teams' => $teams,
            'selectedTeamPublicId' => $selectedTeamPublicId,
            'summary' => [
                'registered' => count($flags),
                'visible' => $flagResult->total,
                'effectiveEnabled' => count(array_filter($flags, static fn (array $flag): bool => ($flag['effectiveEnabled'] ?? false) === true)),
                'globalValues' => count(array_filter($flags, static fn (array $flag): bool => array_key_exists('globalEnabled', $flag) && $flag['globalEnabled'] !== null)),
                'teamOverrides' => count(array_filter($flags, static fn (array $flag): bool => array_key_exists('teamEnabled', $flag) && $flag['teamEnabled'] !== null)),
                'historyRows' => count($history),
            ],
            'filterOptions' => $this->filterOptions($flags, $teams),
            'history' => $history,
            'table' => $flagTable,
        ]);
    }

    public function updateGlobal(Request $request, string $flag): RedirectResponse
    {
        $request->validate([
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
            'team_public_id' => ['nullable', 'string'],
        ]);
        $enabled = $request->boolean('enabled');
        $reason = $this->inputString($request, 'reason');
        $teamPublicId = $this->nullableInputString($request, 'team_public_id');

        try {
            $this->store->setGlobal(
                $flag,
                $enabled,
                $this->actorPublicId($request),
                $reason,
            );
        } catch (InvalidArgumentException) {
            return $this->redirectToIndex($teamPublicId)->with('flash.messages', [
                FlashMessage::error('flash.feature_flags.global_update_failed'),
            ]);
        }

        return $this->redirectToIndex($teamPublicId)->with('flash.messages', [
            FlashMessage::success('flash.feature_flags.global_updated'),
        ]);
    }

    public function updateTeam(Request $request, string $flag): RedirectResponse
    {
        $request->validate([
            'team_public_id' => ['required', 'string'],
            'enabled' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $teamPublicId = $this->inputString($request, 'team_public_id');
        $enabled = $request->boolean('enabled');
        $reason = $this->inputString($request, 'reason');

        if (! $this->teamExists($teamPublicId)) {
            return $this->redirectToIndex(null)->with('flash.messages', [
                FlashMessage::error('flash.feature_flags.team_not_found'),
            ]);
        }

        try {
            $this->store->setTeam(
                $flag,
                $teamPublicId,
                $enabled,
                $this->actorPublicId($request),
                $reason,
            );
        } catch (InvalidArgumentException) {
            return $this->redirectToIndex($teamPublicId)->with('flash.messages', [
                FlashMessage::error('flash.feature_flags.team_update_failed'),
            ]);
        }

        return $this->redirectToIndex($teamPublicId)->with('flash.messages', [
            FlashMessage::success('flash.feature_flags.team_updated'),
        ]);
    }

    public function clearTeam(Request $request, string $flag): RedirectResponse
    {
        $request->validate([
            'team_public_id' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $teamPublicId = $this->inputString($request, 'team_public_id');
        $reason = $this->inputString($request, 'reason');

        if (! $this->teamExists($teamPublicId)) {
            return $this->redirectToIndex(null)->with('flash.messages', [
                FlashMessage::error('flash.feature_flags.team_not_found'),
            ]);
        }

        try {
            $this->store->clearTeam(
                $flag,
                $teamPublicId,
                $this->actorPublicId($request),
                $reason,
            );
        } catch (InvalidArgumentException) {
            return $this->redirectToIndex($teamPublicId)->with('flash.messages', [
                FlashMessage::error('flash.feature_flags.team_clear_failed'),
            ]);
        }

        return $this->redirectToIndex($teamPublicId)->with('flash.messages', [
            FlashMessage::success('flash.feature_flags.team_cleared'),
        ]);
    }

    /**
     * @return list<array{publicId: string, name: string}>
     */
    private function teams(): array
    {
        $teams = DB::table(TeamsDatabaseTable::TEAMS)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['public_id', 'name'])
            ->map(fn (object $team): array => [
                'publicId' => $this->scalarString($team->public_id ?? null),
                'name' => $this->scalarString($team->name ?? null),
            ])
            ->all();

        return array_values($teams);
    }

    /**
     * @return array<string, mixed>
     */
    private function flagRow(string $key, ?string $teamPublicId): array
    {
        $state = $this->store->state($key, $teamPublicId);

        return [
            'key' => $state->definition->key->value,
            'name' => $state->definition->name,
            'description' => $state->definition->description,
            'type' => $state->definition->type->value,
            'ownerModule' => $state->definition->ownerModule,
            'lifecycle' => $state->definition->lifecycle,
            'teamScoped' => $state->definition->teamScoped,
            'defaultEnabled' => $state->definition->defaultEnabled,
            'globalEnabled' => $state->globalValue === null ? null : (bool) ($state->globalValue['enabled'] ?? false),
            'teamEnabled' => $state->teamValue === null ? null : (bool) ($state->teamValue['enabled'] ?? false),
            'effectiveEnabled' => $state->enabled(),
            'source' => $state->source,
            'selectedTeamPublicId' => $state->teamPublicId,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function tableResult(Request $request, TableDefinition $definition, array $rows): TableResult
    {
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);

        return $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<array{publicId: string, name: string}>  $teams
     * @return array{team: string, status: string, source: string, owner: string, lifecycle: string}
     */
    private function flagFilters(Request $request, array $rows, array $teams): array
    {
        return [
            'team' => $this->oneOf($request->query('team'), $this->allOr($this->uniqueValues($teams, 'publicId'))),
            'status' => $this->oneOf($request->query('status'), ['all', 'enabled', 'disabled']),
            'source' => $this->oneOf($request->query('source'), ['all', 'default', 'global', 'team']),
            'owner' => $this->oneOf($request->query('owner'), $this->allOr($this->uniqueValues($rows, 'ownerModule'))),
            'lifecycle' => $this->oneOf($request->query('lifecycle'), $this->allOr($this->uniqueValues($rows, 'lifecycle'))),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{team: string, status: string, source: string, owner: string, lifecycle: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filteredFlags(array $rows, array $filters): array
    {
        return array_values(array_filter($rows, static function (array $row) use ($filters): bool {
            if ($filters['status'] === 'enabled' && ($row['effectiveEnabled'] ?? false) !== true) {
                return false;
            }

            if ($filters['status'] === 'disabled' && ($row['effectiveEnabled'] ?? false) !== false) {
                return false;
            }

            if ($filters['source'] !== 'all' && ($row['source'] ?? null) !== $filters['source']) {
                return false;
            }

            if ($filters['owner'] !== 'all' && ($row['ownerModule'] ?? null) !== $filters['owner']) {
                return false;
            }

            if ($filters['lifecycle'] !== 'all' && ($row['lifecycle'] ?? null) !== $filters['lifecycle']) {
                return false;
            }

            return true;
        }));
    }

    /**
     * @param  list<array<string, mixed>>  $flags
     * @param  list<array{publicId: string, name: string}>  $teams
     * @return array{owners: list<string>, lifecycles: list<string>, teams: list<array{publicId: string, name: string}>}
     */
    private function filterOptions(array $flags, array $teams): array
    {
        return [
            'owners' => $this->uniqueValues($flags, 'ownerModule'),
            'lifecycles' => $this->uniqueValues($flags, 'lifecycle'),
            'teams' => $teams,
        ];
    }

    /**
     * @return list<array<string, scalar|null>>
     */
    private function historyRows(): array
    {
        return array_map(static fn (array $row): array => [
            'publicId' => is_scalar($row['publicId'] ?? null) ? (string) $row['publicId'] : '',
            'createdAt' => is_scalar($row['createdAt'] ?? null) ? (string) $row['createdAt'] : '',
            'flagKey' => is_scalar($row['flagKey'] ?? null) ? (string) $row['flagKey'] : '',
            'scope' => is_scalar($row['scope'] ?? null) ? (string) $row['scope'] : '',
            'teamName' => is_scalar($row['teamName'] ?? null) ? (string) $row['teamName'] : null,
            'teamPublicId' => is_scalar($row['teamPublicId'] ?? null) ? (string) $row['teamPublicId'] : null,
            'action' => is_scalar($row['action'] ?? null) ? (string) $row['action'] : '',
            'reason' => is_scalar($row['reason'] ?? null) ? (string) $row['reason'] : '',
            'actorPublicId' => is_scalar($row['actorPublicId'] ?? null) ? (string) $row['actorPublicId'] : '',
            'beforeEnabled' => is_array($row['before'] ?? null) ? (bool) ($row['before']['enabled'] ?? false) : null,
            'afterEnabled' => is_array($row['after'] ?? null) ? (bool) ($row['after']['enabled'] ?? false) : null,
        ], $this->store->recentHistory());
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

    private function activeTeamPublicId(Request $request): ?string
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($teamPublicId) ? $teamPublicId : null;
    }

    private function teamExists(string $teamPublicId): bool
    {
        return DB::table(TeamsDatabaseTable::TEAMS)->where('public_id', $teamPublicId)->exists();
    }

    private function redirectToIndex(mixed $teamPublicId): RedirectResponse
    {
        $parameters = is_string($teamPublicId) && $teamPublicId !== '' ? ['team' => $teamPublicId] : [];

        return redirect()->route('admin.feature-flags.index', $parameters);
    }

    private function actorPublicId(Request $request): string
    {
        $publicId = data_get($request->user(), 'public_id');

        return is_string($publicId) ? $publicId : '';
    }

    private function inputString(Request $request, string $key): string
    {
        $value = $request->input($key);

        return is_string($value) ? $value : '';
    }

    private function nullableInputString(Request $request, string $key): ?string
    {
        $value = $request->input($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
