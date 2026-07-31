export interface ManagedProcessSummary {
    active?: number;
    failed24h?: number;
    warnings24h?: number;
    handled?: number;
    imports?: number;
    definitions?: number;
    schedulable?: number;
    manual?: number;
    schedules?: number;
    disabled?: number;
}

export interface ManagedProcessRunRow extends Record<string, unknown> {
    publicId: string;
    processKey: string;
    moduleKey: string;
    importKey: string | null;
    importSourceType: string | null;
    importFile: string | null;
    idempotencyKey: string | null;
    idempotencyState: string | null;
    scope: string;
    status: string;
    acknowledged: boolean;
    handlingStatus: 'needs_attention' | 'handled' | 'ok' | string;
    acknowledgedAt: string | null;
    acknowledgedBy: string | null;
    sourceType: string;
    stage: string | null;
    progressCurrent: number;
    progressTotal: number | null;
    progressLabel: string | null;
    counters: Record<string, unknown>;
    inputSnapshot: Record<string, unknown>;
    resultSummary: Record<string, unknown>;
    safeErrorSummary: string | null;
    queueName: string | null;
    correlationId: string;
    actor: string | null;
    team: string | null;
    createdAt: string;
    queuedAt: string | null;
    startedAt: string | null;
    finishedAt: string | null;
    canRetry: boolean;
    canCancel: boolean;
    canAcknowledge: boolean;
}

export interface ManagedProcessDefinitionRow extends Record<string, unknown> {
    key: string;
    moduleKey: string;
    label: string;
    description: string;
    scope: string;
    queueName: string;
    executionMode: string;
    concurrencyPolicy: string;
    parallelism: number;
    retryable: boolean;
    cancellationPolicy: string;
    scheduleSupported: boolean;
    manualStartSupported: boolean;
    externalEffects: boolean;
    highRisk: boolean;
    supportsFileUpload: boolean;
    supportsWatchedDirectory: boolean;
}

export interface ManagedProcessScheduleRow extends Record<string, unknown> {
    publicId: string;
    processKey: string;
    moduleKey: string;
    scope: string;
    team: string | null;
    timezone: string;
    cronExpression: string | null;
    intervalKey: string | null;
    enabled: boolean;
    nextDueAt: string | null;
    overlapPolicy: string;
    reason: string;
    createdAt: string | null;
}

export interface ManagedProcessImportRow extends Record<string, unknown> {
    publicId: string;
    runPublicId: string;
    importKey: string;
    sourceType: string;
    status: string;
    statistics: string;
    idempotencyKey: string | null;
    idempotencyState: string;
    createdAt: string | null;
}

export interface ManagedProcessLogRow {
    publicId: string;
    occurredAt: string;
    severity: string;
    eventType: string;
    stage: string | null;
    message: string;
    safeContext: Record<string, unknown>;
    rowNumber: number | null;
    entityPublicId: string | null;
    externalReference: string | null;
    sourceReference: string | null;
    errorCode: string | null;
    exceptionClass: string | null;
    retryable: boolean | null;
    correlationId: string;
}

export interface ManagedProcessFilterOptions {
    processes?: string[];
    statuses?: string[];
    sources?: string[];
    modules?: string[];
    imports?: string[];
    idempotencyStates?: string[];
    queues?: string[];
    severities?: string[];
    eventTypes?: string[];
    stages?: string[];
}
