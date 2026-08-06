<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import {
    IconBriefcase,
    IconClockHour4,
    IconDatabase,
    IconFilePencil,
    IconHourglass,
    IconPlus,
    IconPlayerPause,
    IconRefresh,
    IconTool,
} from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';
import type { Component } from 'vue';

import ActionLink from '../../Components/ActionLink.vue';
import DataTable from '../../Components/DataTable.vue';
import DialogPanel from '../../Components/DialogPanel.vue';
import FilterPanel from '../../Components/FilterPanel.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import DialogFormActions from '../../Components/Form/DialogFormActions.vue';
import FormDateInput from '../../Components/Form/FormDateInput.vue';
import FormDateTimeInput from '../../Components/Form/FormDateTimeInput.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import FormTextarea from '../../Components/Form/FormTextarea.vue';
import OperationalMetricTile from '../../Components/OperationalMetricTile.vue';
import PageStack from '../../Components/PageStack.vue';
import {
    formatTimeTrackingDuration,
    timeTrackingCompareOptions,
    timeTrackingRangeOptions,
    timeTrackingStatusLabel,
} from '../../Composables/useTimeTrackingReportUi';
import { applyTableFilters, clearTableFilters } from '../../Composables/useTableFilterControls';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../Types/data-table';
import type { AtlasPageProps } from '../../Types/inertia';
import type { ShellSubnavigationItem } from '../../Types/navigation';
import { formatDateTime } from '../../Utils/formatters';

