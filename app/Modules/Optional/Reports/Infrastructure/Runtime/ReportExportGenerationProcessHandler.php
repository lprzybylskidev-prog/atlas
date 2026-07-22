<?php

declare(strict_types=1);

namespace App\Modules\Optional\Reports\Infrastructure\Runtime;

use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessHandler;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessReporter;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessRunInspector;
use App\Modules\Optional\Reports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Optional\Reports\Application\ReportExportGenerationProcess;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use stdClass;

final readonly class ReportExportGenerationProcessHandler implements ManagedProcessHandler
{
    public function __construct(
        private ManagedProcessRunInspector $runs,
        private ManagedProcessReporter $reporter,
        private ReportExportRequestStore $requests,
        private OperationalModuleGuard $modules,
        private ConnectionInterface $database,
    ) {}

    public function processKey(): string
    {
        return ReportExportGenerationProcess::KEY;
    }

    public function handle(string $runPublicId): void
    {
        $input = $this->runs->inputSnapshot($runPublicId);
        $requestPublicId = $this->requiredInputString($input, 'export_request_public_id');
        $request = $this->request($requestPublicId);
        $moduleKey = $this->requiredString($request, 'module_key');
        $teamPublicId = $this->nullableString($request, 'active_team_public_id');
        $userPublicId = $this->requiredString($request, 'requesting_user_public_id');

        $this->modules->ensureAllowed('reports', $teamPublicId, $userPublicId, 'reports.request');
        $this->modules->ensureAllowed($moduleKey, $teamPublicId, $userPublicId);

        $this->requests->markGenerating($requestPublicId);
        $this->reporter->running($runPublicId, 'snapshot_validated', 1, 3, 'Snapshot validated');
        $this->reporter->info($runPublicId, 'checkpoint', 'Report export request snapshot was validated.', 'snapshot_validated', [
            'export_request_public_id' => $requestPublicId,
            'report_key' => $this->requiredString($request, 'report_key'),
            'format' => $this->requiredString($request, 'format'),
            'module_key' => $moduleKey,
        ]);

        $message = 'Report export generator is not registered for this format yet.';
        $this->requests->markFailed($requestPublicId, $message);
        $this->reporter->info($runPublicId, 'checkpoint', $message, 'generator_missing', [
            'export_request_public_id' => $requestPublicId,
            'format' => $this->requiredString($request, 'format'),
        ]);

        throw new RuntimeException($message);
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

    private function request(string $publicId): stdClass
    {
        $request = $this->database->table(DatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $publicId)->first();

        if ($request instanceof stdClass) {
            return $request;
        }

        throw new RuntimeException('Report export request was not found.');
    }

    private function requiredString(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw new RuntimeException(sprintf('Report export request field [%s] must be a non-empty string.', $property));
    }

    private function nullableString(stdClass $row, string $property): ?string
    {
        $value = $row->{$property} ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
