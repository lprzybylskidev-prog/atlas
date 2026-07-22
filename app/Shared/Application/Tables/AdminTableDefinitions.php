<?php

declare(strict_types=1);

namespace App\Shared\Application\Tables;

final class AdminTableDefinitions
{
    public const USERS = 'admin.users';

    public const TEAMS = 'admin.teams';

    public const MANAGER_RELATIONSHIP_HISTORY = 'admin.managers.relationship-history';

    public const ROLES = 'admin.authorization.roles';

    public const PACKAGES = 'admin.authorization.packages';

    public const PERMISSIONS = 'admin.authorization.permissions';

    public const AUDIT = 'admin.audit';

    public const SECURITY_HISTORY = 'admin.audit.security-history';

    public const IMPERSONATION_SESSION_EVENTS = 'admin.audit.impersonation-session-events';

    public const RATE_LIMITS = 'admin.rate-limits';

    public const MODULES = 'admin.modules';

    public const APPLICATION_LOGS = 'admin.logs';

    public const FAILED_JOBS = 'admin.queues.failed-jobs';

    public const MODULE_DETAIL_TEAMS = 'admin.modules.detail.teams';

    public const MODULE_DETAIL_HISTORY = 'admin.modules.detail.history';

    public const MODULE_DETAIL_SCHEDULES = 'admin.modules.detail.schedules';

    public const NOTIFICATIONS = 'notifications';

    public const FILES = 'admin.files';

    public const INTEGRATION_ADAPTERS = 'admin.integrations.adapters';

    public const INTEGRATION_RUNS = 'admin.integrations.runs';

    public const SEARCH_INDEXES = 'admin.search.indexes';

    public const SEARCH_REBUILDS = 'admin.search.rebuilds';

    public const FEATURE_FLAGS = 'admin.feature-flags.flags';

    public const FEATURE_FLAG_HISTORY = 'admin.feature-flags.history';

    public const MANAGED_PROCESS_RUNS = 'admin.managed-processes.runs';

    public const MANAGED_PROCESS_DEFINITIONS = 'admin.managed-processes.definitions';

    public const MANAGED_PROCESS_SCHEDULES = 'admin.managed-processes.schedules';

    public const IMPORT_EXECUTIONS = 'admin.managed-processes.imports';

    public const IMPORT_ROW_ERRORS = 'admin.managed-processes.import-row-errors';