interface DailyWorkTimeRow extends Record<string, unknown> {
    userPublicId: string;
    userName: string;
    userEmail: string;
    teamPublicId: string;
    teamName: string;
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

interface OtherWorkRow extends Record<string, unknown> {
    publicId: string;
    userPublicId: string;
    userName: string;
    userEmail: string;
    teamPublicId: string;
    teamName: string;
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

interface WorkSessionRow extends Record<string, unknown> {
    publicId: string;
    userPublicId: string;
    userName: string;
    userEmail: string;
    teamPublicId: string;
    teamName: string;
    startedAt: string;
    endedAt: string;
    exactSeconds: number;
    closureReason: string;
    laravelSessionId: string;
    moduleSegments: number;
    relatedBreaks: number;
    relatedOtherWork: number;
    maintenanceImpacts: number;
    corrections: number;
}

interface BreakRow extends Record<string, unknown> {
    publicId: string;
    userPublicId: string;
    userName: string;
    userEmail: string;
    teamPublicId: string;
    teamName: string;
    status: string;
    startedAt: string;
    endedAt: string;
    exactSeconds: number;
    breakLimitStatus: string;
    excessBreakSeconds: number;
    closureReason: string;
    requiresManagerReview: boolean;
    availableActions: string[];
}

interface CorrectionRow extends Record<string, unknown> {
    publicId: string;
    userPublicId: string;
    userName: string;
    userEmail: string;
    teamPublicId: string;
    teamName: string;
    sourceType: string;
    sourcePublicId: string;
    type: string;
    status: string;
    description: string;
    requestedAt: string;
    decidedAt: string;
    decisionReason: string;
    originalStartedAt: string;
    originalEndedAt: string;
    originalExactSeconds: number | null;
    proposedStartedAt: string;
    proposedEndedAt: string;
    proposedExactSeconds: number | null;
    finalStartedAt: string;
    finalEndedAt: string;
    finalExactSeconds: number | null;
    proposalCount: number;
    historyCount: number;
    availableActions: string[];
}

interface LocalizedDailyWorkTimeRow extends DailyWorkTimeRow {
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

interface LocalizedOtherWorkRow extends OtherWorkRow {
    duration: string;
}

interface LocalizedWorkSessionRow extends WorkSessionRow {
    duration: string;
}

interface LocalizedBreakRow extends BreakRow {
    duration: string;
    breakLimitLabel: string;
    excessBreakDuration: string;
}

interface LocalizedCorrectionRow extends CorrectionRow {
    typeLabel: string;
}

interface TimeReportSummary {
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
}

interface TeamOption {
    publicId: string;
    name: string;
    trackedUsers: number;
}

interface UserOption {
    publicId: string;
    name: string;
    email: string;
}

interface OtherWorkCategoryOption {
    key: string;
    labelPl: string;
    labelEn: string;
    teamPublicId: string;
}

type AdminOperationsSection = 'daily' | 'other_work' | 'work_sessions' | 'breaks' | 'corrections';
type OperationsSurface = 'admin' | 'manager';

const props = defineProps<{
    surface?: OperationsSurface;
    section: AdminOperationsSection;
    teamOptions: TeamOption[];
    userOptions: UserOption[];
    userOptionsByTeam: Record<string, UserOption[]>;
    moduleOptions: string[];
    moduleOptionsByTeam: Record<string, string[]>;
    otherWorkCategoryOptions: OtherWorkCategoryOption[];
    otherWorkCategoryOptionsByTeam: Record<string, OtherWorkCategoryOption[]>;
    dailyRows: DailyWorkTimeRow[];
    otherWorkRows: OtherWorkRow[];
    workSessionRows: WorkSessionRow[];
    breakRows: BreakRow[];
    correctionRows: CorrectionRow[];
    summary: TimeReportSummary;
    filters: Record<string, string>;
    dailyTable: DataTableMeta;
    otherWorkTable: DataTableMeta;
    workSessionsTable: DataTableMeta;
    breaksTable: DataTableMeta;
    correctionsTable: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const page = usePage<AtlasPageProps>();
const surface = computed<OperationsSurface>(() => props.surface ?? 'admin');
const isAdminSurface = computed(() => surface.value === 'admin');
const isManagerSurface = computed(() => surface.value === 'manager');
const filterDefaults = {
    team: '',
    user: '',
    range: 'settlement_period',
    from: '',
    to: '',
    type: 'all',
    status: 'all',
    category: '',
    decision_state: 'all',
    correction_type: 'all',
    closure_reason: '',
    review: 'all',
    compare: 'off',
};
type FilterKey = keyof typeof filterDefaults;
const sectionFilterKeys: Record<AdminOperationsSection, FilterKey[]> = {
    daily: ['team', 'user', 'range', 'from', 'to', 'compare'],
    other_work: ['team', 'user', 'range', 'from', 'to', 'category', 'status', 'decision_state', 'closure_reason', 'review'],
    breaks: ['team', 'user', 'range', 'from', 'to', 'status', 'closure_reason', 'review'],
    corrections: ['team', 'user', 'range', 'from', 'to', 'correction_type', 'status', 'review'],
    work_sessions: ['team', 'user', 'range', 'from', 'to', 'status', 'closure_reason'],
};
const filters = ref({ ...filterDefaults, ...props.filters });
const actionModalOpen = ref(false);
const selectedAction = ref<{
    kind:
        | 'terminate'
        | 'force_close_break'
        | 'force_close_other_work'
        | 'approve_other_work'
        | 'reject_other_work'
        | 'reject'
        | 'correct'
        | 'convert_excess_break';
    publicId: string;
    subject: string;
    convertedSeconds?: number;
    correction?: LocalizedCorrectionRow;
} | null>(null);
const actionForm = useForm({
    decision: '',
    reason: '',
    converted_seconds: '',
    final_started_at: '',
    final_ended_at: '',
});
const selectedTeamMissing = computed(() => filters.value.team === '');
const selectedUserMissing = computed(() => filters.value.user === '');
const sectionMeta: Record<
    typeof props.section,
    {
        labelKey: string;
        icon: Component;
    }
> = {
    daily: {
        labelKey: 'navigation.work_time_daily',
        icon: IconClockHour4,
    },
    other_work: {
        labelKey: 'navigation.work_time_other_work',
        icon: IconBriefcase,
    },
    breaks: {
        labelKey: 'navigation.work_time_breaks',
        icon: IconPlayerPause,
    },
    corrections: {
        labelKey: 'navigation.work_time_corrections',
        icon: IconFilePencil,
    },
    work_sessions: {
        labelKey: 'navigation.work_time_sessions',
        icon: IconDatabase,
    },
};
const activeSectionMeta = computed(() => sectionMeta[props.section]);
const pageTitle = computed(() => t(activeSectionMeta.value.labelKey));
const teamOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.admin_operations.filters.team_placeholder') },
    ...props.teamOptions.map((team) => ({
        value: team.publicId,
        label: t('pages.time_tracking.admin_operations.filters.team_option', { team: team.name, count: team.trackedUsers }),
    })),
]);
const usersForSelectedTeam = computed<UserOption[]>(() => {
    if (filters.value.team === '') {
        return props.userOptions;
    }

    return props.userOptionsByTeam[filters.value.team] ?? [];
});
const userSelectOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.admin_operations.filters.any_user') },
    ...usersForSelectedTeam.value.map((user) => ({
        value: user.publicId,
        label: user.email === '' ? user.name : `${user.name} (${user.email})`,
    })),
]);
const categoriesForSelectedTeam = computed<OtherWorkCategoryOption[]>(() => {
    if (filters.value.team === '') {
        return props.otherWorkCategoryOptions;
    }

    return props.otherWorkCategoryOptionsByTeam[filters.value.team] ?? [];
});
const subnavigation = computed<ShellSubnavigationItem[]>(() => [
    {
        key: 'daily',
        label: t('navigation.work_time_daily'),
        href: routeWithFilters(sectionPath('daily'), 'daily'),
        icon: IconClockHour4,
        active: props.section === 'daily',
    },
    {
        key: 'other_work',
        label: t('navigation.work_time_other_work'),
        href: routeWithFilters(sectionPath('other_work'), 'other_work'),
        icon: IconBriefcase,
        active: props.section === 'other_work',
    },
    {
        key: 'breaks',
        label: t('navigation.work_time_breaks'),
        href: routeWithFilters(sectionPath('breaks'), 'breaks'),
        icon: IconPlayerPause,
        active: props.section === 'breaks',
    },
    {
        key: 'corrections',
        label: t('navigation.work_time_corrections'),
        href: routeWithFilters(sectionPath('corrections'), 'corrections'),
        icon: IconFilePencil,
        active: props.section === 'corrections',
    },
    {
        key: 'work_sessions',
        label: t('navigation.work_time_sessions'),
        href: routeWithFilters(sectionPath('work_sessions'), 'work_sessions'),
        icon: IconDatabase,
        active: props.section === 'work_sessions',
    },
]);

