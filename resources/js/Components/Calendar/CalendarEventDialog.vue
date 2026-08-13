<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';
import { IconCalendarEvent, IconDeviceFloppy, IconPlus, IconTrash } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import DialogPanel from '../DialogPanel.vue';
import FormCheckbox from '../Form/FormCheckbox.vue';
import FormDateInput from '../Form/FormDateInput.vue';
import FormDateTimeInput from '../Form/FormDateTimeInput.vue';
import GenericInput from '../Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../Form/FormSelect.vue';
import FormTextarea from '../Form/FormTextarea.vue';
import DialogFormActions from '../Form/DialogFormActions.vue';
import FormButton from '../Form/FormButton.vue';
import IconButton from '../IconButton.vue';
import { useModal } from '../../Composables/useModal';
import { useTranslator } from '../../Localization/translator';
import type { CalendarOccurrence } from '../../Types/calendar';

const props = defineProps<{
    open: boolean;
    event: CalendarOccurrence | null;
    initialDate: string;
    defaultReminderMinutes: number;
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useTranslator();
const { confirm } = useModal();
const reminderValues = ref<string[]>([]);
const form = useForm({
    title: '',
    description: '',
    starts_at: '',
    ends_at: '',
    all_day: false,
    location: '',
    availability: 'busy',
    recurrence_frequency: '',
    recurrence_interval: 1,
    recurrence_weekdays: [] as string[],
    recurrence_ends_on: '',
    recurrence_count: null as number | null,
    reminder_minutes: [] as number[],
    mutation_scope: 'series',
    occurrence_date: '',
    version: 1,
});
const deleteForm = useForm({
    occurrence_date: '',
    mutation_scope: 'series',
    version: 1,
});
const editing = computed(() => props.event !== null);
const canSubmit = computed(() => (editing.value ? props.canUpdate : props.canCreate));
const recurrenceOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('pages.calendar.recurrence.none') },
    { value: 'daily', label: t('pages.calendar.recurrence.daily') },
    { value: 'weekly', label: t('pages.calendar.recurrence.weekly') },
    { value: 'monthly', label: t('pages.calendar.recurrence.monthly') },
]);
const availabilityOptions = computed<FormSelectOption[]>(() => [
    { value: 'busy', label: t('pages.calendar.availability.busy') },
    { value: 'free', label: t('pages.calendar.availability.free') },
]);
const scopeOptions = computed<FormSelectOption[]>(() => [
    { value: 'occurrence', label: t('pages.calendar.scope.occurrence') },
    { value: 'future', label: t('pages.calendar.scope.future') },
    { value: 'series', label: t('pages.calendar.scope.series') },
]);
const weekdays = computed(() => [
    { value: 1, label: t('pages.calendar.weekday.monday') },
    { value: 2, label: t('pages.calendar.weekday.tuesday') },
    { value: 3, label: t('pages.calendar.weekday.wednesday') },
    { value: 4, label: t('pages.calendar.weekday.thursday') },
    { value: 5, label: t('pages.calendar.weekday.friday') },
    { value: 6, label: t('pages.calendar.weekday.saturday') },
    { value: 7, label: t('pages.calendar.weekday.sunday') },
]);

watch(
    () => [props.open, props.event, props.initialDate] as const,
    ([open]) => {
        if (!open) {
            return;
        }

        form.clearErrors();
        deleteForm.clearErrors();
        hydrate();
    },
    { immediate: true },
);

watch(
    () => form.all_day,
    (allDay) => {
        if (allDay) {
            form.starts_at = form.starts_at.slice(0, 10);
            form.ends_at = form.ends_at.slice(0, 10);
            return;
        }

        if (form.starts_at.length === 10) {
            form.starts_at = `${form.starts_at}T09:00:00`;
        }
        if (form.ends_at.length === 10) {
            form.ends_at = `${form.ends_at}T10:00:00`;
        }
    },
);

