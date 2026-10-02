<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconCalendarEvent, IconPlus, IconVideo } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import FormButton from '../../Components/Form/FormButton.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormCheckbox from '../../Components/Form/FormCheckbox.vue';
import FormDateInput from '../../Components/Form/FormDateInput.vue';
import FormDateTimeInput from '../../Components/Form/FormDateTimeInput.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import FormTextarea from '../../Components/Form/FormTextarea.vue';
import PageStack from '../../Components/PageStack.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';

interface Meeting {
    publicId: string;
    title: string;
    startsAt: string;
    endsAt: string;
    mode: 'online' | 'in_person' | 'hybrid';
    location: string | null;
    status: string;
    response: string;
    recurring: boolean;
    organizer: string;
}
interface UserOption {
    publicId: string;
    name: string;
    email: string;
}
const props = defineProps<{ meetings: Meeting[]; users: UserOption[]; now: string }>();
const { t } = useTranslator();
const createOpen = ref(false);
const oneHourLater = (value: string): string => {
    const [datePart, timePart = '00:00'] = value.split('T');
    const [year, month, day] = datePart.split('-').map(Number);
    const [hour, minute] = timePart.split(':').map(Number);
    const date = new Date(year, month - 1, day, hour, minute);
    date.setHours(date.getHours() + 1);

    const pad = (part: number): string => String(part).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};
