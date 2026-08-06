<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

use App\Modules\Core\Teams\Application\Public\Contracts\TeamLookup;
use Illuminate\Http\Request;

final readonly class TableRequestContext
{
    public function __construct(
        private TeamLookup $teams,
    ) {}

    /**
     * @return array{0: int, 1: int|null, 2: string|null}
     */
    public function userTeam(Request $request): array
    {
        $user = $request->user();
        $userId = data_get($user, 'id');
        $actorPublicId = data_get($user, 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $teamId = is_string($teamPublicId) ? $this->teams->internalIdForPublicId($teamPublicId) : null;

        abort_unless(is_numeric($userId), 403);

        return [
            (int) $userId,
            is_numeric($teamId) ? (int) $teamId : null,
            is_string($actorPublicId) ? $actorPublicId : null,
        ];
    }
}
