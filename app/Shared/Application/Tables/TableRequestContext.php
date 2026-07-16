<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class TableRequestContext
{
    /**
     * @return array{0: int, 1: int|null, 2: string|null}
     */
    public function userTeam(Request $request): array
    {
        $user = $request->user();
        $userId = data_get($user, 'id');
        $actorPublicId = data_get($user, 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;
        $teamId = is_string($teamPublicId) ? DB::table('teams')->where('public_id', $teamPublicId)->value('id') : null;

        abort_unless(is_numeric($userId), 403);

        return [
            (int) $userId,
            is_numeric($teamId) ? (int) $teamId : null,
            is_string($actorPublicId) ? $actorPublicId : null,
        ];
    }
}
