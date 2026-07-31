<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconCalendarTime, IconListDetails, IconPlayerPlay } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DataTable from '../../../Components/DataTable.vue';
import DialogPanel from '../../../Components/DialogPanel.vue';
import FilterPanel from '../../../Components/FilterPanel.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormFileUpload from '../../../Components/Form/FormFileUpload.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import ManagedProcessArea from '../../../Components/ManagedProcesses/ManagedProcessArea.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import { allOptions, yesNoOptions } from '../../../Composables/useManagedProcessUi';
import { applyTableFilters, clearTableFilters } from '../../../Composables/useTableFilterControls';
import { useTranslator } from '../../../Localization/translator';
import type { DataTableAction, DataTableColumn, DataTableMeta } from '../../../Types/data-table';
import type { ManagedProcessDefinitionRow, ManagedProcessFilterOptions, ManagedProcessSummary } from '../../../Types/managed-processes';

const props = defineProps<{
    definitions: ManagedProcessDefinitionRow[];
    summary: ManagedProcessSummary;
    filterOptions: ManagedProcessFilterOptions;
    table: DataTableMeta;
}>();

const { locale, t } = useTranslator();
const filterKeys = ['module', 'queue', 'manual', 'schedule', 'risk'];
const filterDefaults = {
    module: 'all',
    queue: 'all',
    manual: 'all',
    schedule: 'all',
    risk: 'all',
};
const filters = ref({ ...filterDefaults, ...filterValues() });
const selectedDefinition = ref<ManagedProcessDefinitionRow | null>(null);
const runForm = useForm<{
    upload_file: File | null;
    watched_directory: string;
    idempotency_key: string;
}>({
    upload_file: null,
    watched_directory: '',
    idempotency_key: '',
});
const runModalOpen = computed({
    get: () => selectedDefinition.value !== null,
    set: (open: boolean) => {
        if (!open) {
            closeRunModal();
        }
    },
});
const selectedRequiresImportInput = computed(
    () => selectedDefinition.value?.supportsFileUpload === true || selectedDefinition.value?.supportsWatchedDirectory === true,
);

const columns = computed<DataTableColumn<ManagedProcessDefinitionRow>[]>(() => [
    { key: 'label', label: t('pages.admin.managed_processes.table.definition') },
    { key: 'key', label: t('pages.admin.managed_processes.key') },
    { key: 'moduleKey', label: t('pages.admin.managed_processes.module') },
    { key: 'scope', label: t('pages.admin.managed_processes.scope') },
    { key: 'queueName', label: t('pages.admin.managed_processes.queue') },
    { key: 'executionMode', label: t('pages.admin.managed_processes.mode') },
    { key: 'concurrencyPolicy', label: t('pages.admin.managed_processes.concurrency') },
    { key: 'retryable', label: t('pages.admin.managed_processes.retry'), format: 'boolean' },
    { key: 'scheduleSupported', label: t('pages.admin.managed_processes.schedule_support'), format: 'boolean' },
    { key: 'manualStartSupported', label: t('pages.admin.managed_processes.manual_start'), format: 'boolean', hidden: true },
    { key: 'cancellationPolicy', label: t('pages.admin.managed_processes.table.cancellation'), hidden: true },
    { key: 'externalEffects', label: t('pages.admin.managed_processes.table.external_effects'), format: 'boolean', hidden: true },
    { key: 'highRisk', label: t('pages.admin.managed_processes.table.high_risk'), format: 'boolean', hidden: true },
]);
const actions = computed<DataTableAction<ManagedProcessDefinitionRow>[]>(() => [
    {
        key: 'run',
        label: t('pages.admin.managed_processes.run'),
        onAction: openRunModal,
        tone: 'success',
        disabled: (definition) => definition.manualStartSupported !== true,
        disabledReason: t('pages.admin.managed_processes.action_disabled.manual_start_not_supported'),
    },
]);
const moduleOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.modules ?? [], t('pages.admin.managed_processes.filters.any_module')),
);
const queueOptions = computed<FormSelectOption[]>(() =>
    allOptions(props.filterOptions.queues ?? [], t('pages.admin.managed_processes.filters.any_queue')),
);
const booleanOptions = computed<FormSelectOption[]>(() => yesNoOptions(t));
const riskOptions = computed<FormSelectOption[]>(() => [
    { value: 'all', label: t('pages.admin.managed_processes.filters.any_risk') },
    { value: 'standard', label: t('pages.admin.managed_processes.risk.standard') },
    { value: 'external', label: t('pages.admin.managed_processes.risk.external') },
    { value: 'high', label: t('pages.admin.managed_processes.risk.high') },
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
        module: String(props.table.state.filters?.module ?? 'all'),
        queue: String(props.table.state.filters?.queue ?? 'all'),
        manual: String(props.table.state.filters?.manual ?? 'all'),
        schedule: String(props.table.state.filters?.schedule ?? 'all'),
        risk: String(props.table.state.filters?.risk ?? 'all'),
    };
}

