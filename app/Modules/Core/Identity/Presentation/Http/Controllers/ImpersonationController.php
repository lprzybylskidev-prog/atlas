<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Presentation\Http\Controllers;

use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Modules\Core\Identity\Infrastructure\Persistence\User;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Presentation\Support\FlashMessage;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final readonly class ImpersonationController
{
    public function __construct(
        private ImpersonationManager $impersonation,
    ) {}

    public function create(Request $request, string $user): Response
    {
        $actor = $request->user();
        $eligibility = $actor instanceof User ? $this->impersonation->eligibility($request, (string) $actor->public_id, $user) : null;

        if ($eligibility === null || ! $eligibility->canStart) {
            abort(403);
        }

        return Inertia::render('Admin/Impersonation/Start', [
            'target' => User::query()
                ->where('public_id', $user)
                ->firstOrFail(['public_id', 'name', 'email', 'account_sensitivity']),
            'teams' => $this->teams($user),
            'requiresSensitiveOverride' => $eligibility->requiresSensitiveOverride,
        ]);
    }

    public function store(Request $request, string $user): RedirectResponse
    {
        $validated = $request->validate([
            'team_public_id' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:1000'],
            'override_sensitive' => ['sometimes', 'boolean'],
        ]);
        $validated = is_array($validated) ? $validated : [];
        $actor = $request->user();

        if (! $actor instanceof User || ! $this->impersonation->start(
            request: $request,
            actor: $actor,
            targetPublicId: $user,
            teamPublicId: is_string($validated['team_public_id'] ?? null) ? $validated['team_public_id'] : '',
            reason: is_string($validated['reason'] ?? null) ? $validated['reason'] : '',
            overrideSensitive: ($validated['override_sensitive'] ?? false) === true,
        )) {
            throw ValidationException::withMessages([
                'user' => 'Impersonation cannot be started for this account.',
            ]);
        }

        return redirect()->route('dashboard')->with('flash.messages', [
            FlashMessage::success('flash.auth.impersonation_started'),
        ]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->impersonation->stop($request);

        return redirect()->route('admin.system-status')->with('flash.messages', [
            FlashMessage::success('flash.auth.impersonation_ended'),
        ]);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function teams(string $userPublicId): array
    {
        $teams = [];

        foreach (DB::table(DatabaseTable::TEAM_USER_ASSIGNMENTS)
            ->join(DatabaseTable::USERS, 'team_user_assignments.user_id', '=', 'users.id')
            ->join(DatabaseTable::TEAMS, 'team_user_assignments.team_id', '=', 'teams.id')
            ->where('users.public_id', $userPublicId)
            ->where('teams.is_active', true)
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_from')->orWhere('team_user_assignments.valid_from', '<=', now());
            })
            ->where(static function (Builder $query): void {
                $query->whereNull('team_user_assignments.valid_to')->orWhere('team_user_assignments.valid_to', '>', now());
            })
            ->orderBy('teams.name')
            ->get(['teams.public_id', 'teams.name'])
            ->all() as $team) {
            $teams[] = [
                'value' => $this->scalarString($team->public_id ?? ''),
                'label' => $this->scalarString($team->name ?? ''),
            ];
        }

        return $teams;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : '';
    }
}
