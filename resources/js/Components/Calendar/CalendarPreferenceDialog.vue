<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { IconBellCog, IconDeviceFloppy } from '@tabler/icons-vue';
import { watch } from 'vue';

import DialogPanel from '../DialogPanel.vue';
import DialogFormActions from '../Form/DialogFormActions.vue';
import FormCheckbox from '../Form/FormCheckbox.vue';
import FormInput from '../Form/FormInput.vue';
import { useTranslator } from '../../Localization/translator';
import type { CalendarPreference } from '../../Types/calendar';

const props = defineProps<{
    open: boolean;
    preference: CalendarPreference;
    canUpdate: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useTranslator();
const form = useForm({
    default_reminder_minutes: props.preference.defaultReminderMinutes,
    email_enabled: props.preference.emailEnabled,
});

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        form.defaults({
            default_reminder_minutes: props.preference.defaultReminderMinutes,
            email_enabled: props.preference.emailEnabled,
        });
        form.reset();
        form.clearErrors();
    },
);

function submit(): void {
    form.patch('/calendar/preferences', {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}
</script>

<template>
    <DialogPanel
        :open="open"
        :title="t('pages.calendar.preferences.title')"
        :icon="IconBellCog"
        size="lg"
        :close-label="t('actions.close')"
        @close="emit('close')"
    >
        <form class="space-y-4" @submit.prevent="submit">
            <FormInput
                :model-value="String(form.default_reminder_minutes)"
                :label="t('pages.calendar.preferences.default_minutes')"
                inputmode="numeric"
                :error="form.errors.default_reminder_minutes"
                @update:model-value="form.default_reminder_minutes = Number($event)"
            />
            <FormCheckbox v-model="form.email_enabled" :label="t('pages.calendar.preferences.email_enabled')" />
            <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ t('pages.calendar.preferences.email_description') }}</p>
            <DialogFormActions
                :cancel-label="t('actions.cancel')"
                :submit-label="t('pages.calendar.actions.save')"
                :submit-icon="IconDeviceFloppy"
                :loading="form.processing"
                :disabled="!canUpdate"
                @cancel="emit('close')"
            />
        </form>
    </DialogPanel>
</template>