const dailyColumns = computed<DataTableColumn<LocalizedDailyWorkTimeRow>[]>(() => [
    { key: 'userPublicId', label: t('pages.time_tracking.admin_operations.table.user_public_id'), hidden: true },
    { key: 'userName', label: t('pages.time_tracking.admin_operations.table.user'), hidden: selectedUserMissing.value },
    { key: 'userEmail', label: t('pages.time_tracking.admin_operations.table.user_email'), hidden: true },
    { key: 'teamPublicId', label: t('pages.time_tracking.admin_operations.table.team_public_id'), hidden: true },
    { key: 'teamName', label: t('pages.time_tracking.admin_operations.table.team') },
    { key: 'date', label: t('pages.time_tracking.user_report.daily_table.date') },
    { key: 'countedDuration', label: t('pages.time_tracking.user_report.daily_table.counted') },
    { key: 'workDuration', label: t('pages.time_tracking.user_report.daily_table.work') },
    { key: 'breakDuration', label: t('pages.time_tracking.user_report.daily_table.break') },
    { key: 'technicalBreakDuration', label: t('pages.time_tracking.user_report.daily_table.technical_break') },
    { key: 'maintenanceDuration', label: t('pages.time_tracking.user_report.daily_table.maintenance') },
    { key: 'otherWorkDuration', label: t('pages.time_tracking.user_report.daily_table.other_work') },
    { key: 'acceptedOtherWorkDuration', label: t('pages.time_tracking.user_report.daily_table.accepted_other_work') },
    { key: 'pendingOtherWorkDuration', label: t('pages.time_tracking.user_report.daily_table.pending_other_work') },
    { key: 'localizedSessionStatus', label: t('pages.time_tracking.user_report.daily_table.status'), format: 'status-badge' },
]);
const otherWorkColumns = computed<DataTableColumn<LocalizedOtherWorkRow>[]>(() => [
    { key: 'publicId', label: t('pages.time_tracking.user_report.other_work_table.public_id'), hidden: true },
    { key: 'userName', label: t('pages.time_tracking.admin_operations.table.user') },
    { key: 'teamName', label: t('pages.time_tracking.admin_operations.table.team') },
    { key: 'category', label: t('pages.time_tracking.user_report.other_work_table.category') },
    { key: 'description', label: t('pages.time_tracking.user_report.other_work_table.description') },
    { key: 'endNote', label: t('pages.time_tracking.user_report.other_work_table.end_note'), hidden: true },
    { key: 'status', label: t('pages.time_tracking.user_report.other_work_table.status'), format: 'status-badge' },
    { key: 'decisionState', label: t('pages.time_tracking.user_report.other_work_table.decision_state'), format: 'status-badge' },
    { key: 'startedAt', label: t('pages.time_tracking.user_report.other_work_table.started_at'), format: 'datetime' },
    { key: 'endedAt', label: t('pages.time_tracking.user_report.other_work_table.ended_at'), format: 'datetime' },
    { key: 'duration', label: t('pages.time_tracking.user_report.other_work_table.duration') },
    { key: 'closureReason', label: t('pages.time_tracking.user_report.other_work_table.closure_reason'), hidden: true },
]);
const workSessionColumns = computed<DataTableColumn<LocalizedWorkSessionRow>[]>(() => [
    { key: 'publicId', label: t('pages.time_tracking.admin_operations.table.public_id'), hidden: true },
    { key: 'userPublicId', label: t('pages.time_tracking.admin_operations.table.user_public_id'), hidden: true },
    { key: 'userName', label: t('pages.time_tracking.admin_operations.table.user') },
    { key: 'userEmail', label: t('pages.time_tracking.admin_operations.table.user_email'), hidden: true },
    { key: 'teamPublicId', label: t('pages.time_tracking.admin_operations.table.team_public_id'), hidden: true },
    { key: 'teamName', label: t('pages.time_tracking.admin_operations.table.team') },
    { key: 'startedAt', label: t('pages.time_tracking.admin_operations.table.started_at'), format: 'datetime' },
    { key: 'endedAt', label: t('pages.time_tracking.admin_operations.table.ended_at'), format: 'datetime' },
    { key: 'duration', label: t('pages.time_tracking.admin_operations.table.duration') },
    { key: 'exactSeconds', label: t('pages.time_tracking.admin_operations.table.exact_seconds'), format: 'number', hidden: true },
    { key: 'closureReason', label: t('pages.time_tracking.admin_operations.table.closure_reason'), format: 'status' },
    { key: 'laravelSessionId', label: t('pages.time_tracking.admin_operations.table.laravel_session'), hidden: true },
    { key: 'moduleSegments', label: t('pages.time_tracking.admin_operations.table.module_segments'), format: 'number' },
    { key: 'relatedBreaks', label: t('pages.time_tracking.admin_operations.table.related_breaks'), format: 'number' },
    { key: 'relatedOtherWork', label: t('pages.time_tracking.admin_operations.table.related_other_work'), format: 'number' },
    { key: 'maintenanceImpacts', label: t('pages.time_tracking.admin_operations.table.maintenance_impacts'), format: 'number' },
    { key: 'corrections', label: t('pages.time_tracking.admin_operations.table.corrections'), format: 'number' },
]);
const breakColumns = computed<DataTableColumn<LocalizedBreakRow>[]>(() => [
    { key: 'publicId', label: t('pages.time_tracking.admin_operations.table.public_id'), hidden: true },
    { key: 'userPublicId', label: t('pages.time_tracking.admin_operations.table.user_public_id'), hidden: true },
    { key: 'userName', label: t('pages.time_tracking.admin_operations.table.user') },
    { key: 'userEmail', label: t('pages.time_tracking.admin_operations.table.user_email'), hidden: true },
    { key: 'teamName', label: t('pages.time_tracking.admin_operations.table.team') },
    { key: 'status', label: t('pages.time_tracking.admin_operations.table.status'), format: 'status-badge' },
    { key: 'startedAt', label: t('pages.time_tracking.admin_operations.table.started_at'), format: 'datetime' },
    { key: 'endedAt', label: t('pages.time_tracking.admin_operations.table.ended_at'), format: 'datetime' },
    { key: 'duration', label: t('pages.time_tracking.admin_operations.table.duration') },
    { key: 'breakLimitLabel', label: t('pages.time_tracking.admin_operations.table.break_limit'), format: 'status-badge' },
    { key: 'excessBreakDuration', label: t('pages.time_tracking.admin_operations.table.excess_break') },
    { key: 'closureReason', label: t('pages.time_tracking.admin_operations.table.closure_reason'), format: 'status' },
    { key: 'requiresManagerReview', label: t('pages.time_tracking.admin_operations.table.requires_review'), format: 'boolean' },
]);
const correctionColumns = computed<DataTableColumn<LocalizedCorrectionRow>[]>(() => [
    { key: 'publicId', label: t('pages.time_tracking.admin_operations.table.public_id'), hidden: true },
    { key: 'userPublicId', label: t('pages.time_tracking.admin_operations.table.user_public_id'), hidden: true },
    { key: 'userName', label: t('pages.time_tracking.admin_operations.table.user') },
    { key: 'userEmail', label: t('pages.time_tracking.admin_operations.table.user_email'), hidden: true },
    { key: 'teamName', label: t('pages.time_tracking.admin_operations.table.team') },
    { key: 'sourceType', label: t('pages.time_tracking.admin_operations.table.source_type'), format: 'status-badge' },
    { key: 'sourcePublicId', label: t('pages.time_tracking.admin_operations.table.source_public_id'), hidden: true },
    { key: 'typeLabel', label: t('pages.time_tracking.admin_operations.table.type') },
    { key: 'status', label: t('pages.time_tracking.admin_operations.table.status'), format: 'status-badge' },
    { key: 'description', label: t('pages.time_tracking.admin_operations.table.description') },
    { key: 'requestedAt', label: t('pages.time_tracking.admin_operations.table.requested_at'), format: 'datetime' },
    { key: 'decidedAt', label: t('pages.time_tracking.admin_operations.table.decided_at'), format: 'datetime' },
    { key: 'proposalCount', label: t('pages.time_tracking.admin_operations.table.proposals'), format: 'number' },
    { key: 'historyCount', label: t('pages.time_tracking.admin_operations.table.history'), format: 'number' },
]);
const workSessionActions = computed<DataTableAction<LocalizedWorkSessionRow>[]>(() => [
    {
        key: 'details',
        label: t('pages.time_tracking.admin_operations.actions.details'),
        tone: 'info',
        href: (row) => `${sectionPath('work_sessions')}/${row.publicId}`,
        visible: () => canUseRoute('work-sessions.show'),
    },
    {
        key: 'terminate',
        label: t('pages.time_tracking.admin_operations.actions.terminate'),
        tone: 'danger',
        visible: (row) => row.endedAt === '' && canUseRoute('work-sessions.terminate'),
        onAction: (row) => openAction('terminate', row.publicId, row.userName),
    },
]);
const breakActions = computed<DataTableAction<LocalizedBreakRow>[]>(() => [
    {
        key: 'details',
        label: t('pages.time_tracking.admin_operations.actions.details'),
        tone: 'info',
        href: (row) => `${sectionPath('breaks')}/${row.publicId}`,
        visible: () => canUseRoute('breaks.show'),
    },
    {
        key: 'force_close',
        label: t('pages.time_tracking.admin_operations.actions.force_close'),
        tone: 'danger',
        visible: (row) => row.endedAt === '' && canUseRoute('breaks.force-close'),
        onAction: (row) => openAction('force_close_break', row.publicId, row.userName),
    },
    {
        key: 'convert_excess',
        label: t('pages.time_tracking.admin_operations.actions.convert_excess'),
        tone: 'warning',
        visible: (row) => canUseRoute('breaks.convert-excess') && row.availableActions.includes('convert_excess'),
        onAction: (row) => openAction('convert_excess_break', row.publicId, row.userName, row.excessBreakSeconds),
    },
]);
const otherWorkActions = computed<DataTableAction<LocalizedOtherWorkRow>[]>(() => [
    {
        key: 'details',
        label: t('pages.time_tracking.admin_operations.actions.details'),
        tone: 'info',
        href: (row) => `${sectionPath('other_work')}/${row.publicId}`,
        visible: () => canUseRoute('other-work.show'),
    },
    {
        key: 'force_close',
        label: t('pages.time_tracking.admin_operations.actions.force_close'),
        tone: 'danger',
        visible: (row) => row.endedAt === '' && canUseRoute('other-work.force-close'),
        onAction: (row) => openAction('force_close_other_work', row.publicId, row.userName),
    },
    {
        key: 'approve',
        label: t('pages.time_tracking.admin_operations.actions.approve'),
        tone: 'success',
        visible: (row) => canUseRoute('other-work.decide') && row.availableActions.includes('approve'),
        onAction: (row) => openAction('approve_other_work', row.publicId, row.userName),
    },
    {
        key: 'reject',
        label: t('pages.time_tracking.admin_operations.actions.reject'),
        tone: 'danger',
        visible: (row) => canUseRoute('other-work.decide') && row.availableActions.includes('reject'),
        onAction: (row) => openAction('reject_other_work', row.publicId, row.userName),
    },
]);
const correctionActions = computed<DataTableAction<LocalizedCorrectionRow>[]>(() => [
    {
        key: 'details',
        label: t('pages.time_tracking.admin_operations.actions.details'),
        tone: 'info',
        href: (row) => `${sectionPath('corrections')}/${row.publicId}`,
        visible: () => canUseRoute('corrections.show'),
    },
    {
        key: 'reject',
        label: t('pages.time_tracking.admin_operations.actions.reject'),
        tone: 'danger',
        visible: (row) => canUseRoute('corrections.decide') && row.availableActions.includes('reject'),
        onAction: (row) => openAction('reject', row.publicId, row.userName, undefined, row),
    },
    {
        key: 'correct',
        label: t('pages.time_tracking.admin_operations.actions.correct'),
        tone: 'warning',
        visible: (row) => canUseRoute('corrections.decide') && row.availableActions.includes('correct'),
        onAction: (row) => openAction('correct', row.publicId, row.userName, undefined, row),
    },
]);
const rangeOptions = computed<FormSelectOption[]>(() => timeTrackingRangeOptions(t));
const statusOptions = computed<FormSelectOption[]>(() => {
    const base = [{ value: 'all', label: t('pages.time_tracking.user_report.filters.any_status') }];

    if (props.section === 'other_work') {
        return [
            ...base,
            { value: 'pending', label: statusLabel('pending') },
            { value: 'under_review', label: statusLabel('under_review') },
            { value: 'approved', label: statusLabel('approved') },
            { value: 'rejected', label: statusLabel('rejected') },
            { value: 'cancelled', label: statusLabel('cancelled') },
        ];
    }

    if (props.section === 'corrections') {
        return [
            ...base,
            { value: 'pending', label: statusLabel('pending') },
            { value: 'approved', label: statusLabel('approved') },
            { value: 'corrected', label: statusLabel('corrected') },
            { value: 'rejected', label: statusLabel('rejected') },
            { value: 'cancelled', label: statusLabel('cancelled') },
        ];
    }

    if (props.section === 'breaks') {
        return [
            ...base,
            { value: 'open', label: statusLabel('open') },
            { value: 'closed', label: statusLabel('closed') },
            { value: 'under_review', label: statusLabel('under_review') },
        ];
    }

    return [...base, { value: 'open', label: statusLabel('open') }, { value: 'closed', label: statusLabel('closed') }];
});
const categoryOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.admin_operations.filters.any_category') },
    { value: '__none', label: t('pages.time_tracking.admin_operations.filters.no_category') },
    ...categoriesForSelectedTeam.value.map((category) => ({
        value: category.key,
        label: localizedCategoryLabel(category),
    })),
]);
const decisionStateOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.time_tracking.admin_operations.filters.any_decision_state') },
    { value: 'requires_manager_review', label: decisionStateLabel('requires_manager_review') },
    { value: 'final', label: decisionStateLabel('final') },
]);
const correctionTypeOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.time_tracking.admin_operations.filters.any_correction_type') },
    { value: 'descriptive', label: correctionTypeLabel('descriptive') },
    { value: 'exact_change', label: correctionTypeLabel('exact_change') },
    { value: 'manual_entry', label: correctionTypeLabel('manual_entry') },
    { value: 'closed_period_override', label: correctionTypeLabel('closed_period_override') },
]);
const closureReasonOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.time_tracking.admin_operations.filters.any_closure_reason') },
    { value: 'logout', label: statusLabel('logout') },
    { value: 'inactivity', label: statusLabel('inactivity') },
    { value: 'team_switched', label: statusLabel('team_switched') },
    { value: 'team_untracked', label: statusLabel('team_untracked') },
    { value: 'session_superseded', label: statusLabel('session_superseded') },
    { value: 'module_unavailable', label: statusLabel('module_unavailable') },
    { value: 'forced', label: statusLabel('forced') },
    { value: 'administrative_termination', label: statusLabel('administrative_termination') },
]);
const reviewOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.time_tracking.admin_operations.filters.any_review') },
    { value: 'requires_review', label: t('pages.time_tracking.admin_operations.filters.requires_review') },
    { value: 'pending_decision', label: t('pages.time_tracking.admin_operations.filters.pending_decision') },
    { value: 'maintenance_impact', label: t('pages.time_tracking.admin_operations.filters.maintenance_impact') },
]);
const compareOptions = computed<FormSelectOption[]>(() => timeTrackingCompareOptions(t));
const localizedDailyRows = computed<LocalizedDailyWorkTimeRow[]>(() =>
    props.dailyRows.map((row) => ({
        ...row,
        countedDuration: formatDuration(row.countedSeconds),
        workDuration: formatDuration(row.workSeconds),
        breakDuration: formatDuration(row.breakSeconds),
        technicalBreakDuration: formatDuration(row.technicalBreakSeconds),
        maintenanceDuration: formatDuration(row.maintenanceSeconds),
        otherWorkDuration: formatDuration(row.otherWorkSeconds),
        acceptedOtherWorkDuration: formatDuration(row.acceptedOtherWorkSeconds),
        pendingOtherWorkDuration: formatDuration(row.pendingOtherWorkSeconds),
        localizedSessionStatus: row.sessionStatus,
    })),
);
const localizedOtherWorkRows = computed<LocalizedOtherWorkRow[]>(() =>
    props.otherWorkRows.map((row) => ({
        ...row,
        category: localizedOtherWorkCategory(row),
        duration: formatDuration(row.exactSeconds),
        status: row.status,
        decisionState: row.decisionState,
        closureReason: row.closureReason === '' ? '' : statusLabel(row.closureReason),
    })),
);
const localizedWorkSessionRows = computed<LocalizedWorkSessionRow[]>(() =>
    props.workSessionRows.map((row) => ({
        ...row,
        duration: formatDuration(row.exactSeconds),
        closureReason: row.closureReason === '' ? '' : statusLabel(row.closureReason),
    })),
);
const localizedBreakRows = computed<LocalizedBreakRow[]>(() =>
    props.breakRows.map((row) => ({
        ...row,
        status: row.status,
        duration: formatDuration(row.exactSeconds),
        breakLimitLabel: row.breakLimitStatus,
        excessBreakDuration: formatDuration(row.excessBreakSeconds),
        closureReason: row.closureReason === '' ? '' : statusLabel(row.closureReason),
    })),
);
const localizedCorrectionRows = computed<LocalizedCorrectionRow[]>(() =>
    props.correctionRows.map((row) => ({
        ...row,
        typeLabel: correctionTypeLabel(row.type),
        sourceType: row.sourceType === '' ? 'none' : row.sourceType,
        status: row.status,
    })),
);
const actionTitle = computed(() => {
    const action = selectedAction.value?.kind;

    if (action === undefined) {
        return t('pages.time_tracking.admin_operations.dialog.title');
    }

    return t(`pages.time_tracking.admin_operations.dialog.${action}`);
});
watch(
    () => props.filters,
    () => {
        filters.value = { ...filterDefaults, ...props.filters };
    },
);
watch(
    () => filters.value.team,
    () => {
        if (filters.value.user !== '' && !usersForSelectedTeam.value.some((user) => user.publicId === filters.value.user)) {
            filters.value.user = '';
        }

        if (
            filters.value.category !== '' &&
            filters.value.category !== '__none' &&
            !categoriesForSelectedTeam.value.some((category) => category.key === filters.value.category)
        ) {
            filters.value.category = '';
        }
    },
);

