<?php

declare(strict_types=1);

namespace App\Modules\Core\Audit\Application\Exports;

use App\Modules\Core\Exports\Application\Public\AbstractAdminDataTableExportProvider;
use App\Modules\Core\Exports\Application\Public\DTOs\ReportExportGenerationRequest;
use App\Modules\Core\Exports\Application\Public\Permissions\ReportsPermissionCatalog;
use App\Shared\Application\Tables\AdminTableDefinitions;
use App\Shared\Infrastructure\Database\DatabaseTable;
use Illuminate\Support\Facades\DB;

final readonly class AdminImpersonationSessionEventsDataTableExportProvider extends AbstractAdminDataTableExportProvider
{
    public function tableKey(): string
    {
        return AdminTableDefinitions::IMPERSONATION_SESSION_EVENTS;
    }

    public function tableName(): string
    {
        return 'Impersonation session events';
    }

    public function owningModuleKey(): string
    {
        return 'audit';
    }

    public function requestPermission(): string
    {
        return ReportsPermissionCatalog::REQUEST;
    }

    public function ruleVersion(): string
    {
        return 'admin-impersonation-session-events-export-v1';
    }

    protected function columnLabels(): array
    {
        return [
            'publicId' => 'Public ID',
            'occurredAt' => 'Occurred at',
            'module' => 'Module',
            'action' => 'Action',
            'result' => 'Result',
            'source' => 'Source',
            'actorPublicId' => 'Actor',
            'actualActorPublicId' => 'Actual actor',
            'impersonatedUserPublicId' => 'Impersonated user',
            'targetType' => 'Target type',
            'targetPublicId' => 'Target',
            'teamPublicId' => 'Team',
            'correlationId' => 'Correlation ID',
            'reason' => 'Reason',
            'security' => 'Security',
        ];
    }

    public function rows(ReportExportGenerationRequest $request): iterable
    {
        $session = self::filterValue($request, 'session');

        if ($session === '') {
            return;
        }

        $rows = DB::table(DatabaseTable::AUDIT_EVENTS)
            ->where('impersonation_session_id', $session)
            ->orderBy('occurred_at')
            ->get()
            ->map(static fn (object $record): array => [
                'publicId' => self::stringValue($record->public_id ?? ''),
                'occurredAt' => self::stringValue($record->occurred_at ?? ''),
                'module' => self::stringValue($record->module ?? ''),
                'action' => self::stringValue($record->action ?? ''),
                'result' => self::stringValue($record->result ?? ''),
                'source' => self::stringValue($record->source ?? ''),
                'actorPublicId' => self::stringValue($record->actor_public_id ?? ''),
                'actualActorPublicId' => self::stringValue($record->actual_actor_public_id ?? ''),
                'impersonatedUserPublicId' => self::stringValue($record->impersonated_user_public_id ?? ''),
                'targetType' => self::stringValue($record->target_type ?? ''),
                'targetPublicId' => self::stringValue($record->target_public_id ?? ''),
                'teamPublicId' => self::stringValue($record->team_public_id ?? ''),
                'correlationId' => self::stringValue($record->correlation_id ?? ''),
                'reason' => self::stringValue($record->reason ?? ''),
                'security' => (bool) ($record->is_security ?? false),
            ])
            ->values()
            ->all();

        foreach ($this->sorted($this->filtered(array_values($rows), $request), $request) as $row) {
            yield $row;
        }
    }
}
