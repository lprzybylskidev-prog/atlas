<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ApplyImpersonationContext
{
    public function __construct(
        private ImpersonationManager $impersonation,
    ) {}

    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('admin*') && ! $request->is('impersonation')) {
            $this->impersonation->applyEffectiveUser($request);
        }

        return $next($request);
    }
}
