<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconCalendarPlus, IconDeviceFloppy } from '@tabler/icons-vue';
import { computed } from 'vue';

import AtlasForm from '../../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../../Components/Form/FormButton.vue';
import FormInput from '../../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../../Components/Form/FormSelect.vue';
import FormTextarea from '../../../../Components/Form/FormTextarea.vue';
import ManagedProcessArea from '../../../../Components/ManagedProcesses/ManagedProcessArea.vue';
import PageStack from '../../../../Components/PageStack.vue';
import SurfaceCard from '../../../../Components/SurfaceCard.vue';
import { useTranslator } from '../../../../Localization/translator';
import type { ManagedProcessDefinitionRow } from '../../../../Types/managed-processes';

const props = defineProps<{
    definitions: ManagedProcessDefinitionRow[];
}>();

const { t } = useTranslator();
const form = useForm({
    process_key: '',
    cron_expression: '',
    watched_directory: '',
    idempotency_key: '',
    reason: '',
});
const definitionOptions = computed<FormSelectOption[]>(() =>
    props.definitions.map((definition) => ({
        value: definition.key,
        label: `${definition.label} (${definition.key})`,
    })),
);
const selectedDefinition = computed(() => props.definitions.find((definition) => definition.key === form.process_key) ?? null);

function createSchedule(): void {
    form.post('/admin/managed-processes/schedules', {
        preserveScroll: true,
    });
}
</script>

<template>
    <Head :title="t('pages.admin.managed_processes.schedules.create_head_title')" />
    <ManagedProcessArea
        :title="t('pages.admin.managed_processes.schedules.create')"
        current-path="/admin/managed-processes/schedules/create"
    >
        <PageStack>
            <SurfaceCard :title="t('pages.admin.managed_processes.schedules.create')" :icon="IconCalendarPlus" tone="amber">
                <AtlasForm :processing="form.processing" @submit="createSchedule">
                    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(12rem,16rem)]">
                        <FormSelect
                            v-model="form.process_key"
                            :label="t('pages.admin.managed_processes.process')"
                            :options="definitionOptions"
                            :placeholder="t('pages.admin.managed_processes.placeholders.select_definition')"
                            :error="form.errors.process_key"
                        />
                        <FormInput
                            v-model="form.cron_expression"
                            :label="t('pages.admin.managed_processes.cron_expression')"
                            :placeholder="t('pages.admin.managed_processes.placeholders.cron')"
                            :error="form.errors.cron_expression"
                        />
                    </div>
                    <div v-if="selectedDefinition?.supportsWatchedDirectory" class="mt-3 grid gap-3 lg:grid-cols-2">
                        <FormInput
                            v-model="form.watched_directory"
                            :label="t('pages.admin.managed_processes.watched_directory')"
                            :placeholder="t('pages.admin.managed_processes.placeholders.watched_directory')"
                            :error="form.errors.watched_directory"
                        />
                        <FormInput
                            v-model="form.idempotency_key"
                            :label="t('pages.admin.managed_processes.idempotency_key')"
                            :placeholder="t('pages.admin.managed_processes.placeholders.idempotency_key')"
                            :error="form.errors.idempotency_key"
                        />
                    </div>
                    <div class="mt-3">
                        <FormTextarea
                            v-model="form.reason"
                            :label="t('pages.admin.managed_processes.reason')"
                            :placeholder="t('pages.admin.managed_processes.placeholders.schedule_reason')"
                            :error="form.errors.reason"
                        />
                    </div>
                    <div class="mt-4 flex justify-end">
                        <FormButton type="submit" :icon="IconDeviceFloppy" :loading="form.processing">
                            {{ t('pages.admin.managed_processes.schedules.create') }}
                        </FormButton>
                    </div>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </ManagedProcessArea>
</template>
