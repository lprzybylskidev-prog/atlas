<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { IconFilePencil } from '@tabler/icons-vue';

import { useTranslator } from '../../Localization/translator';
import AtlasForm from '../Form/AtlasForm.vue';
import FormDateTimeInput from '../Form/FormDateTimeInput.vue';
import FormTextarea from '../Form/FormTextarea.vue';
import DialogFormActions from '../Form/DialogFormActions.vue';
import DialogPanel from '../DialogPanel.vue';

export interface TimeTrackingCorrectionSource {
    sourceType: string;
    sourcePublicId: string;
    subject: string;
}

const props = defineProps<{
    open: boolean;
    source: TimeTrackingCorrectionSource | null;
}>();
const emit = defineEmits<{
    'update:open': [value: boolean];
}>();
const { t } = useTranslator();
const form = useForm({
    source_type: '',
    source_public_id: '',
    description: '',
    proposed_started_at: '',
    proposed_ended_at: '',
});

function close(): void {
    form.clearErrors();
    emit('update:open', false);
}

function submit(): void {
    if (props.source === null) {
        return;
    }

    form.source_type = props.source.sourceType;
    form.source_public_id = props.source.sourcePublicId;
    form.post('/user/work-time/corrections', {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            close();
        },
    });
}
</script>

<template>
    <DialogPanel
        :open="open"
        :title="t('pages.time_tracking.user_report.correction_dialog.title')"
        :icon="IconFilePencil"
        tone="amber"
        size="2xl"
        :close-label="t('actions.close')"
        @update:open="emit('update:open', $event)"
        @close="close"
    >
        <AtlasForm :processing="form.processing" @submit="submit">
            <p v-if="source" class="mb-4 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ source.subject }}</p>
            <div class="grid gap-3">
                <FormTextarea
                    v-model="form.description"
                    :label="t('pages.time_tracking.user_report.correction_dialog.description')"
                    :error="form.errors.description"
                    :rows="4"
                />
                <div class="grid gap-4">
                    <FormDateTimeInput
                        v-model="form.proposed_started_at"
                        :label="t('pages.time_tracking.user_report.correction_dialog.proposed_started_at')"
                        :error="form.errors.proposed_started_at"
                    />
                    <FormDateTimeInput
                        v-model="form.proposed_ended_at"
                        :label="t('pages.time_tracking.user_report.correction_dialog.proposed_ended_at')"
                        :error="form.errors.proposed_ended_at"
                    />
                </div>
            </div>
            <DialogFormActions
                :cancel-label="t('actions.cancel')"
                :submit-label="t('pages.time_tracking.user_report.actions.submit_correction')"
                :submit-icon="IconFilePencil"
                submit-tone="primary"
                :loading="form.processing"
                @cancel="close"
            />
        </AtlasForm>
    </DialogPanel>
</template>
