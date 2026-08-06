<?php

declare(strict_types=1);

namespace App\Modules\Core\Notifications\Application\Public\Contracts;

interface NotificationTypeDirectory
{
    /**
     * @return list<array{type: string, labelKey: string, descriptionKey: string, bodyPreviewKey: string, bodyPreviewParams: array<string, int|string>, permissionNames: list<string>}>
     */
    public function types(): array;
}
