<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Exports;

use App\Modules\Core\Audit\Infrastructure\Persistence\TableNames\AuditDatabaseTable;
use App\Shared\Application\Exports\AbstractAdminDataTableExportProvider;
use App\Shared\Application\Exports\DTOs\AdminDataTableExportContext;
use App\Shared\Application\Exports\DTOs\ReportExportGenerationRequest;
use App\Shared\Application\Exports\ExportPermissions;
use App\Shared\Application\Tables\RegisteredTables;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final readonly class AdminAuditEventsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return RegisteredTables::AUDIT;
    }

    public function tableName(): string
    {
        return 'Audit events';
    }

    public function owningModuleKey(): string
    {
        return 'audit';
    }

    public function requestPermission(): string
    {
        return ExportPermissions::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-audit-events-export-v2';
    }

    public function supportsDetailedAuditExport(): bool
    {
        return true;
    }

    public function allowedExportColumns(AdminDataTableExportContext $context): array
    {
        $columns = parent::allowedExportColumns($context);

        return $context->auditExport
            ? $columns
            : array_values(array_filter($columns, static fn (string $column): bool => $column !== 'metadata'));
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'id' => 'Internal ID',
            'occurredAt' => 'Occurred at',
            'module' => 'Module',
            'action' => 'Action',
            'result' => 'Result',
            'source' => 'Source',
            'actorPublicId' => 'Actor',
            'actualActorPublicId' => 'Actual actor',
            'impersonatedUserPublicId' => 'Impersonated user',
            'impersonationSessionId' => 'Impersonation session',
            'targetType' => 'Target type',
            'targetPublicId' => 'Target',
            'aggregateType' => 'Aggregate type',
            'aggregatePublicId' => 'Aggregate',
            'teamPublicId' => 'Team',
            'correlationId' => 'Correlation ID',
            'reason' => 'Reason',
            'security' => 'Security',
            'metadata' => 'Metadata keys',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $query = DB::table(AuditDatabaseTable::AUDIT_EVENTS)->orderByDesc('occurred_at');
        $this->applyFilters($query, $request);

        $this->applySearchAndSorting($query, $request);

        $query->orderByDesc('id');
        foreach ($query->cursor() as $record) {
            yield $this->row($record, $request);
        }
    }

    private function applySearchAndSorting(Builder $query, ReportExportGenerationRequest $request): void
    {
        $search = trim(self::filterValue($request, 'search'));
        if ($search !== '' && $search !== 'all') {
            $query->where(function (Builder $nested) use ($search): void {
                foreach (['public_id', 'module', 'action', 'result', 'source', 'actor_public_id', 'target_public_id', 'team_public_id', 'correlation_id', 'reason'] as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $nested->{$method}($column, 'ilike', '%'.$search.'%');
                }
            });
        }
    }

    private function applyFilters(Builder $query, ReportExportGenerationRequest $request): void
    {
        $this->whereLike($query, 'actor_public_id', self::filterValue($request, 'actor'));
        $this->whereLike($query, 'actual_actor_public_id', self::filterValue($request, 'actual_actor'));
        $this->whereLike($query, 'impersonated_user_public_id', self::filterValue($request, 'impersonated_user'));
        $this->whereLike($query, 'impersonation_session_id', self::filterValue($request, 'impersonation_session'));
        $this->whereLike($query, 'target_public_id', self::filterValue($request, 'target'));
        $this->whereLike($query, 'correlation_id', self::filterValue($request, 'correlation'));
        $this->whereExact($query, 'target_type', self::filterValue($request, 'target_type'));
        $this->whereExact($query, 'action', self::filterValue($request, 'action'));
        $this->whereExact($query, 'team_public_id', self::filterValue($request, 'team'));
        $this->whereExact($query, 'module', self::filterValue($request, 'module'));
        $this->whereExact($query, 'source', self::filterValue($request, 'source'));

        if (in_array(self::filterValue($request, 'result'), ['succeeded', 'rejected', 'failed'], true)) {
            $query->where('result', self::filterValue($request, 'result'));
        }

        if (self::filterValue($request, 'security') === 'yes') {
            $query->where('is_security', true);
        } elseif (self::filterValue($request, 'security') === 'no') {
            $query->where('is_security', false);
        }

        if ($this->isDate(self::filterValue($request, 'date_from'))) {
            $query->whereDate('occurred_at', '>=', self::filterValue($request, 'date_from'));
        }

        if ($this->isDate(self::filterValue($request, 'date_to'))) {
            $query->whereDate('occurred_at', '<=', self::filterValue($request, 'date_to'));
        }
    }

    private function whereExact(Builder $query, string $column, string $value): void
    {
        if ($value === '' || $value === 'all') {
            return;
        }

        $query->where($column, $value);
    }

    private function whereLike(Builder $query, string $column, string $value): void
    {
        if ($value === '' || $value === 'all') {
            return;
        }

        $query->where($column, 'ilike', '%'.$value.'%');
    }

    private function isDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1;
    }

    /**
     * @return array<string, scalar|\Stringable|null>
     */
    private function row(object $record, ReportExportGenerationRequest $request): array
    {
        $values = get_object_vars($record);

        return [
            'id' => is_numeric($values['id'] ?? null) ? (int) $values['id'] : null,
            'publicId' => self::stringValue($values['public_id'] ?? ''),
            'occurredAt' => self::stringValue($values['occurred_at'] ?? ''),
            'module' => self::stringValue($values['module'] ?? ''),
            'action' => self::stringValue($values['action'] ?? ''),
            'result' => self::stringValue($values['result'] ?? ''),
            'source' => self::stringValue($values['source'] ?? ''),
            'actorPublicId' => self::stringValue($values['actor_public_id'] ?? ''),
            'actualActorPublicId' => self::stringValue($values['actual_actor_public_id'] ?? ''),
            'impersonatedUserPublicId' => self::stringValue($values['impersonated_user_public_id'] ?? ''),
            'impersonationSessionId' => self::stringValue($values['impersonation_session_id'] ?? ''),
            'targetType' => self::stringValue($values['target_type'] ?? ''),
            'targetPublicId' => self::stringValue($values['target_public_id'] ?? ''),
            'aggregateType' => self::stringValue($values['aggregate_type'] ?? ''),
            'aggregatePublicId' => self::stringValue($values['aggregate_public_id'] ?? ''),
            'teamPublicId' => self::stringValue($values['team_public_id'] ?? ''),
            'correlationId' => self::stringValue($values['correlation_id'] ?? ''),
            'reason' => self::stringValue($values['reason'] ?? ''),
            'security' => (bool) ($values['is_security'] ?? false),
            'metadata' => $request->auditExport
                ? $this->detailedMetadata($values['metadata'] ?? null)
                : $this->metadataSummary($values['metadata'] ?? null),
        ];
    }

    private function detailedMetadata(mixed $metadata): string
    {
        if (! is_string($metadata) || $metadata === '' || $metadata === '[]' || $metadata === '{}') {
            return '';
        }

        $decoded = json_decode($metadata, true);

        return is_array($decoded)
            ? json_encode($decoded, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            : '';
    }

    private function metadataSummary(mixed $metadata): string
    {
        if (! is_string($metadata) || $metadata === '' || $metadata === '[]' || $metadata === '{}') {
            return '';
        }

        $decoded = json_decode($metadata, true);

        if (! is_array($decoded)) {
            return '';
        }

        return implode(', ', array_slice(array_keys($decoded), 0, 8));
    }
}
