<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Inertia;

use App\Modules\Core\Authorization\Application\Public\Contracts\EffectivePermissionChecker;
use App\Modules\Core\Authorization\Application\Public\DTOs\EffectivePermissionRequest;
use App\Modules\Core\Identity\Application\Public\Contracts\ImpersonationSessionState;
use App\Shared\Presentation\Inertia\Contracts\InertiaRouteAvailabilityContributor;
use App\Shared\Presentation\Inertia\Contracts\InertiaSharedDataContributor;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

final readonly class RouteAvailabilityInertiaData implements InertiaSharedDataContributor
{
    /**
     * @param  iterable<mixed>  $contributors
     */
    public function __construct(
        private iterable $contributors,
        private EffectivePermissionChecker $permissions,
        private ImpersonationSessionState $impersonation,
    ) {}

    public function key(): string
    {
        return 'shared.route-availability';
    }

    public function data(Request $request): array
    {
        return [
            'auth.availableAdminRoutes' => $this->availableRoutes($request, admin: true),
            'auth.availableApplicationRoutes' => $this->availableRoutes($request, admin: false),
        ];
    }

    /**
     * @return list<string>
     */
    private function availableRoutes(Request $request, bool $admin): array
    {
        if ($admin && $this->impersonation->active($request)) {
            return [];
        }

        $userPublicId = data_get($request->user(), 'public_id');
        $teamPublicId = $request->hasSession() ? $request->session()->get('active_team_public_id') : null;

        if (! is_string($userPublicId) || ! is_string($teamPublicId)) {
            return [];
        }

        $routes = [];
        $contributors = [];

        foreach ($this->contributors as $contributor) {
            if ($contributor instanceof InertiaRouteAvailabilityContributor) {
                $contributors[] = $contributor;
            }
        }

        usort(
            $contributors,
            static fn (InertiaRouteAvailabilityContributor $first, InertiaRouteAvailabilityContributor $second): int => $first->key() <=> $second->key(),
        );

        foreach ($contributors as $contributor) {
            foreach ($admin ? $contributor->adminRoutes($request) : $contributor->applicationRoutes($request) as $route) {
                if (! $this->routeCanBeOffered($route)) {
                    continue;
                }

                if ($this->permissions->check(new EffectivePermissionRequest($userPublicId, $route, $teamPublicId))->allowed) {
                    $routes[] = $route;
                }
            }
        }

        return array_values(array_unique($routes));
    }

    private function routeCanBeOffered(string $route): bool
    {
        return match ($route) {
            'admin.pulse.view' => Route::has('pulse'),
            'admin.telescope.view' => app()->environment(['local', 'development']) && Route::has('telescope'),
            default => true,
        };
    }
}
