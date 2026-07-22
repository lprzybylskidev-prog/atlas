<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Infrastructure\Runtime;

use App\Modules\Core\Exports\Application\ReportExportArtifactGenerator;
use App\Modules\Core\Exports\Application\ReportExportGenerationProcess;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessHandler;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunInspector;
use RuntimeException;

final readonly class ReportExportGenerationProcessHandler implements ManagedProcessHandler
{
    public function __construct(
        private ManagedProcessRunInspector $runs,
        private ReportExportArtifactGenerator $generator,
    ) {}

    public function processKey(): string
    {
        return ReportExportGenerationProcess::KEY;
    }

    public function handle(string $runPublicId): void
    {
        $input = $this->runs->inputSnapshot($runPublicId);
        $this->generator->generate(
            requestPublicId: $this->requiredInputString($input, 'export_request_public_id'),
            runPublicId: $runPublicId,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function requiredInputString(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(sprintf('Report export process input [%s] must be a non-empty string.', $key));
        }

        return trim($value);
    }
}
