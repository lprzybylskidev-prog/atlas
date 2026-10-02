<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Runtime;

use App\Modules\Optional\Chat\Application\TranscriptionProcess;
use App\Modules\Optional\Chat\Application\TranscriptionProcessor;
use App\Shared\Application\ManagedProcesses\Contracts\ManagedProcessHandler;

final readonly class TranscriptionProcessHandler implements ManagedProcessHandler
{
    public function __construct(private TranscriptionProcessor $processor) {}

    public function processKey(): string
    {
        return TranscriptionProcess::KEY;
    }

    public function handle(string $runPublicId): void
    {
        $this->processor->process($runPublicId);
    }
}
