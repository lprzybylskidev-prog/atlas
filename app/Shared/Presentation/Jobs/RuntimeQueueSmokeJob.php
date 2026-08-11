<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

final class RuntimeQueueSmokeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly string $marker) {}

    public function handle(): void
    {
        Cache::put($this->marker, true, now()->addMinutes(5));
    }
}
