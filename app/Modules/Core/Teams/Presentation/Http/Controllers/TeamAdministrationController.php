<?php

declare(strict_types=1);

namespace App\Modules\Core\Teams\Presentation\Http\Controllers;

use App\Modules\Core\Teams\Infrastructure\Persistence\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

final class TeamAdministrationController
{
    public function __invoke(): Response
    {
        return Inertia::render('Admin/Teams/Index', [
            'teams' => Team::query()
                ->orderBy('name')
                ->get(['id', 'public_id', 'name', 'is_active', 'created_at', 'updated_at'])
                ->map(static fn (Team $team): array => [
                    'id' => $team->id,
                    'publicId' => (string) $team->public_id,
                    'name' => $team->name,
                    'isActive' => $team->is_active,
                    'createdAt' => $team->created_at?->toISOString() ?? '',
                    'updatedAt' => $team->updated_at?->toISOString() ?? '',
                ])
                ->all(),
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

        Team::query()->create([
            'name' => is_array($validated) && is_string($validated['name'] ?? null) ? $validated['name'] : '',
        ]);

        return redirect()->route('admin.teams.index')->with('success', 'Team was created.');
    }

    public function update(Request $request, string $team): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:teams,name,'.$team.',public_id'],
        ]);

        Team::query()
            ->where('public_id', $team)
            ->update(['name' => is_array($validated) && is_string($validated['name'] ?? null) ? $validated['name'] : '']);

        return redirect()->route('admin.teams.edit', ['team' => $team])->with('success', 'Team was updated.');
    }

    public function activate(string $team): RedirectResponse
    {
        Team::query()->where('public_id', $team)->update(['is_active' => true]);

        return redirect()->route('admin.teams.index')->with('success', 'Team was activated.');
    }

    public function deactivate(string $team): RedirectResponse
    {
        Team::query()->where('public_id', $team)->update(['is_active' => false]);

        return redirect()->route('admin.teams.index')->with('success', 'Team was deactivated.');
    }

    public function destroy(string $team): RedirectResponse
    {
        $record = Team::query()->where('public_id', $team)->first();

        if ($record instanceof Team && ! DB::table('team_user_assignments')->where('team_id', $record->id)->exists()) {
            $record->delete();
        }

        return redirect()->route('admin.teams.index')->with('success', 'Team delete was attempted.');
    }
}
