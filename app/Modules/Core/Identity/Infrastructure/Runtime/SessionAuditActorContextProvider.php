<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Infrastructure\Runtime;

use App\Modules\Core\Identity\Application\Admin\ImpersonationManager;
use App\Shared\Application\Audit\Contracts\AuditActorContextProvider;
use App\Shared\Application\Audit\DTOs\AuditActorContext;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Context;

final readonly class SessionAuditActorContextProvider implements AuditActorContextProvider
{
    public function __construct(
        private Application $app,
    ) {}

    public function current(): AuditActorContext
    {
        if (! $this->app->bound('request')) {
            return new AuditActorContext(
                actorPublicId: $this->contextString('actor_public_id'),
                correlationId: $this->contextString('correlation_id'),
            );
        }

        $request = $this->app->make('request');

        if (! $request->hasSession()) {
            return new AuditActorContext(
                actorPublicId: $this->contextString('actor_public_id'),
                correlationId: $this->contextString('correlation_id'),
            );
        }

        $session = $request->session();

        return new AuditActorContext(
            actorPublicId: $this->contextString('actor_public_id'),
            actualActorPublicId: $this->sessionString($session->get(ImpersonationManager::ACTOR_PUBLIC_ID)),
            impersonatedUserPublicId: $this->sessionString($session->get(ImpersonationManager::USER_PUBLIC_ID)),
            impersonationSessionId: $this->sessionString($session->get(ImpersonationManager::SESSION_ID)),
            correlationId: $this->contextString('correlation_id'),
        );
    }

    private function sessionString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    private function contextString(string $key): ?string
    {
        $value = Context::get($key);

        return is_string($value) && $value !== '' ? $value : null;
    }
}
