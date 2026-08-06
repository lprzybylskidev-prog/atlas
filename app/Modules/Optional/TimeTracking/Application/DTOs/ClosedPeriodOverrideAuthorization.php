<?php

declare(strict_types=1);

namespace App\Modules\Optional\TimeTracking\Application\DTOs;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class ClosedPeriodOverrideAuthorization
{
    public function __construct(
        public string $actorScope,
        public bool $adminModeConfirmed,
        public bool $highRiskReauthenticated,
        public bool $mfaConfirmed,
        public bool $beforeAfterPreviewConfirmed,
        public string $reason,
        public DateTimeImmutable $authorizedAt,
    ) {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Closed-period override reason cannot be empty.');
        }
    }
}
