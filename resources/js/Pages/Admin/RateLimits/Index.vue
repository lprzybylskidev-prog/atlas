<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconActivity, IconEraser, IconGauge, IconKey, IconListDetails, IconLock, IconShieldLock } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import DialogPanel from '../../../Components/DialogPanel.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import DialogFormActions from '../../../Components/Form/DialogFormActions.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import { optionsWithAll } from '../../../Utils/filterOptions';
import { formatStatus } from '../../../Utils/formatters';

interface PolicyOption {
    value: string;
    label: string;
}

interface RateLimitPolicyRow extends Record<string, unknown> {
    publicId: string;
    policy: string;
    policyFamily: string;
    maxAttempts: number;
    decaySeconds: number;
    keyParts: string;
    progressiveDelays: string;
    temporaryLockSeconds: number | null;
    hasProgressiveDelay: boolean;
    hasTemporaryLock: boolean;
    rejections: number;
    distinctKeys: number;
    lastRejectedAt: string | null;
}

interface RateLimitSummary {
    registered: number;
    visible: number;
    rejections: number;
    distinctKeys: number;
    withTemporaryLock: number;
    withProgressiveDelay: number;
}

const props = defineProps<{
    policies: RateLimitPolicyRow[];
    summary: RateLimitSummary;
    filterOptions: {
        families: string[];
        keyParts: string[];
    };
    table: DataTableMeta;
    policyOptions: PolicyOption[];
}>();

const { locale, t } = useTranslator();
const filterKeys = ['family', 'activity', 'key_part', 'progressive_delay', 'temporary_lock'];
const filterDefaults = {
    family: 'all',
    activity: 'all',
    key_part: 'all',
    progressive_delay: 'all',
    temporary_lock: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });
const resetModalOpen = ref(false);
const form = useForm<{
    policy: string;
    limiter_key: string;
    reason: string;
}>({
    policy: props.policyOptions[0]?.value ?? '',
    limiter_key: '',
    reason: '',
});

const columns = computed<DataTableColumn<RateLimitPolicyRow>[]>(() => [
    { key: 'policy', label: t('pages.admin.rate_limits.table.policy') },
    { key: 'policyFamily', label: t('pages.admin.rate_limits.table.family') },
    { key: 'maxAttempts', label: t('pages.admin.rate_limits.table.max_attempts'), format: 'number' },
    { key: 'decaySeconds', label: t('pages.admin.rate_limits.table.decay_seconds'), format: 'number' },
    { key: 'keyParts', label: t('pages.admin.rate_limits.table.key_parts') },
    { key: 'rejections', label: t('pages.admin.rate_limits.table.rejections'), format: 'number' },
    { key: 'distinctKeys', label: t('pages.admin.rate_limits.table.distinct_keys'), format: 'number' },
    { key: 'lastRejectedAt', label: t('pages.admin.rate_limits.table.last_rejected_at'), format: 'datetime' },
    { key: 'progressiveDelays', label: t('pages.admin.rate_limits.table.progressive_delays'), hidden: true },
    { key: 'temporaryLockSeconds', label: t('pages.admin.rate_limits.table.temporary_lock_seconds'), format: 'number', hidden: true },
    { key: 'hasProgressiveDelay', label: t('pages.admin.rate_limits.table.has_progressive_delay'), format: 'boolean', hidden: true },
    { key: 'hasTemporaryLock', label: t('pages.admin.rate_limits.table.has_temporary_lock'), format: 'boolean', hidden: true },
    { key: 'publicId', label: t('pages.admin.rate_limits.table.public_id'), hidden: true },
]);
const actions = computed<DataTableAction<RateLimitPolicyRow>[]>(() => [
    {
        key: 'reset',
        label: t('pages.admin.rate_limits.actions.reset_counter'),
        onAction: openResetModal,
        tone: 'danger',
    },
]);
const familyOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.families, t('pages.admin.rate_limits.filters.any_family')),
);
const activityOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.rate_limits.filters.any_activity') },
    { value: 'with_rejections', label: t('pages.admin.rate_limits.filters.with_rejections') },
    { value: 'without_rejections', label: t('pages.admin.rate_limits.filters.without_rejections') },
]);
const keyPartOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.keyParts, t('pages.admin.rate_limits.filters.any_key_part'), keyPartLabel),
);
const progressiveDelayOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.rate_limits.filters.any_progressive_delay') },
    { value: 'enabled', label: t('pages.admin.rate_limits.filters.with_progressive_delay') },
    { value: 'disabled', label: t('pages.admin.rate_limits.filters.without_progressive_delay') },
]);
const temporaryLockOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.rate_limits.filters.any_temporary_lock') },
    { value: 'enabled', label: t('pages.admin.rate_limits.filters.with_temporary_lock') },
    { value: 'disabled', label: t('pages.admin.rate_limits.filters.without_temporary_lock') },
]);
const tableFilters = computed(() => filterValues());

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

