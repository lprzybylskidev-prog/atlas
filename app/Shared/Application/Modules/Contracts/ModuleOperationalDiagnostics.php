<?php

declare(strict_types=1);

namespace App\Shared\Application\Modules\Contracts;

interface ModuleOperationalDiagnostics
{
    public function moduleKey(): string;

    /**
     * @return list<array{severity: string, label: string, description: string, value?: int|string|null}>
     */
    public function issues(): array;
}
