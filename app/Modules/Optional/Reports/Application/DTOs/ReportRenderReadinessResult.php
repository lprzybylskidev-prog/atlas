<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Application\DTOs;

final readonly class ReportRenderReadinessResult
{
    public function __construct(
        public string $reportKey,
        public bool $ready,
        public ?string $safeSummary = null,
    ) {}

    public static function ready(string $reportKey): self
    {
        return new self($reportKey, true);
    }

    public static function notReady(string $reportKey, string $safeSummary): self
    {
        return new self($reportKey, false, $safeSummary);
    }
}