function applyFilters(): void {
    applyTableFilters(sectionFilterKeys[props.section], filters.value, filterDefaults);
}

function clearFilters(): void {
    for (const key of sectionFilterKeys[props.section]) {
        filters.value[key] = filterDefaults[key];
    }

    clearTableFilters(sectionFilterKeys[props.section]);
}

function openAction(
    kind:
        | 'terminate'
        | 'force_close_break'
        | 'force_close_other_work'
        | 'approve_other_work'
        | 'reject_other_work'
        | 'reject'
        | 'correct'
        | 'convert_excess_break',
    publicId: string,
    subject: string,
    convertedSeconds?: number,
    correction?: LocalizedCorrectionRow,
): void {
    selectedAction.value = { kind, publicId, subject, convertedSeconds, correction };
    actionForm.defaults({
        decision: correctionDecision(kind),
        reason: '',
        converted_seconds: convertedSeconds === undefined ? '' : String(convertedSeconds),
        final_started_at: kind === 'correct' ? (correction?.proposedStartedAt ?? '') : '',
        final_ended_at: kind === 'correct' ? (correction?.proposedEndedAt ?? '') : '',
    });
    actionForm.reset();
    actionForm.clearErrors();
    actionModalOpen.value = true;
}

function closeActionModal(): void {
    actionModalOpen.value = false;
    selectedAction.value = null;
    actionForm.reset();
    actionForm.clearErrors();
}