    public static function get(string $key): TableDefinition
    {
        return match ($key) {
            self::USERS => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('name'),
                new TableColumn('email'),
                new TableColumn('isActive', searchable: false),
                new TableColumn('emailVerified', searchable: false),
                new TableColumn('firstPasswordSet', searchable: false),
                new TableColumn('loginLocked', searchable: false),
                new TableColumn('mfaEnabled', searchable: false),
                new TableColumn('online', searchable: false),
                new TableColumn('accountSensitivity'),
                new TableColumn('emailVerifiedAt', defaultVisible: false),
                new TableColumn('twoFactorConfirmedAt', defaultVisible: false),
                new TableColumn('firstPasswordSetAt', defaultVisible: false),
                new TableColumn('deactivatedAt', defaultVisible: false),
                new TableColumn('failedLoginAttempts', searchable: false, defaultVisible: false),
                new TableColumn('loginLockCount', searchable: false, defaultVisible: false),
                new TableColumn('loginLockedUntil', defaultVisible: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'name'),
            self::TEAMS => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('name'),
                new TableColumn('isActive', searchable: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'name'),
            self::MANAGER_RELATIONSHIP_HISTORY => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('teamPublicId', defaultVisible: false),
                new TableColumn('teamName'),
                new TableColumn('managerUserPublicId', defaultVisible: false),
                new TableColumn('managerName'),
                new TableColumn('managerEmail', defaultVisible: false),
                new TableColumn('reportUserPublicId', defaultVisible: false),
                new TableColumn('reportName'),
                new TableColumn('reportEmail', defaultVisible: false),
                new TableColumn('validFrom'),
                new TableColumn('validTo'),
                new TableColumn('reason'),
                new TableColumn('endReason', defaultVisible: false),
            ], 'validFrom', 'desc'),
            self::ROLES => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('teamId', searchable: false, defaultVisible: false),
                new TableColumn('name'),
                new TableColumn('guard'),
                new TableColumn('permissionsCount', searchable: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'name'),
            self::PACKAGES => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('teamName'),
                new TableColumn('teamPublicId', defaultVisible: false),
                new TableColumn('label'),
                new TableColumn('name'),
                new TableColumn('initialRoles'),
                new TableColumn('directPermissions', defaultVisible: false),
                new TableColumn('templatePermissions', defaultVisible: false),
                new TableColumn('isActive', searchable: false, defaultVisible: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'label'),
            self::PERMISSIONS => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('name'),
                new TableColumn('guard', defaultVisible: false),
                new TableColumn('description'),
                new TableColumn('module'),
                new TableColumn('teamScoped', searchable: false),
                new TableColumn('moduleActivation'),
                new TableColumn('assigned', searchable: false),
                new TableColumn('effective', searchable: false),
                new TableColumn('ineffectiveReason', defaultVisible: false),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('updatedAt', defaultVisible: false),
            ], 'name'),
            self::AUDIT => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('id', searchable: false, defaultVisible: false),
                new TableColumn('occurredAt'),
                new TableColumn('module'),
                new TableColumn('action'),
                new TableColumn('result'),
                new TableColumn('source'),
                new TableColumn('actorPublicId'),
                new TableColumn('actualActorPublicId', defaultVisible: false),
                new TableColumn('impersonatedUserPublicId', defaultVisible: false),
                new TableColumn('impersonationSessionId', defaultVisible: false),
                new TableColumn('targetType'),
                new TableColumn('targetPublicId'),
                new TableColumn('aggregateType', defaultVisible: false),
                new TableColumn('aggregatePublicId', defaultVisible: false),
                new TableColumn('teamPublicId'),
                new TableColumn('correlationId'),
                new TableColumn('reason', defaultVisible: false),
                new TableColumn('security', searchable: false),
                new TableColumn('metadata', defaultVisible: false),
            ], 'occurredAt', 'desc'),
            self::SECURITY_HISTORY => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('userName'),
                new TableColumn('userEmail', defaultVisible: false),
                new TableColumn('userContext', defaultVisible: false),
                new TableColumn('occurredAt'),
                new TableColumn('action'),
                new TableColumn('result'),
                new TableColumn('source'),
                new TableColumn('teamPublicId'),
                new TableColumn('impersonationSessionId'),
                new TableColumn('reason'),
            ], 'occurredAt', 'desc'),
            self::IMPERSONATION_SESSION_EVENTS => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('occurredAt'),
                new TableColumn('module'),
                new TableColumn('action'),
                new TableColumn('result'),
                new TableColumn('source'),
                new TableColumn('actorPublicId'),
                new TableColumn('actualActorPublicId', defaultVisible: false),
                new TableColumn('impersonatedUserPublicId', defaultVisible: false),
                new TableColumn('targetType'),
                new TableColumn('targetPublicId'),
                new TableColumn('teamPublicId'),
                new TableColumn('correlationId', defaultVisible: false),
                new TableColumn('reason'),
                new TableColumn('security', searchable: false, defaultVisible: false),
            ], 'occurredAt'),
            self::RATE_LIMITS => new TableDefinition($key, [
                new TableColumn('publicId'),
                new TableColumn('policy'),
                new TableColumn('maxAttempts', searchable: false),
                new TableColumn('decaySeconds', searchable: false),
                new TableColumn('keyParts'),
                new TableColumn('progressiveDelays', defaultVisible: false),
                new TableColumn('temporaryLockSeconds', searchable: false, defaultVisible: false),
                new TableColumn('rejections', searchable: false),
                new TableColumn('distinctKeys', searchable: false),
                new TableColumn('lastRejectedAt', defaultVisible: false),
            ], 'policy'),
            self::MODULES => new TableDefinition($key, [
                new TableColumn('moduleKey'),
                new TableColumn('category'),
                new TableColumn('technicallyAvailable', searchable: false),
                new TableColumn('globallyEnabled', searchable: false),
                new TableColumn('teamEnabled', searchable: false),
                new TableColumn('effectiveEnabled', searchable: false),
                new TableColumn('teamStateSource'),
                new TableColumn('supportsGlobalActivation', searchable: false, defaultVisible: false),
                new TableColumn('supportsTeamActivation', searchable: false, defaultVisible: false),
                new TableColumn('requiredDependencies', defaultVisible: false),
                new TableColumn('optionalDependencies', defaultVisible: false),
            ], 'moduleKey'),
            self::APPLICATION_LOGS => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('line', searchable: false, defaultVisible: false),
                new TableColumn('occurredAt'),
                new TableColumn('level'),
                new TableColumn('channel'),
                new TableColumn('environment'),
                new TableColumn('module'),
                new TableColumn('source'),
                new TableColumn('eventName'),
                new TableColumn('correlationId', defaultVisible: false),
                new TableColumn('requestId', defaultVisible: false),
                new TableColumn('message'),
                new TableColumn('details', defaultVisible: false),
            ], 'occurredAt', 'desc'),
            self::FAILED_JOBS => new TableDefinition($key, [
                new TableColumn('uuid'),
                new TableColumn('connection'),
                new TableColumn('queue'),
                new TableColumn('failedAt'),
                new TableColumn('displayName'),
                new TableColumn('jobClass', defaultVisible: false),
                new TableColumn('exceptionType'),
                new TableColumn('exceptionMessage'),
            ], 'failedAt', 'desc'),
            self::MODULE_DETAIL_TEAMS => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('moduleKey'),
                new TableColumn('name'),
                new TableColumn('isActive', searchable: false),
                new TableColumn('teamEnabled', searchable: false),
                new TableColumn('effectiveEnabled', searchable: false),
                new TableColumn('source'),
                new TableColumn('version', searchable: false, defaultVisible: false),
            ], 'name'),
            self::MODULE_DETAIL_HISTORY => new TableDefinition($key, [
                new TableColumn('moduleKey'),
                new TableColumn('scope'),
                new TableColumn('teamName'),
                new TableColumn('teamPublicId', defaultVisible: false),
                new TableColumn('previousEnabled', searchable: false),
                new TableColumn('newEnabled', searchable: false),
                new TableColumn('source'),
                new TableColumn('reason'),
                new TableColumn('effectiveAt'),
            ], 'effectiveAt', 'desc'),
            self::MODULE_DETAIL_SCHEDULES => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('moduleKey'),
                new TableColumn('scope'),
                new TableColumn('teamName'),
                new TableColumn('teamPublicId', defaultVisible: false),
                new TableColumn('targetEnabled', searchable: false),
                new TableColumn('status'),
                new TableColumn('reason'),
                new TableColumn('effectiveAt'),
            ], 'effectiveAt', 'desc'),
            self::NOTIFICATIONS => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('type'),
                new TableColumn('severity'),
                new TableColumn('title'),
                new TableColumn('body'),
                new TableColumn('teamPublicId', defaultVisible: false),
                new TableColumn('read', searchable: false),
                new TableColumn('createdAt'),
                new TableColumn('readAt', defaultVisible: false),
                new TableColumn('deepLinkUrl', defaultVisible: false),
            ], 'createdAt', 'desc'),
            self::FILES => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('originalName'),
                new TableColumn('extension'),
                new TableColumn('mimeType'),
                new TableColumn('scanState'),
                new TableColumn('sizeBytes', searchable: false),
                new TableColumn('checksumSha256'),
                new TableColumn('scannedAt'),
                new TableColumn('provider', defaultVisible: false),
                new TableColumn('engineVersion', defaultVisible: false),
                new TableColumn('signatureVersion', defaultVisible: false),
                new TableColumn('scanAttempts', searchable: false, defaultVisible: false),
                new TableColumn('quarantinedAt', defaultVisible: false),
                new TableColumn('availableAt', defaultVisible: false),
                new TableColumn('threatName', defaultVisible: false),
                new TableColumn('createdAt', defaultVisible: false),
            ], 'createdAt', 'desc'),
            self::INTEGRATION_ADAPTERS => new TableDefinition($key, [
                new TableColumn('name'),
                new TableColumn('key'),
                new TableColumn('sourceOfTruth'),
                new TableColumn('adapterClass', defaultVisible: false),
                new TableColumn('circuitState'),
                new TableColumn('lastSuccessAt'),
                new TableColumn('lastErrorAt'),
                new TableColumn('lastErrorMessage', defaultVisible: false),
                new TableColumn('externalApiEnabled', searchable: false, defaultVisible: false),
            ], 'name'),
            self::INTEGRATION_RUNS => new TableDefinition($key, [
                new TableColumn('rowKey', defaultVisible: false),
                new TableColumn('integrationKey'),
                new TableColumn('operation'),
                new TableColumn('status'),
                new TableColumn('startedAt'),
                new TableColumn('finishedAt', defaultVisible: false),
                new TableColumn('correlationId'),
                new TableColumn('message', defaultVisible: false),
            ], 'startedAt', 'desc'),
            self::SEARCH_INDEXES => new TableDefinition($key, [
                new TableColumn('key'),
                new TableColumn('moduleKey'),
                new TableColumn('stableAlias'),
                new TableColumn('searchableFields'),
                new TableColumn('filterableFields', defaultVisible: false),
                new TableColumn('sortableFields', defaultVisible: false),
                new TableColumn('containsSensitiveData', searchable: false),
                new TableColumn('supportsDeletion', searchable: false),
                new TableColumn('supportsAnonymization', searchable: false),
            ], 'key'),
            self::SEARCH_REBUILDS => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('status'),
                new TableColumn('currentStage'),
                new TableColumn('progressLabel'),
                new TableColumn('progressCurrent', searchable: false),
                new TableColumn('progressTotal', searchable: false),
                new TableColumn('createdAt'),
                new TableColumn('startedAt'),
                new TableColumn('finishedAt'),
            ], 'createdAt', 'desc'),
            self::FEATURE_FLAGS => new TableDefinition($key, [
                new TableColumn('name'),
                new TableColumn('key'),
                new TableColumn('ownerModule'),
                new TableColumn('type'),
                new TableColumn('defaultEnabled', searchable: false),
                new TableColumn('globalEnabled', searchable: false),
                new TableColumn('teamEnabled', searchable: false),
                new TableColumn('effectiveEnabled', searchable: false),
                new TableColumn('source'),
                new TableColumn('lifecycle', defaultVisible: false),
                new TableColumn('description', defaultVisible: false),
                new TableColumn('teamScoped', searchable: false, defaultVisible: false),
                new TableColumn('selectedTeamPublicId', defaultVisible: false),
            ], 'name'),
            self::FEATURE_FLAG_HISTORY => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('createdAt'),
                new TableColumn('flagKey'),
                new TableColumn('scope'),
                new TableColumn('teamName'),
                new TableColumn('teamPublicId', defaultVisible: false),
                new TableColumn('action'),
                new TableColumn('reason'),
                new TableColumn('actorPublicId', defaultVisible: false),
                new TableColumn('beforeEnabled', searchable: false, defaultVisible: false),
                new TableColumn('afterEnabled', searchable: false, defaultVisible: false),
            ], 'createdAt', 'desc'),
            self::MANAGED_PROCESS_RUNS => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('processKey'),
                new TableColumn('status'),
                new TableColumn('sourceType'),
                new TableColumn('moduleKey'),
                new TableColumn('progressLabel'),
                new TableColumn('progressCurrent', searchable: false),
                new TableColumn('progressTotal', searchable: false),
                new TableColumn('actor'),
                new TableColumn('team'),
                new TableColumn('startedAt'),
                new TableColumn('finishedAt'),
                new TableColumn('createdAt', defaultVisible: false),
                new TableColumn('queueName', defaultVisible: false),
                new TableColumn('correlationId', defaultVisible: false),
                new TableColumn('safeErrorSummary', defaultVisible: false),
            ], 'createdAt', 'desc'),
            self::MANAGED_PROCESS_DEFINITIONS => new TableDefinition($key, [
                new TableColumn('label'),
                new TableColumn('key'),
                new TableColumn('moduleKey'),
                new TableColumn('scope'),
                new TableColumn('queueName'),
                new TableColumn('executionMode'),
                new TableColumn('concurrencyPolicy'),
                new TableColumn('retryable', searchable: false),
                new TableColumn('scheduleSupported', searchable: false),
                new TableColumn('manualStartSupported', searchable: false, defaultVisible: false),
                new TableColumn('cancellationPolicy', defaultVisible: false),
                new TableColumn('externalEffects', searchable: false, defaultVisible: false),
                new TableColumn('highRisk', searchable: false, defaultVisible: false),
            ], 'label'),
            self::MANAGED_PROCESS_SCHEDULES => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('processKey'),
                new TableColumn('moduleKey'),
                new TableColumn('team'),
                new TableColumn('cronExpression'),
                new TableColumn('enabled', searchable: false),
                new TableColumn('nextDueAt'),
                new TableColumn('createdAt'),
                new TableColumn('overlapPolicy'),
                new TableColumn('reason'),
                new TableColumn('scope', defaultVisible: false),
                new TableColumn('timezone', defaultVisible: false),
                new TableColumn('intervalKey', defaultVisible: false),
            ], 'createdAt', 'desc'),
            self::IMPORT_EXECUTIONS => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('runPublicId', defaultVisible: false),
                new TableColumn('importKey'),
                new TableColumn('sourceType'),
                new TableColumn('status'),
                new TableColumn('idempotencyKey'),
                new TableColumn('idempotencyState'),
                new TableColumn('statistics'),
                new TableColumn('createdAt'),
            ], 'createdAt', 'desc'),
            self::IMPORT_ROW_ERRORS => new TableDefinition($key, [
                new TableColumn('publicId', defaultVisible: false),
                new TableColumn('runPublicId', defaultVisible: false),
                new TableColumn('importPublicId', defaultVisible: false),
                new TableColumn('rowNumber', searchable: false),
                new TableColumn('fieldName'),
                new TableColumn('severity'),
                new TableColumn('errorCode'),
                new TableColumn('message'),
            ], 'rowNumber'),
            default => abort(404),
        };
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [
            self::USERS,
            self::TEAMS,
            self::MANAGER_RELATIONSHIP_HISTORY,
            self::ROLES,
            self::PACKAGES,
            self::PERMISSIONS,
            self::AUDIT,
            self::SECURITY_HISTORY,
            self::IMPERSONATION_SESSION_EVENTS,
            self::RATE_LIMITS,
            self::MODULES,
            self::APPLICATION_LOGS,
            self::FAILED_JOBS,
            self::MODULE_DETAIL_TEAMS,
            self::MODULE_DETAIL_HISTORY,
            self::MODULE_DETAIL_SCHEDULES,
            self::NOTIFICATIONS,
            self::FILES,
            self::INTEGRATION_ADAPTERS,
            self::INTEGRATION_RUNS,
            self::SEARCH_INDEXES,
            self::SEARCH_REBUILDS,
            self::FEATURE_FLAGS,
            self::FEATURE_FLAG_HISTORY,
            self::MANAGED_PROCESS_RUNS,
            self::MANAGED_PROCESS_DEFINITIONS,
            self::MANAGED_PROCESS_SCHEDULES,
            self::IMPORT_EXECUTIONS,
            self::IMPORT_ROW_ERRORS,
        ];
    }
}
