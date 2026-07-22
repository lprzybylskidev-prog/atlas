<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Shared\Presentation\Support\FlashMessage;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class RequireImpersonationExternalEffectAcknowledgement
{
    public function __construct(
        private ImpersonationManager $impersonation,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->impersonation->active($request)) {
            return $next($request);
        }

        if ($request->boolean('impersonation_external_effect_acknowledged')) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'This operation has external effects while impersonation is active and requires explicit acknowledgement.',
            ], 409);
        }

        return redirect()->back(303)->with('flash.messages', [
            FlashMessage::error('flash.auth.external_effect_ack_required'),
        ]);
    }
}
