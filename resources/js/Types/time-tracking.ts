export interface DailyWorkTimeRow extends Record<string, unknown> {
    date: string;
    countedSeconds: number;
    workSeconds: number;
    breakSeconds: number;
    technicalBreakSeconds: number;
    maintenanceSeconds: number;
    otherWorkSeconds: number;
    acceptedOtherWorkSeconds: number;
    pendingOtherWorkSeconds: number;
    sessionStatus: string;
}

export interface LocalizedDailyWorkTimeRow extends DailyWorkTimeRow {
    countedDuration: string;
    workDuration: string;
    breakDuration: string;
    technicalBreakDuration: string;
    maintenanceDuration: string;
    otherWorkDuration: string;
    acceptedOtherWorkDuration: string;
    pendingOtherWorkDuration: string;
    localizedSessionStatus: string;
}

export interface OtherWorkRow extends Record<string, unknown> {
    publicId: string;
    sourceType: string;
    category: string;
    categoryLabelPl: string;
    categoryLabelEn: string;
    description: string;
    endNote: string;
    status: string;
    decisionState: string;
    requiresManagerDecision: boolean;
    startedAt: string;
    endedAt: string;
    exactSeconds: number;
    closureReason: string;
    availableActions: string[];
}

export interface LocalizedOtherWorkRow extends OtherWorkRow {
    duration: string;
}

export interface SourceTimeRow extends Record<string, unknown> {
    publicId: string;
    sourceType: string;
    status: string;
    startedAt: string;
    endedAt: string;
    exactSeconds: number;
    duration: string;
    availableActions: string[];
}

export interface BreakRow extends SourceTimeRow {
    breakLimitStatus: string;
    excessBreakSeconds: number;
    requiresManagerReview: boolean;
}

export interface LocalizedSourceTimeRow extends SourceTimeRow {
    statusLabel: string;
    duration: string;
}

export interface LocalizedBreakRow extends BreakRow {
    statusLabel: string;
    breakLimitLabel: string;
    excessBreakDuration: string;
    duration: string;
}

export interface CorrectionRow extends Record<string, unknown> {
    publicId: string;
    sourceType: string;
    type: string;
    status: string;
    description: string;
    requestedAt: string;
    decidedAt: string;
    decisionReason: string;
}

export interface LocalizedCorrectionRow extends CorrectionRow {
    typeLabel: string;
    statusLabel: string;
}

export interface TimeReportSummary {
    totalSeconds: number;
    workSeconds: number;
    breakSeconds: number;
    technicalBreakSeconds: number;
    maintenanceSeconds: number;
    otherWorkSeconds: number;
    acceptedOtherWorkSeconds: number;
    pendingOtherWorkSeconds: number;
    corrections: number;
    pending: number;
    records: number;
    users: number;
    open: number;
    closed: number;
    approved: number;
    rejected: number;
    corrected: number;
    requiresReview: number;
    excessSeconds: number;
    manualEntries: number;
    relatedCorrections: number;
}

export interface ComparisonMetric {
    metric: string;
    currentSeconds: number;
    previousSeconds: number;
    deltaSeconds: number;
    percentDelta: number | null;
}

export interface TimeReportComparison {
    available: boolean;
    rangeLabel: string;
    previousRangeLabel: string;
    metrics: ComparisonMetric[];
    userMetrics: ComparisonMetric[];
}
