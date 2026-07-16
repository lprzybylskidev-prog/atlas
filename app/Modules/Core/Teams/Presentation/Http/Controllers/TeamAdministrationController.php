<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Audit\Application\Public\Contracts\AuditRecorder;
use App\Modules\Core\Audit\Application\Public\DTOs\AuditEvent;
use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Application\Tables\ArrayTableProcessor;
use App\Shared\Application\Tables\TableRequestContext;
use App\Shared\Application\Tables\TableSavedViewService;
use App\Shared\Application\Tables\TableState;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class TeamAdministrationController
{
    public function __construct(
        private readonly ArrayTableProcessor $tables,
        private readonly TableSavedViewService $views,
        private readonly TableRequestContext $context,
        private readonly AuditRecorder $audit,
    ) {}

    public function __invoke(Request $request): Response
    {
        $definition = AdminTableDefinitions::get(AdminTableDefinitions::TEAMS);
        $state = TableState::fromRequest($request, $definition);
        [$userId, $teamId] = $this->context->userTeam($request);
        $rows = array_values(Team::query()
            ->get(['id', 'public_id', 'name', 'is_active', 'created_at', 'updated_at'])
            ->map(static fn (Team $team): array => [
                'id' => $team->id,
                'publicId' => (string) $team->public_id,
                'name' => $team->name,
                'isActive' => $team->is_active,
                'createdAt' => $team->created_at?->toISOString() ?? '',
                'updatedAt' => $team->updated_at?->toISOString() ?? '',
            ])
            ->all());
        $result = $this->tables->process($rows, $definition, $state)
            ->withSavedViews($this->views->listFor($definition->key, $userId, $teamId));

        return Inertia::render('Admin/Teams/Index', [
            'teams' => $result->rows,
            'table' => $result->tableMeta($definition->key),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Teams/Create');
    }

    public function edit(string $team): Response
    {
        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        return Inertia::render('Admin/Teams/Edit', [
            'team' => [
                'publicId' => (string) $record->public_id,
                'name' => $record->name,
                'isActive' => $record->is_active,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name'],
        ]);

        $record = Team::query()->create([
            'name' => is_array($validated) && is_string($validated['name'] ?? null) ? $validated['name'] : '',
        ]);

        $this->recordAudit($request, 'team.created', 'succeeded', 'team', (string) $record->public_id, [], [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ]);

        return redirect()->route('admin.teams.index')->with('success', 'Team was created.');
    }

    public function update(Request $request, string $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name,'.$team.',public_id'],
        ]);

        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        $before = [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ];
        $record->forceFill([
            'name' => is_array($validated) && is_string($validated['name'] ?? null) ? $validated['name'] : '',
        ])->save();

        $this->recordAudit($request, 'team.updated', 'succeeded', 'team', (string) $record->public_id, $before, [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ]);

        return redirect()->route('admin.teams.edit', ['team' => $team])->with('success', 'Team was updated.');
    }

    public function activate(Request $request, string $team): RedirectResponse
    {
        $this->changeActivation($request, $team, true);

        return redirect()->route('admin.teams.index')->with('success', 'Team was activated.');
    }

    public function deactivate(Request $request, string $team): RedirectResponse
    {
        $this->changeActivation($request, $team, false);

        return redirect()->route('admin.teams.index')->with('success', 'Team was deactivated.');
    }

    public function destroy(Request $request, string $team): RedirectResponse
    {
        $record = Team::query()->where('public_id', $team)->first();

        $deleted = false;

        if ($record instanceof Team && ! DB::table('team_user_assignments')->where('team_id', $record->id)->exists()) {
            $before = [
                'name' => $record->name,
                'isActive' => $record->is_active,
            ];
            $record->delete();
            $deleted = true;

            $this->recordAudit($request, 'team.deleted', 'succeeded', 'team', $team, $before, []);
        }

        if (! $deleted) {
            $this->recordAudit($request, 'team.delete_rejected', 'rejected', 'team', $team, [], []);
        }

        return redirect()->route('admin.teams.index')->with('success', 'Team delete was attempted.');
    }

    private function changeActivation(Request $request, string $team, bool $active): void
    {
        $record = Team::query()->where('public_id', $team)->first();

        if (! $record instanceof Team) {
            abort(404);
        }

        $before = [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ];

        $record->forceFill(['is_active' => $active])->save();

        $this->recordAudit($request, $active ? 'team.activated' : 'team.deactivated', 'succeeded', 'team', $team, $before, [
            'name' => $record->name,
            'isActive' => $record->is_active,
        ]);
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function recordAudit(
        Request $request,
        string $action,
        string $result,
        string $targetType,
        string $targetPublicId,
        array $before,
        array $after,
    ): void {
        $actorPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        $this->audit->record(new AuditEvent(
            module: 'teams',
            action: $action,
            result: $result,
            source: 'admin',
            actorPublicId: is_string($actorPublicId) ? $actorPublicId : null,
            targetType: $targetType,
            targetPublicId: $targetPublicId,
            teamPublicId: is_string($teamPublicId) ? $teamPublicId : null,
            before: $before,
            after: $after,
            security: true,
            securityCategory: 'authorization',
        ));
    }
}
