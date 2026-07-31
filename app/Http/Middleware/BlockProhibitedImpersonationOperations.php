<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class BlockProhibitedImpersonationOperations
{
    private const BLOCKED_ROUTES = [
        'password.update',
        'profile.update',
        'two-factor.enable',
        'two-factor.confirm',
        'two-factor.disable',
        'two-factor.regenerate-recovery-codes',
        'admin.users.update',
        'admin.users.activate',
        'admin.users.deactivate',
        'admin.users.verify-email',
        'admin.users.require-email-verification',
        'admin.users.resend-first-password',
        'admin.users.unlock',
        'admin.users.reset-mfa',
        'admin.users.invalidate-sessions',
        'admin.users.teams.store',
        'admin.users.teams.destroy',
        'admin.users.teams.authorization.update',
        'admin.teams.store',
        'admin.teams.update',
        'admin.teams.activate',
        'admin.teams.deactivate',
        'admin.teams.destroy',
        'admin.teams.users.store',
        'admin.teams.users.destroy',
        'admin.teams.users.authorization.update',
        'admin.managers.store',
        'admin.managers.end',
        'admin.managers.head.update',
        'admin.authorization.roles.store',
        'admin.authorization.roles.update',
        'admin.authorization.roles.destroy',
        'impersonation.start',
    ];

    public function __construct(
        private ImpersonationManager $impersonation,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName();

        if ($this->impersonation->active($request) && is_string($routeName) && in_array($routeName, self::BLOCKED_ROUTES, true)) {
            abort(Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
