<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Application\Admin\HighRiskAdministrativeOperation;
use App\Shared\Presentation\Http\Contracts\HighRiskReauthenticationContinuation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireHighRiskAdministrativeAuthorization
{
    public function __construct(
        private AdministrativeSessionManager $adminMode,
        /** @var iterable<mixed> */
        private iterable $continuations = [],
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, ?string $operation = null): Response
    {
        $this->validateBeforeReauthentication($request);

        if (is_string($operation)) {
            $classified = HighRiskAdministrativeOperation::tryFrom($operation);

            abort_if($classified === null, 500, 'Unknown high-risk administrative operation.');

            $request->attributes->set('atlas_high_risk_operation', $classified->value);
        }

        if (! $this->adminMode->highRiskFresh($request)) {
            if ($request->hasSession()) {
                $request->session()->put(AdministrativeSessionManager::PENDING_REAUTHENTICATION, AdministrativeSessionManager::PENDING_HIGH_RISK);
                $request->session()->put('url.intended', $this->intendedUrl($request));
                $this->preserveRecoverableInput($request);
            }

            return redirect()->route('password.confirm');
        }

        return $next($request);
    }

    private function intendedUrl(Request $request): string
    {
        $url = $request->isMethod('GET') ? $request->fullUrl() : $request->headers->get('referer');

        if (is_string($url) && str_starts_with($url, url('/'))) {
            return $url;
        }

        return route('admin.system-status');
    }

    private function validateBeforeReauthentication(Request $request): void
    {
        foreach ($this->continuations() as $continuation) {
            if ($continuation->supports($request)) {
                $continuation->validate($request);
            }
        }
    }

    private function preserveRecoverableInput(Request $request): void
    {
        foreach ($this->continuations() as $continuation) {
            if ($continuation->supports($request)) {
                $continuation->preserve($request);
            }
        }
    }

    /**
     * @return list<HighRiskReauthenticationContinuation>
     */
    private function continuations(): array
    {
        $continuations = [];

        foreach ($this->continuations as $continuation) {
            if ($continuation instanceof HighRiskReauthenticationContinuation) {
                $continuations[] = $continuation;
            }
        }

        return $continuations;
    }
}
