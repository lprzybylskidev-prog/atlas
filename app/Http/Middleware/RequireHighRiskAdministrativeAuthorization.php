<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Identity\Application\Admin\AdministrativeSessionManager;
use App\Modules\Core\Identity\Application\Admin\HighRiskAdministrativeOperation;
use App\Modules\Core\Privacy\Presentation\Http\PrivacyPreviewInput;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireHighRiskAdministrativeAuthorization
{
    private const RECOVERABLE_INPUT = 'atlas_high_risk_recoverable_input';

    public function __construct(
        private AdministrativeSessionManager $adminMode,
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
                $this->flashRecoverableInput($request);
                $this->storeRecoverableInput($request);
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
        if (! $this->isPrivacyPreviewRoute($request)) {
            return;
        }

        Validator::make($request->all(), PrivacyPreviewInput::rules(), [], PrivacyPreviewInput::attributes())->validate();
    }

    private function flashRecoverableInput(Request $request): void
    {
        if (! $this->isPrivacyPreviewRoute($request)) {
            return;
        }

        $request->flashOnly([
            'operation',
            'subject_type',
            'subject_identifier',
            'reason',
            'dry_run',
        ]);
    }

    private function storeRecoverableInput(Request $request): void
    {
        if (! $this->isPrivacyPreviewRoute($request) || ! $request->hasSession()) {
            return;
        }

        $request->session()->put(self::RECOVERABLE_INPUT, $request->only([
            'operation',
            'subject_type',
            'subject_identifier',
            'reason',
            'dry_run',
        ]));
    }

    private function isPrivacyPreviewRoute(Request $request): bool
    {
        return in_array($request->route()?->getName(), [
            'admin.privacy-retention.hard-delete.preview',
            'admin.privacy-retention.anonymization.preview',
        ], true);
    }
}
