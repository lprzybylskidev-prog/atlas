<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\Contracts;

use App\Modules\Core\Notifications\Application\Public\DTOs\PublishRealtimeEvent;

interface RealtimePublisher
{
    public function publishRealtime(PublishRealtimeEvent $event): string;

    public function publishSessionInvalidated(string $userPublicId, ?string $teamPublicId = null, ?string $sessionId = null): string;

    public function publishSystemAlert(string $title, string $severity = 'info', ?string $body = null, ?string $userPublicId = null, ?string $teamPublicId = null): string;

    public function publishOperationProgress(
        string $operationType,
        string $operationId,
        string $status,
        int $progressPercent,
        ?string $userPublicId = null,
        ?string $teamPublicId = null,
        ?string $message = null,
    ): string;
}