function submitAction(): void {
    const action = selectedAction.value;

    if (action === null) {
        return;
    }

    actionForm.post(actionEndpoint(action), {
        preserveScroll: true,
        onSuccess: closeActionModal,
    });
}

function actionEndpoint(action: NonNullable<typeof selectedAction.value>): string {
    if (action.kind === 'terminate') {
        return `${sectionPath('work_sessions')}/${action.publicId}/terminate`;
    }

    if (action.kind === 'force_close_break') {
        return `${sectionPath('breaks')}/${action.publicId}/force-close`;
    }

    if (action.kind === 'convert_excess_break') {
        return `${sectionPath('breaks')}/${action.publicId}/convert-excess`;
    }

    if (action.kind === 'force_close_other_work') {
        return `${sectionPath('other_work')}/${action.publicId}/force-close`;
    }

    if (action.kind === 'approve_other_work' || action.kind === 'reject_other_work') {
        return `${sectionPath('other_work')}/${action.publicId}/decide`;
    }

    return `${sectionPath('corrections')}/${action.publicId}/decide`;
}

function correctionDecision(kind: string): string {
    if (kind === 'approve_other_work') {
        return 'approve';
    }

    if (kind === 'reject_other_work') {
        return 'reject';
    }

    if (kind === 'reject' || kind === 'correct') {
        return kind;
    }

    return '';
}

