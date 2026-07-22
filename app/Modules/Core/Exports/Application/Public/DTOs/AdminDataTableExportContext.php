<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application\Public\DTOs;

use App\Shared\Application\Tables\TableState;
use DateTimeImmutable;

final readonly class AdminDataTableExportContext
{
    /**
     * @param  array<string, int|string|bool|null>  $filters
     * @param  array<string, string|null>|null  $timeRange
     */
    public function __construct(
        public TableState $state,
        public int $requestingUserId,
        public string $requestingUserPublicId,
        public ?int $activeTeamId,
        public ?string $activeTeamPublicId,
        public array $filters = [],
        public ?array $timeRange = null,
        public ?int $estimatedRowCount = null,
        public DateTimeImmutable $expiresAt = new DateTimeImmutable('+1 day'),
        public bool $auditExport = false,
        public bool $allowSynchronous = true,
    ) {}
}