function applyFilters(): void {
    applyTableFilters(filterKeys, filters.value, filterDefaults);
}

function clearFilters(): void {
    filters.value = { ...filterDefaults };
    clearTableFilters(filterKeys);
}

function openRunModal(definition: ManagedProcessDefinitionRow): void {
    selectedDefinition.value = definition;
    runForm.clearErrors();
}

function closeRunModal(): void {
    selectedDefinition.value = null;
    runForm.reset();
    runForm.clearErrors();
}

function startSelectedDefinition(): void {
    if (selectedDefinition.value === null) {
        return;
    }

    runForm.post(`/admin/managed-processes/definitions/${encodeURIComponent(selectedDefinition.value.key)}/run`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: closeRunModal,
    });
}
</script>

<template>
    <Head :title="t('pages.admin.managed_processes.definitions.head_title')" />
    <ManagedProcessArea :title="t('pages.admin.managed_processes.definitions.title')" current-path="/admin/managed-processes/definitions">
        <PageStack>
            <div class="grid gap-3 md:grid-cols-3">
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.definitions.metric.definitions')"
                    :value="summary.definitions ?? 0"
                    :icon="IconListDetails"
                    tone="teal"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.definitions.metric.manual')"
                    :value="summary.manual ?? 0"
                    :icon="IconPlayerPlay"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.managed_processes.definitions.metric.schedulable')"
                    :value="summary.schedulable ?? 0"
                    :icon="IconCalendarTime"
                    tone="amber"
                />
            </div>

            <FilterPanel
                :title="t('pages.admin.managed_processes.definitions.filters')"
                :apply-label="t('filters.apply')"
                :clear-label="t('filters.clear')"
                @apply="applyFilters"
                @clear="clearFilters"
            >
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-5">
                    <FormSelect v-model="filters.module" :label="t('pages.admin.managed_processes.module')" :options="moduleOptions" />
                    <FormSelect v-model="filters.queue" :label="t('pages.admin.managed_processes.queue')" :options="queueOptions" />
                    <FormSelect
                        v-model="filters.manual"
                        :label="t('pages.admin.managed_processes.manual_start')"
                        :options="booleanOptions"
                    />
                    <FormSelect
                        v-model="filters.schedule"
                        :label="t('pages.admin.managed_processes.schedule_support')"
                        :options="booleanOptions"
                    />
                    <FormSelect v-model="filters.risk" :label="t('pages.admin.managed_processes.table.risk')" :options="riskOptions" />
                </div>
            </FilterPanel>

            <DataTable
                :title="t('pages.admin.managed_processes.definitions.registered')"
                :rows="definitions"
                :columns="columns"
                row-key="key"
                :actions="actions"
                :table="table"
                :filters="tableFilters"
                :ui-locale="locale"
                :empty-label="t('pages.admin.managed_processes.definitions.empty')"
            />

            <DialogPanel
                v-model:open="runModalOpen"
                :title="selectedDefinition?.label ?? t('pages.admin.managed_processes.run')"
                :icon="IconPlayerPlay"
                tone="teal"
                :close-label="t('modal.cancel')"
            >
                <AtlasForm v-if="selectedDefinition" :processing="runForm.processing" @submit="startSelectedDefinition">
                    <p class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{ t('pages.admin.managed_processes.run_modal.description', { process: selectedDefinition.key }) }}
                    </p>
                    <div v-if="selectedRequiresImportInput" class="mt-4 grid gap-3">
                        <FormFileUpload
                            v-if="selectedDefinition.supportsFileUpload"
                            v-model="runForm.upload_file"
                            :label="t('pages.admin.managed_processes.upload_file')"
                            accept=".csv,text/csv,text/plain"
                            :error="runForm.errors.upload_file"
                        />
                        <FormInput
                            v-if="selectedDefinition.supportsWatchedDirectory"
                            v-model="runForm.watched_directory"
                            :label="t('pages.admin.managed_processes.watched_directory')"
                            :placeholder="t('pages.admin.managed_processes.placeholders.watched_directory')"
                            :error="runForm.errors.watched_directory"
                        />
                        <FormInput
                            v-model="runForm.idempotency_key"
                            :label="t('pages.admin.managed_processes.idempotency_key')"
                            :placeholder="t('pages.admin.managed_processes.placeholders.idempotency_key')"
                            :error="runForm.errors.idempotency_key"
                        />
                    </div>
                    <div class="mt-5 flex flex-wrap justify-end gap-2">
                        <FormButton type="button" tone="neutral" @click="closeRunModal">
                            {{ t('modal.cancel') }}
                        </FormButton>
                        <FormButton type="submit" :icon="IconPlayerPlay" :loading="runForm.processing">
                            {{ t('pages.admin.managed_processes.run') }}
                        </FormButton>
                    </div>
                </AtlasForm>
            </DialogPanel>
        </PageStack>
    </ManagedProcessArea>
</template>