function canUseAdminRoute(routeName: string): boolean {
    return page.props.auth.availableAdminRoutes.includes(routeName);
}

function canUseRoute(suffix: string): boolean {
    const prefix = isManagerSurface.value ? 'manager.work-time.' : 'admin.work-time.';
    const routeName = `${prefix}${suffix}`;

    return isManagerSurface.value
        ? page.props.auth.availableApplicationRoutes.includes(routeName)
        : page.props.auth.availableAdminRoutes.includes(routeName);
}

function sectionPath(targetSection: AdminOperationsSection): string {
    const paths: Record<OperationsSurface, Record<AdminOperationsSection, string>> = {
        admin: {
            daily: '/admin/work-time/summary',
            other_work: '/admin/work-time/other-work',
            breaks: '/admin/work-time/breaks',
            corrections: '/admin/work-time/corrections',
            work_sessions: '/admin/work-time/work-sessions',
        },
        manager: {
            daily: '/manager/work-time/summary',
            other_work: '/manager/work-time/other-work',
            breaks: '/manager/work-time/breaks',
            corrections: '/manager/work-time/corrections',
            work_sessions: '/manager/work-time/work-sessions',
        },
    };

    return paths[surface.value][targetSection];
}

function routeWithFilters(path: string, targetSection: AdminOperationsSection): string {
    const params = new URLSearchParams();

    for (const key of sectionFilterKeys[targetSection]) {
        const value = filters.value[key];

        if (value !== undefined && value !== '' && value !== filterDefaults[key]) {
            params.set(key, value);
        }
    }

    const query = params.toString();

    return query === '' ? path : `${path}?${query}`;
}

function formatDuration(seconds: number): string {
    return formatTimeTrackingDuration(seconds, t);
}

function statusLabel(status: string): string {
    return timeTrackingStatusLabel(status, t);
}

function localizedOtherWorkCategory(row: OtherWorkRow): string {
    const label = locale.value === 'pl' ? row.categoryLabelPl : row.categoryLabelEn;

    return label === '' ? t('pages.time_tracking.other_work_lock.category.none') : label;
}

function localizedCategoryLabel(category: OtherWorkCategoryOption): string {
    const label = locale.value === 'pl' ? category.labelPl : category.labelEn;

    return label === '' ? category.key : label;
}

function decisionStateLabel(state: string): string {
    const key = `pages.time_tracking.user_report.decision_state.${state}`;
    const label = t(key);

    return label === key ? statusLabel(state) : label;
}

function correctionTypeLabel(type: string): string {
    const key = `pages.time_tracking.admin_operations.correction_types.${type}`;
    const label = t(key);

    return label === key ? type : label;
}

function exceededBreakRowClass(row: LocalizedBreakRow): string {
    return row.excessBreakSeconds > 0 || row.breakLimitStatus === 'exceeded'
        ? 'bg-rose-50/80 dark:bg-rose-950/25 [&>td]:!text-rose-950 dark:[&>td]:!text-rose-100'
        : '';
}

