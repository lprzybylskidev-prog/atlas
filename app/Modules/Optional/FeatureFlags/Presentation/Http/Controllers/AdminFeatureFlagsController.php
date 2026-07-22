<?php

declare(strict_types=1);

namespace App\Modules\Optional\FeatureFlags\Presentation\Http\Controllers;

use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagRegistry;
use App\Modules\Optional\FeatureFlags\Application\Contracts\FeatureFlagStore;
use App\Shared\Infrastructure\Database\DatabaseTable;
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
    ) {}

    public function index(Request $request): Response
    {
        $teams = $this->teams();
        $selectedTeamPublicId = $request->query('team');

        if (! is_string($selectedTeamPublicId) || ! $this->teamExists($selectedTeamPublicId)) {
            $selectedTeamPublicId = $this->activeTeamPublicId($request) ?? ($teams[0]['publicId'] ?? null);
        }

        return Inertia::render('Admin/FeatureFlags/Index', [
            'flags' => array_map(fn ($definition): array => $this->flagRow($definition->key->value, $selectedTeamPublicId), $this->registry->all()),
            'teams' => $teams,
            'selectedTeamPublicId' => $selectedTeamPublicId,
            'history' => $this->store->recentHistory(),
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
        } catch (InvalidArgumentException $exception) {
            return $this->redirectToIndex($teamPublicId)->with('error', $exception->getMessage());
        }

        return $this->redirectToIndex($teamPublicId)->with('success', 'Global feature flag value was updated.');
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
            return $this->redirectToIndex(null)->with('error', 'Team was not found.');
        }

        try {
            $this->store->setTeam(
                $flag,
                $teamPublicId,
                $enabled,
                $this->actorPublicId($request),
                $reason,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->redirectToIndex($teamPublicId)->with('error', $exception->getMessage());
        }

        return $this->redirectToIndex($teamPublicId)->with('success', 'Team feature flag override was updated.');
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
            return $this->redirectToIndex(null)->with('error', 'Team was not found.');
        }

        try {
            $this->store->clearTeam(
                $flag,
                $teamPublicId,
                $this->actorPublicId($request),
                $reason,
            );
        } catch (InvalidArgumentException $exception) {
            return $this->redirectToIndex($teamPublicId)->with('error', $exception->getMessage());
        }

        return $this->redirectToIndex($teamPublicId)->with('success', 'Team feature flag override was cleared.');
    }

    /**
     * @return list<array{publicId: string, name: string}>
     */
    private function teams(): array
    {
        $teams = DB::table(DatabaseTable::TEAMS)
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

    private function activeTeamPublicId(Request $request): ?string
    {
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        return is_string($teamPublicId) ? $teamPublicId : null;
    }

    private function teamExists(string $teamPublicId): bool
    {
        return DB::table(DatabaseTable::TEAMS)->where('public_id', $teamPublicId)->exists();
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
