<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconDatabaseCog, IconEraser, IconVideo } from '@tabler/icons-vue';

import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import OperationalMetricTile from '../../../Components/OperationalMetricTile.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useModal } from '../../../Composables/useModal';
import { useTranslator } from '../../../Localization/translator';

const props = defineProps<{
    recordingRetention: { retentionDays: number | null; eligible: number; oldestEndedAt: string | null };
}>();
const { t } = useTranslator();
const { confirm } = useModal();
const form = useForm<{ recording_retention_days: string }>({
    recording_retention_days: props.recordingRetention.retentionDays?.toString() ?? '',
});

function save(): void {
    form.transform((data) => ({
        recording_retention_days: data.recording_retention_days === '' ? null : Number(data.recording_retention_days),
    })).patch('/admin/chat/recording-retention');
}
async function runCleanup(): Promise<void> {
    if (
        !(await confirm({
            titleKey: 'pages.admin.chat_operations.confirm.title',
            descriptionKey: 'pages.admin.chat_operations.confirm.body',
            confirmKey: 'pages.admin.chat_operations.run',
            cancelKey: 'actions.cancel',
            tone: 'danger',
        }))
    ) {
        return;
    }
    router.post('/admin/chat/recording-retention/run');
}
</script>

<template>
    <Head :title="t('pages.admin.chat_operations.title')" />
    <AppLayout :title="t('pages.admin.chat_operations.title')" :title-icon="IconVideo" mode="admin">
        <PageStack>
            <div class="grid gap-4 sm:grid-cols-2">
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.retention_days')"
                    :value="recordingRetention.retentionDays ?? t('pages.admin.chat_operations.indefinite')"
                    :icon="IconDatabaseCog"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.eligible')"
                    :value="recordingRetention.eligible"
                    :icon="IconEraser"
                    :tone="recordingRetention.eligible > 0 ? 'amber' : 'emerald'"
                />
            </div>
            <SurfaceCard :title="t('pages.admin.chat_operations.policy')" :icon="IconDatabaseCog">
                <AtlasForm class="space-y-4" :processing="form.processing" @submit="save">
                    <FormInput
                        v-model="form.recording_retention_days"
                        type="number"
                        min="1"
                        max="3650"
                        :label="t('pages.admin.chat_operations.retention_days')"
                        :description="t('pages.admin.chat_operations.retention_help')"
                        :error="form.errors.recording_retention_days"
                    />
                    <div class="flex flex-wrap gap-2">
                        <FormButton type="submit" :loading="form.processing">{{ t('pages.admin.chat_operations.save') }}</FormButton>
                        <FormButton tone="neutral" type="button" @click="form.recording_retention_days = ''">
                            {{ t('pages.admin.chat_operations.retain_indefinitely') }}
                        </FormButton>
                        <FormButton tone="danger" type="button" @click="runCleanup">
                            {{ t('pages.admin.chat_operations.run') }}
                        </FormButton>
                    </div>
                </AtlasForm>
            </SurfaceCard>
        </PageStack>
    </AppLayout>
</template>
