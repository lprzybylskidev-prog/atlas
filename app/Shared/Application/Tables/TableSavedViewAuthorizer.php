<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

use App\Shared\Application\Modules\Contracts\ModuleGate;
use App\Shared\Application\Modules\ModuleAccessRequest;
use App\Shared\Application\Modules\ModuleKeyResolver;
use App\Shared\Application\Security\Contracts\AdministrativeModeState;
use Illuminate\Http\Request;
use InvalidArgumentException;

final readonly class TableSavedViewAuthorizer
{
    public function __construct(
        private TableRequestContext $context,
        private ModuleGate $moduleGate,
        private ModuleKeyResolver $moduleKeys,
        private AdministrativeModeState $administrativeMode,
    ) {}

    public function authorize(Request $request, string $tableKey, bool $teamSharingRequested = false): void
    {
        try {
            $access = RegisteredTables::savedViewAccess($tableKey);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        if ($teamSharingRequested && ! $access->teamSharingAllowed) {
            abort(403);
        }

        if (str_starts_with($access->permission, 'admin.') && ! $this->administrativeMode->active($request)) {
            abort(403);
        }

        [, $teamId] = $this->context->userTeam($request);
        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId) || $teamId === null) {
            abort(403);
        }

        $allowed = $this->moduleGate->allows(new ModuleAccessRequest(
            moduleKey: $this->moduleKeys->forPermission($access->permission),
            activeTeamId: $teamId,
            activeTeamPublicId: $teamPublicId,
            userPublicId: $userPublicId,
            requiredPermission: $access->permission,
        ));

        abort_unless($allowed, 403);
    }
}