function hydrate(): void {
    const event = props.event;
    const startsAt = event?.startsAt.slice(0, 19) ?? `${props.initialDate}T09:00:00`;
    const endsAt = event?.endsAt.slice(0, 19) ?? `${props.initialDate}T10:00:00`;

    form.defaults({
        title: event?.title ?? '',
        description: event?.description ?? '',
        starts_at: startsAt,
        ends_at: endsAt,
        all_day: event?.allDay ?? false,
        location: event?.location ?? '',
        availability: event?.availability ?? 'busy',
        recurrence_frequency: event?.recurrenceFrequency ?? '',
        recurrence_interval: event?.recurrenceInterval ?? 1,
        recurrence_weekdays: event?.recurrenceWeekdays.map(String) ?? [],
        recurrence_ends_on: event?.recurrenceEndsOn ?? '',
        recurrence_count: event?.recurrenceCount ?? null,
        reminder_minutes: event?.reminderMinutes ?? [props.defaultReminderMinutes],
        mutation_scope: event?.recurring ? 'occurrence' : 'series',
        occurrence_date: event?.occurrenceDate ?? props.initialDate,
        version: event?.version ?? 1,
    });
    form.reset();
    reminderValues.value = form.reminder_minutes.map(String);
}

function submit(): void {
    form.reminder_minutes = reminderValues.value.map((value) => Number.parseInt(value, 10)).filter((value) => Number.isFinite(value));

    const options = {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    };

    if (props.event === null) {
        form.post('/calendar/events', options);
        return;
    }

    form.patch(`/calendar/events/${props.event.eventPublicId}`, options);
}

async function destroyEvent(): Promise<void> {
    const event = props.event;
    if (event === null || !props.canDelete) {
        return;
    }

    if (
        !(await confirm({
            titleKey: 'pages.calendar.delete.title',
            descriptionKey: event.recurring ? 'pages.calendar.delete.recurring_description' : 'pages.calendar.delete.description',
            confirmKey: 'pages.calendar.delete.confirm',
            cancelKey: 'actions.cancel',
            tone: 'danger',
            subject: event.title,
        }))
    ) {
        return;
    }

    deleteForm.occurrence_date = event.occurrenceDate;
    deleteForm.mutation_scope = form.mutation_scope;
    deleteForm.version = event.version;
    deleteForm.delete(`/calendar/events/${event.eventPublicId}`, {
        preserveScroll: true,
        onSuccess: () => emit('close'),
    });
}

function addReminder(): void {
    if (reminderValues.value.length < 10) {
        reminderValues.value.push('15');
    }
}

function removeReminder(index: number): void {
    reminderValues.value.splice(index, 1);
}
</script>

