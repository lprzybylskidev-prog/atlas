<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Presentation\Http\Middleware;

use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationSessionState;
use App\Modules\Core\Teams\Application\Public\Persistence\TeamsDatabaseTable;
use App\Modules\Optional\TimeTracking\Application\Contracts\ActiveTimeLockStore;
use App\Modules\Optional\TimeTracking\Application\Permissions\TimeTrackingPermissionCatalog;
use App\Modules\Optional\TimeTracking\Application\TimeTrackingModuleAccess;
use App\Modules\Optional\TimeTracking\Application\WorkSessionCoordinator;
use Closure;
use DateTimeImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class SynchronizeWorkSession
{
    public function __construct(
        private ActiveTimeLockStore $locks,
        private WorkSessionCoordinator $coordinator,
        private TimeTrackingModuleAccess $access,
        private ImpersonationSessionState $impersonation,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($this->impersonation->active($request)) {
            $response = $next($request);

            return $response instanceof Response ? $response : new Response;
        }

        $userId = $this->userId($request);
        $occurredAt = new DateTimeImmutable;

        if ($userId !== null) {
            $lock = $this->locks->activeForUser($userId);

            if ($lock !== null && $this->routeIsBlockedByActiveLock($request, $lock->type)) {
                return $this->lockedResponse($request, $lock->type);
            }
        }

        if ($userId !== null && $request->route()?->getName() === 'logout') {
            $this->coordinator->endForLogout($userId, $occurredAt);

            $response = $next($request);

            return $response instanceof Response ? $response : new Response;
        }

        $response = $next($request);

        if ($request->route()?->getName() === 'time-tracking.activity.record') {
            return $response instanceof Response ? $response : new Response;
        }

        $userId = $this->userId($request);
        $teamId = $this->activeTeamId($request);

        if ($userId !== null && $teamId !== null && $request->hasSession()) {
            $teamPublicId = $this->activeTeamPublicId($request);
            $userPublicId = $this->userPublicId($request);

            if (! $this->access->allows($teamId, $teamPublicId, $userPublicId)) {
                $this->coordinator->endForModuleUnavailable($userId, $occurredAt);

                return $response instanceof Response ? $response : new Response;
            }

            $this->coordinator->synchronizeActiveTeam(
                userId: $userId,
                teamId: $teamId,
                laravelSessionId: $request->session()->getId(),
                occurredAt: $occurredAt,
            );
        }

        return $response instanceof Response ? $response : new Response;
    }

    private function userId(Request $request): ?int
    {
        $id = data_get($request->user(), 'id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function activeTeamId(Request $request): ?int
    {
        if (! $request->hasSession()) {
            return null;
        }

        $teamPublicId = $this->activeTeamPublicId($request);

        if (! is_string($teamPublicId) || $teamPublicId === '') {
            return null;
        }

        $id = DB::table(TeamsDatabaseTable::TEAMS)
            ->where('public_id', $teamPublicId)
            ->value('id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function activeTeamPublicId(Request $request): ?string
    {
        if (! $request->hasSession()) {
            return null;
        }

        $teamPublicId = $request->session()->get('active_team_public_id');

        return is_string($teamPublicId) && $teamPublicId !== '' ? $teamPublicId : null;
    }

    private function userPublicId(Request $request): ?string
    {
        $publicId = data_get($request->user(), 'public_id');

        return is_string($publicId) && $publicId !== '' ? $publicId : null;
    }

    private function routeIsBlockedByActiveLock(Request $request, string $lockType): bool
    {
        $routeName = $request->route()?->getName();

        if ($lockType === 'break' && in_array($routeName, [TimeTrackingPermissionCatalog::BREAK_SHOW, TimeTrackingPermissionCatalog::BREAK_END], true)) {
            return false;
        }

        if ($lockType === 'other_work' && in_array($routeName, [TimeTrackingPermissionCatalog::OTHER_WORK_SHOW, TimeTrackingPermissionCatalog::OTHER_WORK_END], true)) {
            return false;
        }

        return $request->route()?->getName() !== null;
    }

    private function lockedResponse(Request $request, string $lockType): Response
    {
        if ($request->isMethod('GET') && $lockType === 'break') {
            return new RedirectResponse(route(TimeTrackingPermissionCatalog::BREAK_SHOW));
        }

        if ($request->isMethod('GET') && $lockType === 'other_work') {
            return new RedirectResponse(route(TimeTrackingPermissionCatalog::OTHER_WORK_SHOW));
        }

        throw new HttpException(423, sprintf('TimeTracking %s is active and blocks this session action.', $lockType));
    }
}
