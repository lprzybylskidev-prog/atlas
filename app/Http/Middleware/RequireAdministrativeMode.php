<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireAdministrativeMode
{
    public function __construct(
        private AdministrativeSessionManager $adminMode,
        private ImpersonationManager $impersonation,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->impersonation->active($request)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        if (! $request->hasSession() || ! $this->adminMode->active($request)) {
            if ($request->hasSession()) {
                $this->impersonation->stop($request, reason: 'admin_mode_expired');
                $this->adminMode->exit($request, 'expired');
                $request->session()->put(AdministrativeSessionManager::PENDING_REAUTHENTICATION, AdministrativeSessionManager::PENDING_ENTER);
            }

            if ($request->isMethod('GET')) {
                return redirect()->guest(route('password.confirm'));
            }

            if ($request->hasSession()) {
                $request->session()->put('url.intended', route('admin.system-status'));
            }

            return redirect()->route('password.confirm');
        }

        $response = $next($request);

        if ($request->is('admin*')) {
            $this->adminMode->touch($request);
        }

        return $response;
    }
}