function correctionTimelineRows(
    correction: LocalizedCorrectionRow | undefined,
): Array<{ key: string; label: string; startedAt: string; endedAt: string; duration: string }> {
    if (correction === undefined) {
        return [];
    }

    return [
        {
            key: 'original',
            label: t('pages.time_tracking.admin_operations.dialog.original_values'),
            startedAt: correction.originalStartedAt,
            endedAt: correction.originalEndedAt,
            duration: correction.originalExactSeconds === null ? '' : formatDuration(correction.originalExactSeconds),
        },
        {
            key: 'proposed',
            label: t('pages.time_tracking.admin_operations.dialog.proposed_values'),
            startedAt: correction.proposedStartedAt,
            endedAt: correction.proposedEndedAt,
            duration: correction.proposedExactSeconds === null ? '' : formatDuration(correction.proposedExactSeconds),
        },
        {
            key: 'final',
            label: t('pages.time_tracking.admin_operations.dialog.current_final_values'),
            startedAt: correction.finalStartedAt,
            endedAt: correction.finalEndedAt,
            duration: correction.finalExactSeconds === null ? '' : formatDuration(correction.finalExactSeconds),
        },
    ].filter((row) => row.startedAt !== '' || row.endedAt !== '' || row.duration !== '');
}

function correctionTimestampLabel(value: string): string {
    return value === '' ? '-' : formatDateTime(value, locale.value);
}
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout
        :title="pageTitle"
        :title-icon="activeSectionMeta.icon"
        :mode="surface"
        :subnavigation="isAdminSurface ? subnavigation : []"
        :subnavigation-label="t('navigation.group.work_time')"
    >
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.total')"
                    :value="formatDuration(summary.totalSeconds)"
                    :icon="IconClockHour4"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.work')"
                    :value="formatDuration(summary.workSeconds)"
                    :icon="IconHourglass"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.break')"
                    :value="formatDuration(summary.breakSeconds)"
                    :icon="IconPlayerPause"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.other_work')"
                    :value="formatDuration(summary.otherWorkSeconds)"
                    :icon="IconBriefcase"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.admin_operations.metrics.work_sessions')"
                    :value="workSessionRows.length"
                    :icon="IconDatabase"
                    tone="zinc"
                />
                <OperationalMetricTile
                    :label="t('pages.time_tracking.user_report.metrics.pending')"
                    :value="summary.pending"
                    :icon="IconRefresh"
                    :tone="summary.pending > 0 ? 'rose' : 'zinc'"
                />
            </div>

            <FilterPanel
                :title="t('pages.time_tracking.user_report.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect
                        v-model="filters.team"
                        :label="t('pages.time_tracking.admin_operations.filters.team')"
                        :options="teamOptions"
                    />
                    <FormSelect
                        v-model="filters.user"
                        :label="t('pages.time_tracking.admin_operations.filters.user')"
                        :options="userSelectOptions"
                    />
                    <FormSelect
                        v-model="filters.range"
                        :label="t('pages.time_tracking.user_report.filters.range')"
                        :options="rangeOptions"
                    />
                    <FormDateInput v-model="filters.from" :label="t('pages.time_tracking.user_report.filters.from')" />
                    <FormDateInput v-model="filters.to" :label="t('pages.time_tracking.user_report.filters.to')" />
                    <FormSelect
                        v-if="section === 'other_work'"
                        v-model="filters.category"
                        :label="t('pages.time_tracking.admin_operations.filters.category')"
                        :options="categoryOptions"
                    />
                    <FormSelect
                        v-if="section === 'corrections'"
                        v-model="filters.correction_type"
                        :label="t('pages.time_tracking.admin_operations.filters.correction_type')"
                        :options="correctionTypeOptions"
                    />
                    <FormSelect
                        v-if="section !== 'daily'"
                        v-model="filters.status"
                        :label="t('pages.time_tracking.user_report.filters.status')"
                        :options="statusOptions"
                    />
                    <FormSelect
                        v-if="section === 'other_work'"
                        v-model="filters.decision_state"
                        :label="t('pages.time_tracking.admin_operations.filters.decision_state')"
                        :options="decisionStateOptions"
                    />
                    <FormSelect
                        v-if="section === 'other_work' || section === 'breaks' || section === 'work_sessions'"
                        v-model="filters.closure_reason"
                        :label="t('pages.time_tracking.admin_operations.filters.closure_reason')"
                        :options="closureReasonOptions"
                    />
                    <FormSelect
                        v-if="section === 'other_work' || section === 'breaks' || section === 'corrections'"
                        v-model="filters.review"
                        :label="t('pages.time_tracking.admin_operations.filters.review')"
                        :options="reviewOptions"
                    />
                    <FormSelect
                        v-if="section === 'daily'"
                        v-model="filters.compare"
                        :label="t('pages.time_tracking.user_report.filters.compare')"
                        :options="compareOptions"
                    />
                </div>
            </FilterPanel>

            <div v-if="section === 'other_work' && canUseRoute('other-work.categories.index')" class="flex justify-end">
                <ActionLink :href="`${sectionPath('other_work')}/categories`" :icon="IconPlus" tone="primary">
                    {{ t('pages.time_tracking.admin_operations.category.manage') }}
                </ActionLink>
            </div>

            <div
                v-if="isAdminSurface && section === 'corrections' && canUseAdminRoute('admin.work-time.corrections.manual-entry')"
                class="flex justify-end"
            >
                <ActionLink href="/admin/work-time/corrections/manual-entry" :icon="IconPlus" tone="primary">
                    {{ t('pages.time_tracking.admin_operations.actions.manual_entry') }}
                </ActionLink>
            </div>

            <DataTable
                v-if="section === 'daily'"
                :title="t('pages.time_tracking.admin_operations.daily_title')"
                :rows="localizedDailyRows"
                :columns="dailyColumns"
                row-key="publicId"
                :empty-label="
                    selectedTeamMissing
                        ? t('pages.time_tracking.admin_operations.empty.select_team')
                        : t('pages.time_tracking.admin_operations.empty.daily')
                "
                :total-rows="dailyTable.pagination.total"
                :ui-locale="locale"
                :table="dailyTable"
                :filters="filters"
            />
            <DataTable
                v-else-if="section === 'other_work'"
                :title="t('pages.time_tracking.admin_operations.other_work_title')"
                :rows="localizedOtherWorkRows"
                :columns="otherWorkColumns"
                row-key="publicId"
                :empty-label="
                    selectedTeamMissing
                        ? t('pages.time_tracking.admin_operations.empty.select_team')
                        : t('pages.time_tracking.admin_operations.empty.other_work')
                "
                :total-rows="otherWorkTable.pagination.total"
                :ui-locale="locale"
                :table="otherWorkTable"
                :filters="filters"
                :actions="otherWorkActions"
            />
            <DataTable
                v-else-if="section === 'breaks'"
                :title="t('pages.time_tracking.admin_operations.breaks_title')"
                :rows="localizedBreakRows"
                :columns="breakColumns"
                row-key="publicId"
                :empty-label="
                    selectedTeamMissing
                        ? t('pages.time_tracking.admin_operations.empty.select_team')
                        : t('pages.time_tracking.admin_operations.empty.breaks')
                "
                :total-rows="breaksTable.pagination.total"
                :ui-locale="locale"
                :table="breaksTable"
                :filters="filters"
                :actions="breakActions"
                :row-class="exceededBreakRowClass"
            />
            <DataTable
                v-else-if="section === 'corrections'"
                :title="t('pages.time_tracking.admin_operations.corrections_title')"
                :rows="localizedCorrectionRows"
                :columns="correctionColumns"
                row-key="publicId"
                :empty-label="
                    selectedTeamMissing
                        ? t('pages.time_tracking.admin_operations.empty.select_team')
                        : t('pages.time_tracking.admin_operations.empty.corrections')
                "
                :total-rows="correctionsTable.pagination.total"
                :ui-locale="locale"
                :table="correctionsTable"
                :filters="filters"
                :actions="correctionActions"
            />
            <DataTable
                v-else
                :title="t('pages.time_tracking.admin_operations.work_sessions_title')"
                :rows="localizedWorkSessionRows"
                :columns="workSessionColumns"
                row-key="publicId"
                :empty-label="
                    selectedTeamMissing
                        ? t('pages.time_tracking.admin_operations.empty.select_team')
                        : t('pages.time_tracking.admin_operations.empty.work_sessions')
                "
                :total-rows="workSessionsTable.pagination.total"
                :ui-locale="locale"
                :table="workSessionsTable"
                :filters="filters"
                :actions="workSessionActions"
            />
        </PageStack>

        <DialogPanel
            v-model:open="actionModalOpen"
            :title="actionTitle"
            :icon="IconTool"
            tone="rose"
            :close-label="t('modal.cancel')"
            @close="closeActionModal"
        >
            <AtlasForm :processing="actionForm.processing" @submit="submitAction">
                <div class="space-y-4">
                    <div v-if="selectedAction" class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                        {{ selectedAction.subject }}
                    </div>
                    <FormTextarea
                        v-model="actionForm.reason"
                        :label="t('pages.time_tracking.admin_operations.dialog.reason')"
                        :error="actionForm.errors.reason"
                    />
                    <div v-if="selectedAction?.kind === 'correct'" class="grid gap-3">
                        <div
                            v-if="correctionTimelineRows(selectedAction.correction).length > 0"
                            class="overflow-hidden rounded-md border border-zinc-200 dark:border-zinc-800"
                        >
                            <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
                                <thead
                                    class="bg-zinc-50 text-left text-xs font-semibold uppercase text-zinc-500 dark:bg-zinc-900 dark:text-zinc-400"
                                >
                                    <tr>
                                        <th class="px-3 py-2">{{ t('pages.time_tracking.admin_operations.dialog.values_kind') }}</th>
                                        <th class="px-3 py-2">{{ t('pages.time_tracking.admin_operations.manual.started_at') }}</th>
                                        <th class="px-3 py-2">{{ t('pages.time_tracking.admin_operations.manual.ended_at') }}</th>
                                        <th class="px-3 py-2">{{ t('pages.time_tracking.admin_operations.table.duration') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-zinc-100 dark:divide-zinc-900">
                                    <tr v-for="row in correctionTimelineRows(selectedAction.correction)" :key="row.key">
                                        <td class="px-3 py-2 font-medium text-zinc-950 dark:text-zinc-50">{{ row.label }}</td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                            {{ correctionTimestampLabel(row.startedAt) }}
                                        </td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                            {{ correctionTimestampLabel(row.endedAt) }}
                                        </td>
                                        <td class="px-3 py-2 text-zinc-600 dark:text-zinc-300">
                                            {{ row.duration === '' ? '-' : row.duration }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <FormDateTimeInput
                            v-model="actionForm.final_started_at"
                            :label="t('pages.time_tracking.admin_operations.manual.started_at')"
                            :error="actionForm.errors.final_started_at"
                        />
                        <FormDateTimeInput
                            v-model="actionForm.final_ended_at"
                            :label="t('pages.time_tracking.admin_operations.manual.ended_at')"
                            :error="actionForm.errors.final_ended_at"
                        />
                    </div>
                    <FormInput
                        v-if="selectedAction?.kind === 'convert_excess_break'"
                        v-model="actionForm.converted_seconds"
                        type="number"
                        min="1"
                        :label="t('pages.time_tracking.admin_operations.dialog.converted_seconds')"
                        :error="actionForm.errors.converted_seconds"
                    />
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.time_tracking.admin_operations.dialog.submit')"
                    :submit-icon="IconTool"
                    submit-tone="danger"
                    :loading="actionForm.processing"
                    @cancel="closeActionModal"
                />
            </AtlasForm>
        </DialogPanel>
    </AppLayout>
</template>