<template>
    <DialogPanel
        :open="open"
        :title="editing ? t('pages.calendar.form.edit_title') : t('pages.calendar.form.create_title')"
        :icon="IconCalendarEvent"
        size="4xl"
        :close-label="t('actions.close')"
        @close="emit('close')"
    >
        <form class="max-h-[75vh] space-y-5 overflow-y-auto pr-1" @submit.prevent="submit">
            <div class="grid gap-4 md:grid-cols-2">
                <GenericInput v-model="form.title" :label="t('pages.calendar.form.title')" :error="form.errors.title" />
                <GenericInput v-model="form.location" :label="t('pages.calendar.form.location')" :error="form.errors.location" />
            </div>

            <FormTextarea v-model="form.description" :label="t('pages.calendar.form.description')" :error="form.errors.description" />
            <FormCheckbox v-model="form.all_day" :label="t('pages.calendar.form.all_day')" />

            <div class="grid gap-4 md:grid-cols-2">
                <FormDateInput
                    v-if="form.all_day"
                    v-model="form.starts_at"
                    :label="t('pages.calendar.form.starts_at')"
                    :error="form.errors.starts_at"
                />
                <FormDateTimeInput
                    v-else
                    v-model="form.starts_at"
                    :label="t('pages.calendar.form.starts_at')"
                    :error="form.errors.starts_at"
                    :include-offset="false"
                />
                <FormDateInput
                    v-if="form.all_day"
                    v-model="form.ends_at"
                    :label="t('pages.calendar.form.ends_at')"
                    :error="form.errors.ends_at"
                />
                <FormDateTimeInput
                    v-else
                    v-model="form.ends_at"
                    :label="t('pages.calendar.form.ends_at')"
                    :error="form.errors.ends_at"
                    :include-offset="false"
                />
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <FormSelect v-model="form.availability" :label="t('pages.calendar.form.availability')" :options="availabilityOptions" />
                <FormSelect v-model="form.recurrence_frequency" :label="t('pages.calendar.form.recurrence')" :options="recurrenceOptions" />
            </div>

            <section v-if="form.recurrence_frequency" class="space-y-4 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="grid gap-4 md:grid-cols-3">
                    <GenericInput
                        :model-value="String(form.recurrence_interval)"
                        :label="t('pages.calendar.form.recurrence_interval')"
                        inputmode="numeric"
                        :error="form.errors.recurrence_interval"
                        @update:model-value="form.recurrence_interval = Number($event)"
                    />
                    <FormDateInput
                        v-model="form.recurrence_ends_on"
                        :label="t('pages.calendar.form.recurrence_ends_on')"
                        :error="form.errors.recurrence_ends_on"
                    />
                    <GenericInput
                        :model-value="form.recurrence_count === null ? '' : String(form.recurrence_count)"
                        :label="t('pages.calendar.form.recurrence_count')"
                        inputmode="numeric"
                        :error="form.errors.recurrence_count"
                        @update:model-value="form.recurrence_count = $event === '' ? null : Number($event)"
                    />
                </div>
                <div v-if="form.recurrence_frequency === 'weekly'">
                    <p class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ t('pages.calendar.form.weekdays') }}</p>
                    <div class="flex flex-wrap gap-3">
                        <FormCheckbox
                            v-for="weekday in weekdays"
                            :key="weekday.value"
                            v-model="form.recurrence_weekdays"
                            :value="String(weekday.value)"
                            :label="weekday.label"
                        />
                    </div>
                </div>
            </section>

            <section class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-800">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h3 class="font-semibold text-zinc-900 dark:text-zinc-50">{{ t('pages.calendar.form.reminders') }}</h3>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ t('pages.calendar.form.reminders_description') }}</p>
                    </div>
                    <FormButton type="button" tone="neutral" :icon="IconPlus" @click="addReminder">
                        {{ t('pages.calendar.actions.add_reminder') }}
                    </FormButton>
                </div>
                <div v-for="(reminder, index) in reminderValues" :key="index" class="flex items-end gap-2">
                    <GenericInput
                        v-model="reminderValues[index]"
                        class="flex-1"
                        :label="t('pages.calendar.form.reminder_minutes')"
                        inputmode="numeric"
                        :error="form.errors[`reminder_minutes.${index}`]"
                    />
                    <IconButton
                        :label="t('pages.calendar.actions.remove_reminder')"
                        :icon="IconTrash"
                        class="mb-0.5"
                        @click="removeReminder(index)"
                    />
                </div>
            </section>

            <FormSelect
                v-if="editing && event?.recurring"
                v-model="form.mutation_scope"
                :label="t('pages.calendar.form.mutation_scope')"
                :options="scopeOptions"
            />

            <div class="flex flex-wrap items-center justify-between gap-3">
                <FormButton
                    v-if="editing && canDelete"
                    type="button"
                    tone="danger"
                    :icon="IconTrash"
                    :loading="deleteForm.processing"
                    @click="destroyEvent"
                >
                    {{ t('pages.calendar.delete.confirm') }}
                </FormButton>
                <DialogFormActions
                    class="ml-auto"
                    :cancel-label="t('actions.cancel')"
                    :submit-label="editing ? t('pages.calendar.actions.save') : t('pages.calendar.actions.create')"
                    :submit-icon="IconDeviceFloppy"
                    :loading="form.processing"
                    :disabled="!canSubmit"
                    @cancel="emit('close')"
                />
            </div>
        </form>
    </DialogPanel>
</template>
