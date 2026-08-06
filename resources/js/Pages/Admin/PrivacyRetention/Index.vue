<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconDatabase, IconFingerprint, IconListDetails, IconLock, IconPlayerPlay, IconScale, IconShieldCheck } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import CodeViewer from '../../../Components/CodeViewer.vue';
import DialogPanel from '../../../Components/DialogPanel.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import DataTable from '../../../Components/DataTable.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../Components/Form/FormTextarea.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import { usePrivacyRetentionSubnavigation } from '../../../Composables/usePrivacyRetentionSubnavigation';
import type { DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import { optionsWithAll } from '../../../Utils/filterOptions';
import { formatStatus } from '../../../Utils/formatters';

interface PrivacyCoverageRow extends Record<string, unknown> {
    publicId: string;
    area: string;
    ownerModule: string;
    coverage: string;
    hardDeletePolicy: string;
    anonymizationPolicy: string;
    retentionControlled: boolean;
    hasParticipant: boolean;
}

interface PrivacyPreviewImpact {
    dataSet: string;
    estimatedRecords: number;
    irreversible: boolean;
    details: Record<string, unknown>[];
}

interface PrivacyPreviewBlocker {
    code: string;
    message: string;
}

interface LatestPrivacyPreview {
    publicId: string;
    operation: string;
    subjectType: string;
    subjectIdentifier: string;
    status: string;
    dryRun: boolean;
    reason: string;
    confirmationPhrase: string;
    previewedAt: string | null;
    impacts: PrivacyPreviewImpact[];
    blockers: PrivacyPreviewBlocker[];
    participantCount: number;
    estimatedRecords: number;
    canExecute: boolean;
}

interface PrivacySummary {
    areas: number;
    visible: number;
    implemented: number;
    partial: number;
    blockedHardDelete: number;
    participants: number;
}

const props = defineProps<{
    coverage: PrivacyCoverageRow[];
    summary: PrivacySummary;
    previewFormDefaults: {
        operation: 'hard_delete' | 'anonymization';
        subject_type: string;
        subject_identifier: string;
        reason: string;
        dry_run: boolean;
    };
    subjectTypeOptions: FormSelectOption[];
    autoSubmitPreview: boolean;
    filterOptions: {
        owners: string[];
        coverage: string[];
    };
    table: DataTableMeta;
    latestPreview: LatestPrivacyPreview | null;
}>();

const { locale, t } = useTranslator();
const subnavigation = usePrivacyRetentionSubnavigation('/admin/privacy-retention', t);
const previewResultModalOpen = ref(props.latestPreview !== null);
const impactDetailsModalOpen = ref(false);
const selectedImpact = ref<PrivacyPreviewImpact | null>(null);
const autoPreviewSubmitted = ref(false);
const filterKeys = ['owner', 'coverage', 'retention', 'participant'];
const filterDefaults = {
    owner: 'all',
    coverage: 'all',
    retention: 'all',
    participant: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });
const tableFilters = computed(() => filterValues());
const previewForm = useForm<{
    operation: 'hard_delete' | 'anonymization';
    subject_type: string;
    subject_identifier: string;
    reason: string;
    dry_run: boolean;
}>({
    operation: props.previewFormDefaults.operation,
    subject_type: props.previewFormDefaults.subject_type,
    subject_identifier: props.previewFormDefaults.subject_identifier,
    reason: props.previewFormDefaults.reason,
    dry_run: props.previewFormDefaults.dry_run,
});

const columns = computed<DataTableColumn<PrivacyCoverageRow>[]>(() => [
    { key: 'area', label: t('pages.admin.privacy_retention.table.area') },
    { key: 'ownerModule', label: t('pages.admin.privacy_retention.table.owner_module'), format: 'status' },
    { key: 'coverage', label: t('pages.admin.privacy_retention.table.coverage'), format: 'status' },
    { key: 'hardDeletePolicy', label: t('pages.admin.privacy_retention.table.hard_delete_policy'), format: 'status' },
    { key: 'anonymizationPolicy', label: t('pages.admin.privacy_retention.table.anonymization_policy'), format: 'status' },
    { key: 'retentionControlled', label: t('pages.admin.privacy_retention.table.retention_controlled'), format: 'boolean' },
    { key: 'hasParticipant', label: t('pages.admin.privacy_retention.table.participant'), format: 'boolean' },
    { key: 'publicId', label: t('pages.admin.privacy_retention.table.public_id'), hidden: true },
]);

const ownerOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.owners, t('pages.admin.privacy_retention.filters.any_owner'), ownerLabel),
);
const coverageOptions = computed<FormSelectOption[]>(() =>
    optionsWithAll(props.filterOptions.coverage, t('pages.admin.privacy_retention.filters.any_coverage'), coverageLabel),
);
const retentionOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.privacy_retention.filters.any_retention') },
    { value: 'controlled', label: t('pages.admin.privacy_retention.filters.retention_controlled') },
    { value: 'not_controlled', label: t('pages.admin.privacy_retention.filters.retention_not_controlled') },
]);
const participantOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.privacy_retention.filters.any_participant') },
    { value: 'registered', label: t('pages.admin.privacy_retention.filters.participant_registered') },
    { value: 'missing', label: t('pages.admin.privacy_retention.filters.participant_missing') },
]);
const operationOptions = computed<FormSelectOption[]>(() => [
    { value: 'hard_delete', label: t('pages.admin.privacy_retention.operation.hard_delete') },
    { value: 'anonymization', label: t('pages.admin.privacy_retention.operation.anonymization') },
]);
const visibleImpacts = computed<PrivacyPreviewImpact[]>(() =>
    (props.latestPreview?.impacts ?? []).filter((impact) => impact.estimatedRecords > 0),
);
const selectedImpactDetails = computed(() =>
    selectedImpact.value === null
        ? ''
        : JSON.stringify(
              {
                  dataSet: selectedImpact.value.dataSet,
                  estimatedRecords: selectedImpact.value.estimatedRecords,
                  irreversible: selectedImpact.value.irreversible,
                  records: selectedImpact.value.details,
              },
              null,
              2,
          ),
);

watch(
    () => props.table.state.filters,
    () => {
        filters.value = { ...filterDefaults, ...filterValues() };
    },
);

watch(
    () => props.latestPreview?.publicId ?? null,
    (publicId) => {
        previewResultModalOpen.value = publicId !== null;
    },
    { immediate: true },
);

watch(
    () => props.autoSubmitPreview,
    (autoSubmit) => {
        if (!autoSubmit || autoPreviewSubmitted.value || props.latestPreview !== null) {
            return;
        }

        autoPreviewSubmitted.value = true;
        preview();
    },
    { immediate: true },
);

