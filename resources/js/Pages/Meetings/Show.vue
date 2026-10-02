<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconCalendarEvent, IconMessage, IconUsers, IconVideo } from '@tabler/icons-vue';
import { computed } from 'vue';
import FormButton from '../../Components/Form/FormButton.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormDateInput from '../../Components/Form/FormDateInput.vue';
import FormDateTimeInput from '../../Components/Form/FormDateTimeInput.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import PageStack from '../../Components/PageStack.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useModal } from '../../Composables/useModal';
import { useTranslator } from '../../Localization/translator';
interface Participant {
    publicId: string;
    name: string;
    role: string;
    response: string;
}
interface UserOption {
    publicId: string;
    name: string;
    email: string;
}
interface Meeting {
    publicId: string;
    conversationPublicId: string;
    title: string;
    description: string | null;
    startsAt: string;
    endsAt: string;
    mode: string;
    location: string | null;
    status: string;
    recurring: boolean;
    organizer: string;
    role: string;
    response: string;
    canJoinOnline: boolean;
    recurrenceFrequency: string | null;
    recurrenceWeekdays: number[];
    recurrenceEndsOn: string | null;
    recurrenceCount: number | null;
    participants: Participant[];
}
const props = defineProps<{ meeting: Meeting; users: UserOption[] }>();
const { t } = useTranslator();
const { confirm } = useModal();
const invite = useForm({ user_public_id: '' });
const edit = useForm({
    title: props.meeting.title,
    description: props.meeting.description ?? '',
    starts_at: props.meeting.startsAt,
    ends_at: props.meeting.endsAt,
    mode: props.meeting.mode,
    location: props.meeting.location ?? '',
    invitee_public_ids: props.meeting.participants.filter((item) => item.role !== 'organizer').map((item) => item.publicId),
    recurrence_frequency: props.meeting.recurrenceFrequency ?? '',
    recurrence_weekdays: props.meeting.recurrenceWeekdays,
    recurrence_ends_on: props.meeting.recurrenceEndsOn ?? '',
    recurrence_count: props.meeting.recurrenceCount,
    reminder_minutes: [15],
    mutation_scope: 'series',
    occurrence_date: props.meeting.startsAt.slice(0, 10),
});
const options = computed<FormSelectOption[]>(() =>
    props.users
        .filter((user) => !props.meeting.participants.some((p) => p.publicId === user.publicId))
        .map((user) => ({ value: user.publicId, label: user.name, description: user.email })),
);
const modeOptions = computed<FormSelectOption[]>(() =>
    ['online', 'in_person', 'hybrid'].map((value) => ({ value, label: t(`meetings.modes.${value}`) })),
);
const scopeOptions = computed<FormSelectOption[]>(() =>
    ['occurrence', 'future', 'series'].map((value) => ({ value, label: t(`meetings.scopes.${value}`) })),
);
function respond(response: 'accepted' | 'declined'): void {
    router.patch(`/meetings/${props.meeting.publicId}/response`, { response });
}
async function cancel(): Promise<void> {
    if (
        !(await confirm({
            titleKey: 'meetings.confirm.cancel.title',
            descriptionKey: 'meetings.confirm.cancel.body',
            confirmKey: 'meetings.actions.cancel',
            cancelKey: 'actions.cancel',
            tone: 'danger',
            subject: props.meeting.title,
        }))
    ) {
        return;
    }
    router.post(`/meetings/${props.meeting.publicId}/cancel`, {
        mutation_scope: 'series',
        occurrence_date: props.meeting.startsAt.slice(0, 10),
    });
}
function add(): void {
    invite.post(`/meetings/${props.meeting.publicId}/invitations`, { onSuccess: () => invite.reset() });
}
function update(): void {
    edit.transform((data) => ({ ...data, location: data.mode === 'online' ? null : data.location })).patch(
        `/meetings/${props.meeting.publicId}`,
    );
}
async function remove(id: string): Promise<void> {
    const participant = props.meeting.participants.find((item) => item.publicId === id);
    if (
        !(await confirm({
            titleKey: 'meetings.confirm.remove.title',
            descriptionKey: 'meetings.confirm.remove.body',
            confirmKey: 'meetings.actions.remove',
            cancelKey: 'actions.cancel',
            tone: 'danger',
            subject: participant?.name,
        }))
    ) {
        return;
    }
    router.delete(`/meetings/${props.meeting.publicId}/participants/${id}`);
}
</script>
<template>
    <Head :title="meeting.title" /><AppLayout :title="meeting.title" :title-icon="IconVideo" mode="app">
        <PageStack>
            <SurfaceCard
                :title="meeting.title"
                :subtitle="meeting.organizer"
                :icon="IconCalendarEvent"
                :tone="meeting.status === 'cancelled' ? 'rose' : 'teal'"
            >
                <div class="space-y-3">
                    <div class="flex flex-wrap gap-2">
                        <StatusBadge :label="t(`meetings.modes.${meeting.mode}`)" tone="info" /><StatusBadge
                            :label="t(`meetings.responses.${meeting.response}`)"
                            tone="warning"
                        /><StatusBadge v-if="meeting.status === 'cancelled'" :label="t('meetings.statuses.cancelled')" tone="danger" />
                    </div>
                    <p>{{ new Date(meeting.startsAt).toLocaleString() }} – {{ new Date(meeting.endsAt).toLocaleString() }}</p>
                    <p v-if="meeting.description">{{ meeting.description }}</p>
                    <p v-if="meeting.location">{{ meeting.location }}</p>
                    <div v-if="meeting.status !== 'cancelled'" class="flex flex-wrap gap-2">
                        <div class="contents">
                            <FormButton @click="respond('accepted')">{{ t('meetings.actions.accept') }}</FormButton>
                        </div>
                        <div class="contents">
                            <FormButton tone="danger" @click="respond('declined')">{{ t('meetings.actions.decline') }}</FormButton>
                        </div>
                        <div v-if="meeting.canJoinOnline" class="contents">
                            <FormButton :icon="IconVideo" disabled>{{ t('meetings.actions.join_online') }}</FormButton>
                        </div>
                        <div v-if="meeting.role === 'organizer'" class="contents">
                            <FormButton tone="danger" @click="cancel">{{ t('meetings.actions.cancel') }}</FormButton>
                        </div>
                    </div>
                </div>
            </SurfaceCard>
            <SurfaceCard
                v-if="meeting.role === 'organizer' && meeting.status !== 'cancelled'"
                :title="t('meetings.edit.title')"
                :icon="IconCalendarEvent"
            >
                <AtlasForm class="grid gap-4 md:grid-cols-2" :processing="edit.processing" @submit="update">
                    <FormDateTimeInput v-model="edit.starts_at" :label="t('meetings.fields.starts_at')" :error="edit.errors.starts_at" />
                    <FormDateTimeInput v-model="edit.ends_at" :label="t('meetings.fields.ends_at')" :error="edit.errors.ends_at" />
                    <FormInput v-model="edit.title" :label="t('meetings.fields.title')" :error="edit.errors.title" />
                    <FormSelect v-model="edit.mode" :label="t('meetings.fields.mode')" :options="modeOptions" />
                    <FormInput v-if="edit.mode !== 'online'" v-model="edit.location" :label="t('meetings.fields.location')" />
                    <FormSelect v-model="edit.mutation_scope" :label="t('meetings.fields.scope')" :options="scopeOptions" />
                    <FormDateInput v-model="edit.occurrence_date" :label="t('meetings.fields.occurrence_date')" />
                    <div class="md:col-span-2">
                        <FormButton type="submit" :loading="edit.processing">{{ t('meetings.actions.save') }}</FormButton>
                    </div>
                </AtlasForm>
            </SurfaceCard>
            <SurfaceCard :title="t('meetings.participants.title')" :icon="IconUsers">
                <div class="space-y-3">
                    <div
                        v-for="participant in meeting.participants"
                        :key="participant.publicId"
                        class="flex items-center justify-between gap-3 border-b border-zinc-200 pb-2 dark:border-zinc-800"
                    >
                        <div>
                            <p class="font-medium">{{ participant.name }}</p>
                            <p class="text-xs text-zinc-500">
                                {{ t(`meetings.roles.${participant.role}`) }} · {{ t(`meetings.responses.${participant.response}`) }}
                            </p>
                        </div>
                        <FormButton
                            v-if="meeting.role === 'organizer' && participant.role !== 'organizer'"
                            tone="danger"
                            @click="remove(participant.publicId)"
                        >
                            {{ t('meetings.actions.remove') }}
                        </FormButton>
                    </div>
                    <div v-if="options.length" class="flex items-end gap-2">
                        <div class="max-w-md flex-1">
                            <FormSelect v-model="invite.user_public_id" :label="t('meetings.actions.invite')" :options="options" />
                        </div>
                        <FormButton :disabled="!invite.user_public_id" @click="add">{{ t('meetings.actions.invite') }}</FormButton>
                    </div>
                </div>
            </SurfaceCard>
            <SurfaceCard :title="t('meetings.chat.title')" :icon="IconMessage">
                <p class="text-sm text-zinc-600 dark:text-zinc-300">{{ t('meetings.chat.body') }}</p>
            </SurfaceCard>
        </PageStack>
    </AppLayout>
</template>
