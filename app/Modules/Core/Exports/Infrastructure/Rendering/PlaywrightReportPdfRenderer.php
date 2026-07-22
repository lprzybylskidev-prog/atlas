<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Infrastructure\Rendering;

use App\Modules\Core\Exports\Application\Contracts\ReportPdfRenderer;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

final readonly class PlaywrightReportPdfRenderer implements ReportPdfRenderer
{
    public function render(string $html): string
    {
        $directory = storage_path('app/private/report-render/'.bin2hex(random_bytes(8)));
        $htmlPath = $directory.'/report.html';
        $pdfPath = $directory.'/report.pdf';

        File::ensureDirectoryExists($directory, 0700, true);
        File::put($htmlPath, $html);

        $process = new Process(['node', base_path('tools/reports/render-pdf.mjs'), $htmlPath, $pdfPath], base_path());
        $process->setTimeout(60);
        $process->run();

        try {
            if (! $process->isSuccessful()) {
                throw new RuntimeException('Report PDF rendering failed.');
            }

            $contents = File::get($pdfPath);

            if ($contents === '') {
                throw new RuntimeException('Report PDF rendering produced an empty artifact.');
            }

            return $contents;
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
