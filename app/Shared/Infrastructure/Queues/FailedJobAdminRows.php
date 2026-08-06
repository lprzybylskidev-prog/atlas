<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Queues;

use App\Modules\Core\Identity\Application\Public\Persistence\IdentityDatabaseTable;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class FailedJobAdminRows
{
    /**
     * @return list<array{uuid: string, connection: string, queue: string, failedAt: string, displayName: string, jobClass: string, exceptionType: string, exceptionMessage: string, payload: string, exception: string, acknowledged: bool, handlingStatus: string, acknowledgedAt: string|null, acknowledgedBy: string|null}>
     */
    public function rows(int $limit = 200): array
    {
        return array_values(DB::table(DatabaseTable::FAILED_JOBS.' as failed_jobs')
            ->leftJoin(DatabaseTable::FAILED_JOB_ACKNOWLEDGEMENTS.' as acknowledgements', 'acknowledgements.failed_job_uuid', '=', 'failed_jobs.uuid')
            ->leftJoin(IdentityDatabaseTable::USERS.' as acknowledged_users', 'acknowledged_users.id', '=', 'acknowledgements.acknowledged_by_user_id')
            ->orderByDesc('failed_jobs.failed_at')
            ->limit($limit)
            ->get([
                'failed_jobs.id',
                'failed_jobs.uuid',
                'failed_jobs.connection',
                'failed_jobs.queue',
                'failed_jobs.payload',
                'failed_jobs.exception',
                'failed_jobs.failed_at',
                'acknowledgements.acknowledged_at',
                'acknowledged_users.name as acknowledged_by',
            ])
            ->map(fn (object $row): array => $this->jobRow($row))
            ->values()
            ->all());
    }

    /**
     * @return array{uuid: string, connection: string, queue: string, failedAt: string, displayName: string, jobClass: string, exceptionType: string, exceptionMessage: string, payload: string, exception: string, acknowledged: bool, handlingStatus: string, acknowledgedAt: string|null, acknowledgedBy: string|null}
     */
    public function jobRow(object $row): array
    {
        $payload = $this->scalarString($row->payload ?? '');
        $exception = $this->scalarString($row->exception ?? '');
        $payloadData = $this->jsonPayload($payload);
        $acknowledgedAt = $this->nullableScalarString($row->acknowledged_at ?? null);

        return [
            'uuid' => $this->scalarString($row->uuid ?? ''),
            'connection' => $this->scalarString($row->connection ?? ''),
            'queue' => $this->scalarString($row->queue ?? ''),
            'failedAt' => $this->scalarString($row->failed_at ?? ''),
            'displayName' => $this->displayName($payloadData),
            'jobClass' => $this->jobClass($payloadData),
            'exceptionType' => $this->exceptionType($exception),
            'exceptionMessage' => $this->exceptionMessage($exception),
            'payload' => $this->prettyJson($payload),
            'exception' => $exception,
            'acknowledged' => $acknowledgedAt !== null,
            'handlingStatus' => $acknowledgedAt === null ? 'needs_attention' : 'handled',
            'acknowledgedAt' => $acknowledgedAt,
            'acknowledgedBy' => $this->nullableScalarString($row->acknowledged_by ?? null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonPayload(string $payload): array
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }

        return is_array($decoded) ? $this->stringKeyedArray($decoded) : [];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function jobClass(array $payload): string
    {
        $commandName = data_get($payload, 'data.commandName');

        if (is_string($commandName) && $commandName !== '') {
            return $commandName;
        }

        return $this->displayName($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function displayName(array $payload): string
    {
        $value = $payload['displayName'] ?? $payload['job'] ?? null;

        return is_scalar($value) ? (string) $value : 'Unknown job';
    }

    private function exceptionType(string $exception): string
    {
        if (preg_match('/^([A-Za-z0-9_\\\\]+):/', $exception, $matches) === 1) {
            return $matches[1];
        }

        return 'Exception';
    }

    private function exceptionMessage(string $exception): string
    {
        $firstLine = strtok($exception, "\n");

        if (! is_string($firstLine) || trim($firstLine) === '') {
            return 'No exception message recorded.';
        }

        return mb_substr($firstLine, 0, 700);
    }

    private function prettyJson(string $payload): string
    {
        try {
            $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            $encoded = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            return $payload;
        }

        return is_string($encoded) ? $encoded : $payload;
    }

    private function scalarString(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    private function nullableScalarString(mixed $value): ?string
    {
        return is_scalar($value) && (string) $value !== '' ? (string) $value : null;
    }

    /**
     * @param  array<mixed>  $values
     * @return array<string, mixed>
     */
    private function stringKeyedArray(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
