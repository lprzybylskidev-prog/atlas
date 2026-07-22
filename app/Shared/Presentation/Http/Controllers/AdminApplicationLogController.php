<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Http\Controllers;

use App\Shared\Infrastructure\Observability\ApplicationLogReader;
use App\Shared\Presentation\Support\AdminDataTableExportMeta;
use Inertia\Inertia;
use Inertia\Response;

final readonly class AdminApplicationLogController
{
    public function __construct(
        private ApplicationLogReader $reader,
    ) {}

    public function __invoke(): Response
    {
        $log = $this->reader->latest();

        return Inertia::render('Admin/Logs/Index', [
            'logs' => $log['entries'],
            'summary' => $log['summary'],
            'exports' => AdminDataTableExportMeta::defaults(),
        ]);
    }
}
