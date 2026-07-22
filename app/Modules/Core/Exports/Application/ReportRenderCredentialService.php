<?php

declare(strict_types=1);

namespace App\Modules\Core\Exports\Application;

use App\Modules\Core\Exports\Application\Enums\ReportExportFormat;
use App\Modules\Core\Exports\Application\Exceptions\ReportRenderCredentialInvalid;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialAccess;
use App\Modules\Core\Exports\Application\Public\Contracts\ReportRenderCredentialIssuer;
use App\Modules\Core\Exports\Application\Public\DTOs\IssuedReportRenderCredential;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\DTOs\ResolvedReportRenderCredential;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Infrastructure\Database\DatabaseTable;
use App\Shared\Infrastructure\Operations\OperationalModuleGuard;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;
use stdClass;

final readonly class ReportRenderCredentialService implements ReportRenderCredentialAccess, ReportRenderCredentialIssuer
{
    public function __construct(
        private ConnectionInterface $database,
        private OperationalModuleGuard $modules,
    ) {}

    public function issue(string $exportRequestPublicId): IssuedReportRenderCredential
    {
        $request = $this->request($exportRequestPublicId);
        $token = bin2hex(random_bytes(32));
        $publicId = (string) Str::ulid();
        $expiresAt = $this->credentialExpiry($this->dateTime($request, 'expires_at'));

        $this->database->table(DatabaseTable::REPORT_RENDER_CREDENTIALS)->insert([
            'public_id' => $publicId,
            'export_request_id' => $this->intValue($request->id ?? null),
            'token_hash' => hash('sha256', $token),
            'requested_by_user_id' => $this->nullableInt($request->requested_by_user_id ?? null),
            'team_id' => $this->nullableInt($request->team_id ?? null),
            'module_key' => $this->requiredString($request, 'module_key'),
            'report_key' => $this->requiredString($request, 'report_key'),
            'allowed_dataset' => json_encode([
                'report_key' => $this->requiredString($request, 'report_key'),
                'filters' => $this->jsonObject($request, 'filters'),
                'sorting' => $this->jsonArray($request, 'sorting'),
                'time_range' => $this->nullableJsonArray($request, 'time_range'),
            ], JSON_THROW_ON_ERROR),
            'allowed_columns' => json_encode($this->authorizationAllowedColumns($request), JSON_THROW_ON_ERROR),
            'expires_at' => $expiresAt,
            'consumed_at' => null,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return new IssuedReportRenderCredential(
            publicId: $publicId,
            token: $token,
            exportRequestPublicId: $exportRequestPublicId,
            expiresAt: $expiresAt,
        );
    }

    public function resolve(string $token): ResolvedReportRenderCredential
    {
        $credential = $this->database->table(DatabaseTable::REPORT_RENDER_CREDENTIALS.' as credentials')
            ->join(DatabaseTable::REPORT_EXPORT_REQUESTS.' as requests', 'credentials.export_request_id', '=', 'requests.id')
            ->where('credentials.token_hash', hash('sha256', $token))
            ->whereNull('credentials.consumed_at')
            ->where('credentials.expires_at', '>', now('UTC'))
            ->first([
                'credentials.public_id as credential_public_id',
                'requests.public_id',
                'requests.report_key',
                'requests.report_name',
                'requests.module_key',
                'requests.audit_export',
                'requests.format',
                'requests.active_team_public_id',
                'requests.requesting_user_public_id',
                'requests.filters',
                'requests.sorting',
                'requests.visible_columns',
                'requests.column_order',
                'requests.time_range',
                'requests.authorization_snapshot',
                'requests.release_version',
                'requests.rule_version',
                'requests.expires_at',
            ]);

        if (! $credential instanceof stdClass) {
            throw ReportRenderCredentialInvalid::blocked();
        }

        $teamPublicId = $this->nullableString($credential, 'active_team_public_id');
        $userPublicId = $this->requiredString($credential, 'requesting_user_public_id');
        $moduleKey = $this->requiredString($credential, 'module_key');

        $this->modules->ensureAllowed('exports', $teamPublicId, $userPublicId, ReportsPermissionCatalog::REQUEST);
        $this->modules->ensureAllowed($moduleKey, $teamPublicId, $userPublicId);
        if ((bool) ($credential->audit_export ?? false)) {
            $this->modules->ensureAllowed('exports', $teamPublicId, $userPublicId, ReportsPermissionCatalog::AUDIT_EXPORT);
        }

        return new ResolvedReportRenderCredential(
            publicId: $this->requiredString($credential, 'credential_public_id'),
            request: $this->generationRequest($credential),
        );
    }

    public function consume(string $credentialPublicId): void
    {
        $updated = $this->database->table(DatabaseTable::REPORT_RENDER_CREDENTIALS)
            ->where('public_id', $credentialPublicId)
            ->whereNull('consumed_at')
            ->update([
                'consumed_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);

        if ($updated !== 1) {
            throw ReportRenderCredentialInvalid::blocked();
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

    private function credentialExpiry(DateTimeImmutable $requestExpiry): DateTimeImmutable
    {
        $ttl = max(60, Config::integer('atlas.exports.render_token_ttl_seconds', 300));
        $ttlExpiry = new DateTimeImmutable(sprintf('+%d seconds', $ttl), new DateTimeZone('UTC'));

        return $requestExpiry < $ttlExpiry ? $requestExpiry : $ttlExpiry;
    }

    private function requiredString(stdClass $row, string $property): string
    {
        $value = $row->{$property} ?? null;

        if (is_string($value) && $value !== '') {
            return $value;
        }

        throw new RuntimeException(sprintf('Report render credential field [%s] must be a non-empty string.', $property));
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

        throw new RuntimeException(sprintf('Report render credential field [%s] must be JSON.', $property));
    }

    /**
     * @return array<string, mixed>
     */
    private function jsonObject(stdClass $row, string $property): array
    {
        $object = [];

        foreach ($this->jsonArray($row, $property) as $key => $value) {
            if (is_string($key)) {
                $object[$key] = $value;
            }
        }

        return $object;
    }

    /**
     * @return array<mixed, mixed>|null
     */
    private function nullableJsonArray(stdClass $row, string $property): ?array
    {
        return ($row->{$property} ?? null) === null ? null : $this->jsonArray($row, $property);
    }

    /**
     * @return list<array{id: string, desc: bool}>
     */
    private function sorting(stdClass $row): array
    {
        $sorting = [];

        foreach ($this->jsonArray($row, 'sorting') as $item) {
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
        if (($row->{$property} ?? null) === null) {
            return null;
        }

        $map = [];

        foreach ($this->jsonArray($row, $property) as $key => $value) {
            if (is_string($key) && (is_string($value) || $value === null)) {
                $map[$key] = $value;
            }
        }

        return $map;
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

        throw new RuntimeException(sprintf('Report render credential field [%s] must be a date-time.', $property));
    }

    private function intValue(mixed $value): int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        throw new RuntimeException('Expected numeric report render credential identifier.');
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
}
