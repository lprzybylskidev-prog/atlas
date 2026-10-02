<?php

declare(strict_types=1);

namespace App\Modules\Optional\Chat\Infrastructure\Runtime;

use App\Modules\Optional\Chat\Application\TranscriptionProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class ContinueTranscriptionJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public readonly string $runPublicId) {}

    public function handle(TranscriptionProcessor $processor): void
    {
        $processor->process($this->runPublicId);
    }
}
