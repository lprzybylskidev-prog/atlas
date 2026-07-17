<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Application\Admin\HighRiskAdministrativeOperation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireHighRiskAdministrativeAuthorization
{
    public function __construct(
        private AdministrativeSessionManager $adminMode,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $operation = null): Response
    {
        if (is_string($operation)) {
            $classified = HighRiskAdministrativeOperation::tryFrom($operation);

            abort_if($classified === null, 500, 'Unknown high-risk administrative operation.');

            $request->attributes->set('atlas_high_risk_operation', $classified->value);
        }

        if (! $this->adminMode->highRiskFresh($request)) {
            if ($request->hasSession()) {
                $request->session()->put(AdministrativeSessionManager::PENDING_REAUTHENTICATION, AdministrativeSessionManager::PENDING_HIGH_RISK);
                $request->session()->put('url.intended', route('admin.system-status'));
            }

            return redirect()->route('password.confirm');
        }

        return $next($request);
    }
}
