<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Contracts\ReportExportRequestStore;
use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Modules\Core\Files\Application\Public\Contracts\FileStorage;
use App\Modules\Core\Notifications\Application\Public\Contracts\NotificationPublisher;
use App\Modules\Core\Notifications\Application\Public\DTOs\CreateNotification;
use App\Modules\Optional\ManagedProcesses\Application\Public\Contracts\ManagedProcessReporter;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;
use stdClass;
use Throwable;

final readonly class ReportExportArtifactGenerator
{
    public function __construct(
        private ReportExportRequestStore $requests,
        private OperationalModuleGuard $modules,
        private ConnectionInterface $database,
        private ReportExportGeneratorRegistry $generators,
        private FileStorage $files,
        private NotificationPublisher $notifications,
        private ManagedProcessReporter $reporter,
    ) {}

    public function generate(string $requestPublicId, ?string $runPublicId = null): string
    {
        $existingArtifactPublicId = $this->requests->availableArtifactPublicId($requestPublicId);

        if ($existingArtifactPublicId !== null) {
            $this->info($runPublicId, 'checkpoint', 'Report export artifact was already available.', 'completed', [
                'export_request_public_id' => $requestPublicId,
                'artifact_public_id' => $existingArtifactPublicId,
            ]);
            $this->succeeded($runPublicId, 'completed', 4, 4, 'Report export artifact already available', ['artifacts' => 1], [
                'export_request_public_id' => $requestPublicId,
                'artifact_public_id' => $existingArtifactPublicId,
                'deduplicated' => true,
            ]);

            return $existingArtifactPublicId;
        }

        $request = $this->request($requestPublicId);
        $moduleKey = $this->requiredString($request, 'module_key');
        $teamPublicId = $this->nullableString($request, 'active_team_public_id');
        $userPublicId = $this->requiredString($request, 'requesting_user_public_id');

        $this->modules->ensureAllowed('exports', $teamPublicId, $userPublicId, ReportsPermissionCatalog::REQUEST);
        $this->modules->ensureAllowed($moduleKey, $teamPublicId, $userPublicId);
        $this->ensureAuditExportAllowed($request, $teamPublicId, $userPublicId);

        $this->requests->markGenerating($requestPublicId);
        $this->running($runPublicId, 'snapshot_validated', 1, 4, 'Snapshot validated');
        $this->info($runPublicId, 'checkpoint', 'Report export request snapshot was validated.', 'snapshot_validated', [
            'export_request_public_id' => $requestPublicId,
            'report_key' => $this->requiredString($request, 'report_key'),
            'format' => $this->requiredString($request, 'format'),
            'module_key' => $moduleKey,
        ]);

        try {
            $generationRequest = $this->generationRequest($request);
            $generator = $this->generators->get($generationRequest->format);
            $this->running($runPublicId, 'generating', 2, 4, 'Generating report export');
            $artifact = $generator->generate($generationRequest);
            $this->running($runPublicId, 'storing', 3, 4, 'Storing generated artifact');
            $file = $this->files->storeGenerated(
                filename: $artifact->filename,
                mimeType: $artifact->contentType,
                contents: $artifact->contents,
                actorId: $this->nullableInt($request->requested_by_user_id ?? null),
                teamId: $this->nullableInt($request->team_id ?? null),
                metadata: [
                    'module_key' => $moduleKey,
                    'report_key' => $generationRequest->reportKey,
                    'export_request_public_id' => $requestPublicId,
                    'format' => $generationRequest->format->value,
                ],
            );

            $artifactPublicId = $this->requests->publishArtifact($requestPublicId, $file, $artifact->filename, $artifact->contentType);
            $this->publishReadyNotification($runPublicId, $generationRequest, $artifactPublicId);
            $this->succeeded($runPublicId, 'completed', 4, 4, 'Report export generated', ['artifacts' => 1], [
                'export_request_public_id' => $requestPublicId,
                'artifact_public_id' => $artifactPublicId,
                'format' => $generationRequest->format->value,
                'size_bytes' => $file->sizeBytes,
            ]);

            return $artifactPublicId;
        } catch (Throwable $exception) {
            $message = mb_substr($exception->getMessage(), 0, 2000);
            $this->requests->markFailed($requestPublicId, $message);
            $this->publishFailedNotification($request, $message);
            $this->info($runPublicId, 'checkpoint', $message, 'generation_failed', [
                'export_request_public_id' => $requestPublicId,
                'format' => $this->requiredString($request, 'format'),
            ]);

            throw $exception;
        }
    }

    /**
     * @param  array<string, scalar|null>  $safeContext
     */
    private function info(?string $runPublicId, string $eventType, string $message, ?string $stage = null, array $safeContext = []): void
    {
        if ($runPublicId !== null) {
            $this->reporter->info($runPublicId, $eventType, $message, $stage, $safeContext);
        }
    }

    /**
     * @param  array<string, int>|null  $counters
     */
    private function running(?string $runPublicId, ?string $stage, ?int $current, ?int $total, ?string $label, ?array $counters = null): void
    {
        if ($runPublicId !== null) {
            $this->reporter->running($runPublicId, $stage, $current, $total, $label, $counters);
        }
    }

    /**
     * @param  array<string, int>|null  $counters
     * @param  array<string, scalar|null>|null  $resultSummary
     */
    private function succeeded(?string $runPublicId, ?string $stage, ?int $current, ?int $total, ?string $label, ?array $counters = null, ?array $resultSummary = null): void
    {
        if ($runPublicId !== null) {
            $this->reporter->succeeded($runPublicId, $stage, $current, $total, $label, $counters, $resultSummary);
        }
    }

    private function publishReadyNotification(?string $runPublicId, ReportExportGenerationRequest $generationRequest, string $artifactPublicId): void
    {
        try {
            $this->notifications->publish(new CreateNotification(
                type: 'report_export.available',
                title: 'Report export is ready',
                body: sprintf('%s export is ready to download.', $generationRequest->reportName),
                recipientUserPublicId: $generationRequest->requestingUserPublicId,
                teamPublicId: $generationRequest->activeTeamPublicId,
                severity: 'success',
                deepLinkUrl: '/exports/'.$artifactPublicId.'/download',
                data: [
                    'export_request_public_id' => $generationRequest->publicId,
                    'artifact_public_id' => $artifactPublicId,
                    'report_key' => $generationRequest->reportKey,
                    'format' => $generationRequest->format->value,
                ],
            ));
        } catch (Throwable $exception) {
            $this->info(
                $runPublicId,
                'notification_failed',
                'Report export was generated, but the ready notification could not be published.',
                'notification',
                ['message' => mb_substr($exception->getMessage(), 0, 500)],
            );
        }
    }

    private function publishFailedNotification(stdClass $request, string $message): void
    {
        try {
            $this->notifications->publish(new CreateNotification(
                type: 'report_export.failed',
                title: 'Report export failed',
                body: mb_substr($message, 0, 500),
                recipientUserPublicId: $this->requiredString($request, 'requesting_user_public_id'),
                teamPublicId: $this->nullableString($request, 'active_team_public_id'),
                severity: 'warning',
                deepLinkUrl: null,
                data: [
                    'export_request_public_id' => $this->requiredString($request, 'public_id'),
                    'report_key' => $this->requiredString($request, 'report_key'),
                    'format' => $this->requiredString($request, 'format'),
                ],
            ));
        } catch (Throwable) {
        }
    }

    private function ensureAuditExportAllowed(stdClass $request, ?string $teamPublicId, string $userPublicId): void
    {
        if ((bool) ($request->audit_export ?? false)) {
            $this->modules->ensureAllowed('exports', $teamPublicId, $userPublicId, ReportsPermissionCatalog::AUDIT_EXPORT);
        }
    }

    private function request(string $publicId): stdClass
    {
        $request = $this->database->table(DatabaseTable::REPORT_EXPORT_REQUESTS)->where('public_id', $publicId)->first();

        if ($request instanceof stdClass) {
            return $request;
        }

        throw new RuntimeException('Report export request was not found.');
    }

    private function generationRequest(stdClass $request): ReportExportGenerationRequest
    {
        return new ReportExportGenerationRequest(
            publicId: $this->requiredString($request, 'public_id'),
            reportKey: $this->requiredString($request, 'report_key'),
            reportName: $this->requiredString($request, 'report_name'),
            moduleKey: $this->requiredString($request, 'module_key'),
            format: ReportExportFormat::from($this->requiredString($request, 'format')),
            activeTeamPublicId: $this->nullableString($request, 'active_team_public_id'),
            requestingUserPublicId: $this->requiredString($request, 'requesting_user_public_id'),
            filters: $this->jsonObject($request, 'filters'),
            sorting: $this->sorting($request),
            visibleColumns: $this->stringList($request, 'visible_columns'),
            columnOrder: $this->stringList($request, 'column_order'),
            allowedColumns: $this->authorizationAllowedColumns($request),
            timeRange: $this->nullableStringMap($request, 'time_range'),
            releaseVersion: $this->requiredString($request, 'release_version'),
            ruleVersion: $this->requiredString($request, 'rule_version'),
            expiresAt: $this->dateTime($request, 'expires_at'),
        );
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

    /**
     * @return array<mixed, mixed>
     */
    private function jsonArray(stdClass $row, string $property): array
    {
        $value = $row->{$property} ?? null;
        $decoded = is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : null;

        if (is_array($decoded)) {
            return $decoded;
        }

        throw new RuntimeException(sprintf('Report export request field [%s] must be a JSON object or array.', $property));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(stdClass $row, string $property): array
    {
        $decoded = $this->jsonArray($row, $property);
        $object = [];

        foreach ($decoded as $key => $value) {
            if (is_string($key)) {
                $object[$key] = $value;
            }
        }

        return $object;
    }

    /**
     * @return list<array{id: string, desc: bool}>
     */
    private function sorting(stdClass $row): array
    {
        $decoded = $this->jsonArray($row, 'sorting');
        $sorting = [];

        foreach ($decoded as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }

            $sorting[] = [
                'id' => $item['id'],
                'desc' => (bool) ($item['desc'] ?? false),
            ];
        }

        return $sorting;
    }

    /**
     * @return list<string>
     */
    private function stringList(stdClass $row, string $property): array
    {
        $values = [];

        foreach ($this->jsonArray($row, $property) as $value) {
            if (is_string($value) && $value !== '') {
                $values[] = $value;
            }
        }

        return $values;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function nullableStringMap(stdClass $row, string $property): ?array
    {
        $value = $row->{$property} ?? null;

        if ($value === null) {
            return null;
        }

        $decoded = $this->jsonArray($row, $property);
        $map = [];

        foreach ($decoded as $key => $item) {
            if (is_string($key) && (is_string($item) || $item === null)) {
                $map[$key] = $item;
            }
        }

        return $map;
    }

    private function nullableInt(mixed $value): ?int
    {
        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @return list<string>
     */
    private function authorizationAllowedColumns(stdClass $row): array
    {
        $authorization = $this->jsonObject($row, 'authorization_snapshot');
        $allowedColumns = $authorization['allowed_columns'] ?? [];

        if (! is_array($allowedColumns)) {
            return [];
        }

        return array_values(array_filter(
            $allowedColumns,
            static fn (mixed $value): bool => is_string($value) && $value !== '',
        ));
    }

    private function dateTime(stdClass $row, string $property): DateTimeImmutable
    {
        $value = $row->{$property} ?? null;

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        if (is_string($value) && $value !== '') {
            return new DateTimeImmutable($value, new DateTimeZone('UTC'));
        }

        throw new RuntimeException(sprintf('Report export request field [%s] must be a date-time.', $property));
    }
}