function filterValues(): Record<string, string> {
    return {
        owner: String(props.table.state.filters?.owner ?? 'all'),
        coverage: String(props.table.state.filters?.coverage ?? 'all'),
        retention: String(props.table.state.filters?.retention ?? 'all'),
        participant: String(props.table.state.filters?.participant ?? 'all'),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function preview(): void {
    const endpoint =
        previewForm.operation === 'hard_delete'
            ? '/admin/privacy-retention/hard-delete/preview'
            : '/admin/privacy-retention/anonymization/preview';

    previewForm.post(endpoint, {
        preserveScroll: true,
    });
}

function openImpactDetails(impact: PrivacyPreviewImpact): void {
    selectedImpact.value = impact;
    impactDetailsModalOpen.value = true;
}

function closeImpactDetails(): void {
    impactDetailsModalOpen.value = false;
    selectedImpact.value = null;
}

function ownerLabel(value: string): string {
    const key = `pages.admin.privacy_retention.owner.${value}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function coverageLabel(value: string): string {
    const key = `pages.admin.privacy_retention.coverage.${value}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function policyLabel(value: string): string {
    const key = `pages.admin.privacy_retention.policy.${value}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function operationLabel(value: string): string {
    const key = `pages.admin.privacy_retention.operation.${value}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function statusLabel(value: string): string {
    const key = `pages.admin.privacy_retention.preview.status.${value}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function blockerCodeLabel(value: string): string {
    const key = `pages.admin.privacy_retention.blocker.${value}`;
    const translated = t(key);

    return translated === key ? formatStatus(value) : translated;
}

function blockerMessage(blocker: PrivacyPreviewBlocker): string {
    const key = `pages.admin.privacy_retention.blocker_message.${blocker.code}`;
    const translated = t(key);

    return translated === key ? blocker.message : translated;
}
</script>

<template>
    <Head :title="t('pages.admin.privacy_retention.head_title')" />
    <AppLayout
        mode="admin"
        :title="t('pages.admin.privacy_retention.title')"
        :title-icon="IconShieldCheck"
        :subnavigation="subnavigation"
        :subnavigation-label="t('pages.admin.privacy_retention.nav.label')"
    >
        <PageStack>
            <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.metric.areas')"
                    :value="summary.areas"
                    :icon="IconDatabase"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.metric.visible')"
                    :value="summary.visible"
                    :icon="IconListDetails"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.metric.implemented')"
                    :value="summary.implemented"
                    :icon="IconShieldCheck"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.metric.partial')"
                    :value="summary.partial"
                    :icon="IconFingerprint"
                    tone="amber"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.metric.blocked_hard_delete')"
                    :value="summary.blockedHardDelete"
                    :icon="IconLock"
                    tone="rose"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.privacy_retention.metric.participants')"
                    :value="summary.participants"
                    :icon="IconScale"
                    tone="zinc"
                />
            </div>

            <SurfaceCard :title="t('pages.admin.privacy_retention.preview_form.title')" :icon="IconPlayerPlay" tone="rose">
                <AtlasForm :processing="previewForm.processing" @submit="preview">
                    <div class="grid gap-3 md:grid-cols-2">
                        <FormSelect
                            v-model="previewForm.operation"
                            :label="t('pages.admin.privacy_retention.preview_form.operation')"
                            :options="operationOptions"
                            :error="previewForm.errors.operation"
                        />
                        <FormSelect
                            v-model="previewForm.subject_type"
                            :label="t('pages.admin.privacy_retention.preview_form.subject_type')"
                            :options="subjectTypeOptions"
                            :error="previewForm.errors.subject_type"
                        />
                        <FormInput
                            v-model="previewForm.subject_identifier"
                            :label="t('pages.admin.privacy_retention.preview_form.subject_identifier')"
                            :error="previewForm.errors.subject_identifier"
                        />
                        <FormInput
                            :model-value="t('pages.admin.privacy_retention.preview_form.dry_run_value')"
                            :label="t('pages.admin.privacy_retention.preview_form.dry_run')"
                            disabled
                        />
                        <FormTextarea
                            v-model="previewForm.reason"
                            class="md:col-span-2"
                            :label="t('pages.admin.privacy_retention.preview_form.reason')"
                            :placeholder="t('pages.admin.privacy_retention.preview_form.reason_placeholder')"
                            :error="previewForm.errors.reason"
                        />
                    </div>
                    <div class="mt-5 flex justify-end">
                        <FormButton type="submit" tone="danger" :icon="IconPlayerPlay" :loading="previewForm.processing">
                            {{ t('pages.admin.privacy_retention.preview_form.submit') }}
                        </FormButton>
                    </div>
                </AtlasForm>
            </SurfaceCard>

            <DialogPanel
                v-model:open="previewResultModalOpen"
                :title="t('pages.admin.privacy_retention.preview_result.title')"
                :icon="IconFingerprint"
                tone="amber"
                size="4xl"
                :close-label="t('modal.close')"
            >
                <div v-if="latestPreview" class="space-y-4">
                    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <div>
                            <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.privacy_retention.preview_result.operation') }}
                            </p>
                            <p class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                {{ operationLabel(latestPreview.operation) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.privacy_retention.preview_result.status') }}
                            </p>
                            <p class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                {{ statusLabel(latestPreview.status) }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.privacy_retention.preview_result.estimated_records') }}
                            </p>
                            <p class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                {{ latestPreview.estimatedRecords }}
                            </p>
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.privacy_retention.preview_result.participants') }}
                            </p>
                            <p class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                                {{ latestPreview.participantCount }}
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-zinc-200 bg-zinc-50 p-3 dark:border-zinc-800 dark:bg-zinc-900/60">
                        <p class="text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.privacy_retention.preview_result.subject') }}
                        </p>
                        <p class="mt-1 text-sm font-medium text-zinc-950 dark:text-zinc-50">
                            {{ latestPreview.subjectType }} / {{ latestPreview.subjectIdentifier }}
                        </p>
                        <p class="mt-3 text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                            {{ t('pages.admin.privacy_retention.preview_result.confirmation') }}
                        </p>
                        <p class="mt-1 font-mono text-sm text-zinc-950 dark:text-zinc-50">
                            {{ latestPreview.confirmationPhrase }}
                        </p>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <div class="space-y-3">
                            <p class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                {{ t('pages.admin.privacy_retention.preview_result.impacts') }}
                            </p>
                            <div class="grid gap-3">
                                <button
                                    v-for="impact in visibleImpacts"
                                    :key="`${impact.dataSet}-${impact.estimatedRecords}`"
                                    type="button"
                                    class="min-h-20 rounded-lg border border-zinc-200 p-3 text-left text-sm transition hover:border-teal-300 hover:bg-teal-50 focus-visible:outline focus-visible:outline-amber-500 dark:border-zinc-800 dark:hover:border-teal-800 dark:hover:bg-teal-950/30"
                                    :aria-label="
                                        t('pages.admin.privacy_retention.preview_result.open_impact_details', {
                                            dataSet: impact.dataSet,
                                        })
                                    "
                                    @click="openImpactDetails(impact)"
                                >
                                    <p class="break-words font-medium text-zinc-950 dark:text-zinc-50">{{ impact.dataSet }}</p>
                                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{
                                            t('pages.admin.privacy_retention.preview_result.impact_records', {
                                                count: impact.estimatedRecords,
                                            })
                                        }}
                                    </p>
                                </button>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <p class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                {{ t('pages.admin.privacy_retention.preview_result.blockers') }}
                            </p>
                            <div
                                v-for="blocker in latestPreview.blockers"
                                :key="`${blocker.code}-${blocker.message}`"
                                class="rounded-lg border border-rose-200 bg-rose-50 p-3 text-sm dark:border-rose-900 dark:bg-rose-950/40"
                            >
                                <p class="font-medium text-rose-800 dark:text-rose-200">{{ blockerCodeLabel(blocker.code) }}</p>
                                <p class="mt-1 text-xs text-rose-700 dark:text-rose-300">{{ blockerMessage(blocker) }}</p>
                            </div>
                            <p v-if="latestPreview.blockers.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ t('pages.admin.privacy_retention.preview_result.no_blockers') }}
                            </p>
                        </div>
                    </div>
                </div>
            </DialogPanel>

            <DialogPanel
                v-model:open="impactDetailsModalOpen"
                :title="selectedImpact?.dataSet ?? t('pages.admin.privacy_retention.preview_result.details_title')"
                :icon="IconListDetails"
                tone="sky"
                size="4xl"
                :close-label="t('modal.close')"
                @close="closeImpactDetails"
            >
                <CodeViewer
                    :content="selectedImpactDetails"
                    language="json"
                    max-height="max-h-[60vh]"
                    :copy-label="t('actions.copy')"
                    :copied-label="t('actions.copied')"
                    :wrap-label="t('actions.wrap_lines')"
                    :unwrap-label="t('actions.unwrap_lines')"
                />
            </DialogPanel>

            <FilterPanel
                :title="t('pages.admin.privacy_retention.filters.title')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <FormSelect v-model="filters.owner" :label="t('pages.admin.privacy_retention.filters.owner')" :options="ownerOptions" />
                    <FormSelect
                        v-model="filters.coverage"
                        :label="t('pages.admin.privacy_retention.filters.coverage')"
                        :options="coverageOptions"
                    />
                    <FormSelect
                        v-model="filters.retention"
                        :label="t('pages.admin.privacy_retention.filters.retention')"
                        :options="retentionOptions"
                    />
                    <FormSelect
                        v-model="filters.participant"
                        :label="t('pages.admin.privacy_retention.filters.participant')"
                        :options="participantOptions"
                    />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.privacy_retention.coverage_table.title')"
                :rows="
                    coverage.map((row) => ({
                        ...row,
                        ownerModule: ownerLabel(row.ownerModule),
                        coverage: coverageLabel(row.coverage),
                        hardDeletePolicy: policyLabel(row.hardDeletePolicy),
                        anonymizationPolicy: policyLabel(row.anonymizationPolicy),
                    }))
                "
                :columns="columns"
                row-key="publicId"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.privacy_retention.coverage_table.empty')"
            />
        </PageStack>
    </AppLayout>
</template>