const form = useForm({
    title: '',
    description: '',
    starts_at: props.now,
    ends_at: oneHourLater(props.now),
    mode: 'online',
    location: '',
    invitee_public_ids: [] as string[],
    recurrence_frequency: '',
    recurrence_weekdays: [] as string[],
    recurrence_ends_on: '',
    recurrence_count: '',
    reminder_minutes: [15],
});
const modeOptions = computed<FormSelectOption[]>(() =>
    ['online', 'in_person', 'hybrid'].map((value) => ({ value, label: t(`meetings.modes.${value}`) })),
);
const recurrenceOptions = computed<FormSelectOption[]>(() => [
    { value: '', label: t('meetings.recurrence.none') },
    ...['daily', 'weekly', 'monthly'].map((value) => ({ value, label: t(`meetings.recurrence.${value}`) })),
]);
const weekdays = [
    { value: '1', key: 'monday' },
    { value: '2', key: 'tuesday' },
    { value: '3', key: 'wednesday' },
    { value: '4', key: 'thursday' },
    { value: '5', key: 'friday' },
    { value: '6', key: 'saturday' },
    { value: '7', key: 'sunday' },
];
function submit(): void {
    form.post('/meetings', { onSuccess: () => form.reset() });
}
function meetNow(): void {
    form.starts_at = props.now;
    form.ends_at = oneHourLater(form.starts_at);
    createOpen.value = true;
}
</script>
<template>
    <Head :title="t('pages.meetings.index.title')" /><AppLayout :title="t('pages.meetings.index.title')" :title-icon="IconVideo" mode="app">
        <PageStack>
            <div class="flex flex-wrap gap-2">
                <div class="contents">
                    <FormButton :icon="IconVideo" @click="meetNow">{{ t('meetings.actions.meet_now') }}</FormButton>
                </div>
                <div class="contents">
                    <FormButton tone="neutral" :icon="IconPlus" @click="createOpen = !createOpen">
                        {{ t('meetings.actions.schedule') }}
                    </FormButton>
                </div>
            </div>
            <SurfaceCard v-if="createOpen" :title="t('meetings.form.title')" :icon="IconCalendarEvent">
                <AtlasForm class="grid gap-4 md:grid-cols-2" :processing="form.processing" @submit="submit">
                    <FormDateTimeInput
                        v-model="form.starts_at"
                        :label="t('meetings.fields.starts_at')"
                        :error="form.errors.starts_at"
                    /><FormDateTimeInput v-model="form.ends_at" :label="t('meetings.fields.ends_at')" :error="form.errors.ends_at" />
                    <FormDateInput
                        v-if="form.recurrence_frequency"
                        v-model="form.recurrence_ends_on"
                        :label="t('meetings.fields.ends_on')"
                    />
                    <FormInput v-model="form.title" :label="t('meetings.fields.title')" :error="form.errors.title" /><FormSelect
                        v-model="form.mode"
                        :label="t('meetings.fields.mode')"
                        :options="modeOptions"
                        :error="form.errors.mode"
                    />
                    <div class="md:col-span-2">
                        <FormTextarea
                            v-model="form.description"
                            :label="t('meetings.fields.description')"
                            :error="form.errors.description"
                        />
                    </div>
                    <FormInput
                        v-if="form.mode !== 'online'"
                        v-model="form.location"
                        :label="t('meetings.fields.location')"
                        :error="form.errors.location"
                    />
                    <FormSelect v-model="form.recurrence_frequency" :label="t('meetings.fields.recurrence')" :options="recurrenceOptions" />
                    <fieldset v-if="form.recurrence_frequency === 'weekly'" class="grid gap-2 md:col-span-2 sm:grid-cols-2 lg:grid-cols-4">
                        <legend class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                            {{ t('pages.calendar.form.weekdays') }}
                        </legend>
                        <FormCheckbox
                            v-for="weekday in weekdays"
                            :key="weekday.value"
                            v-model="form.recurrence_weekdays"
                            :value="weekday.value"
                        >
                            {{ t(`pages.calendar.weekday.${weekday.key}`) }}
                        </FormCheckbox>
                    </fieldset>
                    <template v-if="form.recurrence_frequency">
                        <FormInput v-model="form.recurrence_count" type="number" :label="t('meetings.fields.count')" />
                    </template>
                    <fieldset class="grid gap-2 md:col-span-2">
                        <legend class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                            {{ t('meetings.fields.invitees') }}
                        </legend>
                        <FormCheckbox v-for="user in users" :key="user.publicId" v-model="form.invitee_public_ids" :value="user.publicId">
                            {{ user.name }} ({{ user.email }})
                        </FormCheckbox>
                    </fieldset>
                    <div class="md:col-span-2">
                        <FormButton type="submit" :loading="form.processing">{{ t('meetings.actions.create') }}</FormButton>
                    </div>
                </AtlasForm>
            </SurfaceCard>
            <div class="grid gap-3 lg:grid-cols-2">
                <div v-for="meeting in meetings" :key="meeting.publicId">
                    <SurfaceCard
                        :title="meeting.title"
                        :subtitle="meeting.organizer"
                        :icon="IconCalendarEvent"
                        :tone="meeting.status === 'cancelled' ? 'rose' : 'teal'"
                    >
                        <div class="space-y-2 text-sm">
                            <div class="flex flex-wrap gap-2">
                                <StatusBadge :label="t(`meetings.modes.${meeting.mode}`)" tone="info" /><StatusBadge
                                    :label="t(`meetings.responses.${meeting.response}`)"
                                    :tone="
                                        meeting.response === 'accepted' ? 'success' : meeting.response === 'declined' ? 'danger' : 'warning'
                                    "
                                /><StatusBadge
                                    v-if="meeting.status === 'cancelled'"
                                    :label="t('meetings.statuses.cancelled')"
                                    tone="danger"
                                />
                            </div>
                            <p>{{ new Date(meeting.startsAt).toLocaleString() }} – {{ new Date(meeting.endsAt).toLocaleString() }}</p>
                            <p v-if="meeting.location">{{ meeting.location }}</p>
                            <Link
                                class="font-medium text-teal-700 hover:underline dark:text-teal-300"
                                :href="`/meetings/${meeting.publicId}`"
                            >
                                {{ t('meetings.actions.details') }}
                            </Link>
                        </div>
                    </SurfaceCard>
                </div>
                <div v-if="meetings.length === 0">
                    <SurfaceCard :title="t('meetings.empty.title')" :icon="IconCalendarEvent">
                        <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ t('meetings.empty.body') }}</p>
                    </SurfaceCard>
                </div>
            </div>
        </PageStack>
    </AppLayout>
</template>
