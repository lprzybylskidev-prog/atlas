<?php

declare(strict_types=1);

namespace App\Modules\Core\Identity\Application\Public\Contracts;

interface ImpersonationSimulationRecorder
{
    public function put(string $impersonationSessionId, string $key, mixed $value): void;
}