function filterValues(): Record<string, string> {
    return {
        family: String(props.table.state.filters?.family ?? 'all'),
        activity: String(props.table.state.filters?.activity ?? 'all'),
        key_part: String(props.table.state.filters?.key_part ?? 'all'),
        progressive_delay: String(props.table.state.filters?.progressive_delay ?? 'all'),
        temporary_lock: String(props.table.state.filters?.temporary_lock ?? 'all'),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function openResetModal(policy: RateLimitPolicyRow): void {
    form.defaults({
        policy: policy.policy,
        limiter_key: '',
        reason: '',
    });
    form.reset();
    form.clearErrors();
    resetModalOpen.value = true;
}

function closeResetModal(): void {
    resetModalOpen.value = false;
    form.reset();
    form.clearErrors();
}

function resetCounter(): void {
    form.post('/admin/rate-limits/reset', {
        preserveScroll: true,
        onSuccess: closeResetModal,
    });
}

function keyPartLabel(value: string): string {
    const keys: Record<string, string> = {
        api_client: 'pages.admin.rate_limits.key_parts.api_client',
        ip: 'pages.admin.rate_limits.key_parts.ip',
        team: 'pages.admin.rate_limits.key_parts.team',
        user: 'pages.admin.rate_limits.key_parts.user',
    };

    return keys[value] === undefined ? formatStatus(value) : t(keys[value]);
}
</script>

<template>
    <Head :title="t('pages.admin.rate_limits.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.rate_limits.title')" :title-icon="IconShieldLock">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.admin.rate_limits.metric.registered')"
                    :value="summary.registered"
                    :icon="IconGauge"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.rate_limits.metric.visible')"
                    :value="summary.visible"
                    :icon="IconListDetails"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.rate_limits.metric.rejections')"
                    :value="summary.rejections"
                    :icon="IconActivity"
                    :tone="summary.rejections > 0 ? 'amber' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.rate_limits.metric.distinct_keys')"
                    :value="summary.distinctKeys"
                    :icon="IconKey"
                    :tone="summary.distinctKeys > 0 ? 'amber' : 'zinc'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.rate_limits.metric.temporary_lock')"
                    :value="summary.withTemporaryLock"
                    :icon="IconLock"
                    tone="rose"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.rate_limits.metric.progressive_delay')"
                    :value="summary.withProgressiveDelay"
                    :icon="IconShieldLock"
                    tone="zinc"
                />
            </div>

            <FilterPanel
                :title="t('pages.admin.rate_limits.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect v-model="filters.family" :label="t('pages.admin.rate_limits.filters.family')" :options="familyOptions" />
                    <FormSelect
                        v-model="filters.activity"
                        :label="t('pages.admin.rate_limits.filters.activity')"
                        :options="activityOptions"
                    />
                    <FormSelect
                        v-model="filters.key_part"
                        :label="t('pages.admin.rate_limits.filters.key_part')"
                        :options="keyPartOptions"
                    />
                    <FormSelect
                        v-model="filters.progressive_delay"
                        :label="t('pages.admin.rate_limits.filters.progressive_delay')"
                        :options="progressiveDelayOptions"
                    />
                    <FormSelect
                        v-model="filters.temporary_lock"
                        :label="t('pages.admin.rate_limits.filters.temporary_lock')"
                        :options="temporaryLockOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.rate_limits.policies.title')"
                :rows="policies"
                :columns="columns"
                row-key="publicId"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.rate_limits.policies.empty')"
            />
        </PageStack>

        <DialogPanel
            v-model:open="resetModalOpen"
            :title="t('pages.admin.rate_limits.reset_modal.title')"
            :icon="IconEraser"
            tone="rose"
            :close-label="t('modal.cancel')"
            @close="closeResetModal"
        >
            <AtlasForm :processing="form.processing" @submit="resetCounter">
                <div class="space-y-4">
                    <FormSelect
                        v-model="form.policy"
                        :label="t('pages.admin.rate_limits.reset_modal.policy')"
                        :options="policyOptions"
                        :error="form.errors.policy"
                    />
                    <FormInput
                        v-model="form.limiter_key"
                        :label="t('pages.admin.rate_limits.reset_modal.limiter_key')"
                        :error="form.errors.limiter_key"
                    />
                    <FormTextarea
                        v-model="form.reason"
                        :label="t('pages.admin.rate_limits.reset_modal.reason')"
                        :placeholder="t('pages.admin.rate_limits.reset_modal.reason_placeholder')"
                        :error="form.errors.reason"
                    />
                </div>
                <DialogFormActions
                    :cancel-label="t('modal.cancel')"
                    :submit-label="t('pages.admin.rate_limits.actions.reset_counter')"
                    :submit-icon="IconEraser"
                    submit-tone="danger"
                    :loading="form.processing"
                    @cancel="closeResetModal"
                />
            </AtlasForm>
        </DialogPanel>
    </AppLayout>
</template>
