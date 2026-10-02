<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    IconCalendarEvent,
    IconDeviceDesktopShare,
    IconDownload,
    IconLock,
    IconMessage,
    IconMicrophone,
    IconMicrophoneOff,
    IconPhoneOff,
    IconPlayerPause,
    IconPlayerPlay,
    IconPlayerRecord,
    IconPlayerStop,
    IconUsers,
    IconVideo,
    IconVideoOff,
} from '@tabler/icons-vue';
import { computed, nextTick, onMounted, onUnmounted, ref } from 'vue';
import DialogPanel from '../../Components/DialogPanel.vue';
import ActionLink from '../../Components/ActionLink.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import AtlasForm from '../../Components/Form/AtlasForm.vue';
import FormDateInput from '../../Components/Form/FormDateInput.vue';
import FormDateTimeInput from '../../Components/Form/FormDateTimeInput.vue';
import FormInput from '../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../Components/Form/FormSelect.vue';
import PageStack from '../../Components/PageStack.vue';
import StatusBadge from '../../Components/StatusBadge.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import MediaDeviceSetup, { type MediaDevicePreparation } from '../../Components/Chat/MediaDeviceSetup.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useModal } from '../../Composables/useModal';
import { useTranslator } from '../../Localization/translator';
import { chatJson } from '../../Services/chatAttachments';
import type { CallMediaSession } from '../../Services/callMediaSession';
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
interface Attendance {
    publicId: string;
    name: string;
    joinedAt: string;
    leftAt: string | null;
    durationSeconds: number | null;
    occurrenceDate: string;
}
interface Meeting {
    publicId: string;
    conversationPublicId: string;
    title: string;
    description: string | null;
    startsAt: string;
    endsAt: string;
    mode: 'online' | 'in_person' | 'hybrid';
    location: string | null;
    status: string;
    recurring: boolean;
    organizer: string;
    role: string;
    response: string;
    canJoinOnline: boolean;
    canRejoinOnline: boolean;
    canManageRecording: boolean;
    recurrenceFrequency: string | null;
    recurrenceWeekdays: number[];
    recurrenceEndsOn: string | null;
    recurrenceCount: number | null;
    participants: Participant[];
    attendance: Attendance[];
}
interface MediaDeviceSetupHandle {
    prepare(requestedCamera?: boolean | null): Promise<MediaDevicePreparation>;
    stop(): void;
}
type RecordingStatus =
    | 'not_recording'
    | 'starting'
    | 'recording'
    | 'pausing'
    | 'paused'
    | 'resuming'
    | 'stopping'
    | 'processing'
    | 'ready'
    | 'failed'
    | 'removed';
interface RecordingState {
    publicId: string | null;
    status: RecordingStatus;
    startedAt: string | null;
    endedAt: string | null;
    durationSeconds: number | null;
    ready: boolean;
}
interface RecordingDetails {
    canShare: boolean;
    shares: { publicId: string; recipientPublicId: string; recipientName: string }[];
}
const props = defineProps<{ meeting: Meeting; users: UserOption[]; recording: RecordingState | null }>();
const { t } = useTranslator();
const { confirm } = useModal();
const invite = useForm({ user_public_id: '' });
const preCallOpen = ref(false);
const preCallBusy = ref(false);
const deviceSetup = ref<MediaDeviceSetupHandle | null>(null);
const meetingSession = ref<CallMediaSession | null>(null);
const rtcActive = ref(false);
const rtcMinimized = ref(false);
const cameraEnabled = ref(false);
const microphoneEnabled = ref(true);
const screenSharing = ref(false);
const remoteMedia = ref<HTMLDivElement | null>(null);
const recording = ref<RecordingState>(
    props.recording ?? {
        publicId: null,
        status: 'not_recording',
        startedAt: null,
        endedAt: null,
        durationSeconds: null,
        ready: false,
    },
);
const recordingBusy = ref(false);
const recordingDetails = ref<RecordingDetails | null>(null);
const shareRecipient = ref('');
const shareBusy = ref(false);
let recordingPoll: ReturnType<typeof setInterval> | null = null;
const canUseRtcSession = computed(() => props.meeting.canJoinOnline && props.meeting.mode !== 'in_person');
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
async function openPreCall(): Promise<void> {
    if (!canUseRtcSession.value) return;
    preCallOpen.value = true;
    preCallBusy.value = true;
    await nextTick();
    await deviceSetup.value?.prepare(null);
    preCallBusy.value = false;
}
function closePreCall(): void {
    if (rtcActive.value) {
        rtcMinimized.value = true;
        preCallOpen.value = false;
        return;
    }
    deviceSetup.value?.stop();
    preCallOpen.value = false;
}
async function joinOnline(): Promise<void> {
    const preparation = await deviceSetup.value?.prepare(null);
    if (!preparation) return;
    preCallBusy.value = true;
    try {
        const response = await chatJson<{ rtc: { serverUrl: string; participantToken: string }; session: unknown }>(
            `/meetings/${props.meeting.publicId}/rtc/join`,
            'POST',
            {
                occurrence_date: props.meeting.startsAt.slice(0, 10),
                camera_enabled: preparation.cameraEnabled,
                microphone_enabled: preparation.microphoneEnabled,
            },
        );
        const { CallMediaSession: MediaSession } = await import('../../Services/callMediaSession');
        const media = new MediaSession();
        media.onTrackSubscribed((element) => {
            element.autoplay = true;
            if (element instanceof HTMLVideoElement) element.playsInline = true;
            remoteMedia.value?.appendChild(element);
        });
        await media.connect(
            { ...response.rtc, roomName: '', expiresAt: '' },
            { camera: preparation.cameraEnabled, microphone: preparation.microphoneEnabled },
        );
        meetingSession.value = media;
        cameraEnabled.value = preparation.cameraEnabled;
        microphoneEnabled.value = preparation.microphoneEnabled;
        rtcActive.value = true;
        await refreshRecording();
        recordingPoll = setInterval(() => void refreshRecording(), 3000);
    } finally {
        preCallBusy.value = false;
    }
}
async function toggleMedia(kind: 'camera' | 'microphone'): Promise<void> {
    const next = kind === 'camera' ? !cameraEnabled.value : !microphoneEnabled.value;
    if (kind === 'camera') {
        await meetingSession.value?.camera(next);
        cameraEnabled.value = next;
    } else {
        await meetingSession.value?.microphone(next);
        microphoneEnabled.value = next;
    }
    await chatJson(`/meetings/${props.meeting.publicId}/rtc/media`, 'PATCH', {
        occurrence_date: props.meeting.startsAt.slice(0, 10),
        camera_enabled: cameraEnabled.value,
        microphone_enabled: microphoneEnabled.value,
    });
}
async function toggleScreenShare(): Promise<void> {
    const next = !screenSharing.value;
    await chatJson(
        `/meetings/${props.meeting.publicId}/rtc/screen-share?occurrence_date=${props.meeting.startsAt.slice(0, 10)}`,
        next ? 'POST' : 'DELETE',
    );
    await meetingSession.value?.screenShare(next);
    screenSharing.value = next;
}
async function leaveOnline(): Promise<void> {
    await chatJson(`/meetings/${props.meeting.publicId}/rtc/leave`, 'POST', { occurrence_date: props.meeting.startsAt.slice(0, 10) });
    meetingSession.value?.disconnect();
    meetingSession.value = null;
    rtcActive.value = false;
    if (recordingPoll !== null) clearInterval(recordingPoll);
    recordingPoll = null;
    rtcMinimized.value = false;
    closePreCall();
}
async function refreshRecording(): Promise<void> {
    if (!canUseRtcSession.value || !rtcActive.value) return;
    const response = await chatJson<{ recording: RecordingState }>(
        `/meetings/${props.meeting.publicId}/recording?occurrence_date=${props.meeting.startsAt.slice(0, 10)}`,
        'GET',
    );
    recording.value = response.recording;
    if (recording.value.status === 'ready') await refreshRecordingDetails();
}
async function refreshRecordingDetails(): Promise<void> {
    if (!recording.value.publicId || recording.value.status !== 'ready') return;
    const response = await chatJson<{ recording: RecordingDetails }>(`/meeting-recordings/${recording.value.publicId}`, 'GET');
    recordingDetails.value = response.recording;
}
async function shareRecording(): Promise<void> {
    if (!recording.value.publicId || !shareRecipient.value) return;
    shareBusy.value = true;
    try {
        await chatJson(`/meeting-recordings/${recording.value.publicId}/shares`, 'POST', { recipient_public_id: shareRecipient.value });
        shareRecipient.value = '';
        await refreshRecordingDetails();
    } finally {
        shareBusy.value = false;
    }
}
async function revokeRecordingShare(share: string): Promise<void> {
    if (!recording.value.publicId) return;
    await chatJson(`/meeting-recordings/${recording.value.publicId}/shares/${share}`, 'DELETE');
    await refreshRecordingDetails();
}
async function controlRecording(action: 'start' | 'pause' | 'resume' | 'stop'): Promise<void> {
    recordingBusy.value = true;
    try {
        const response = await chatJson<{ recording: RecordingState }>(`/meetings/${props.meeting.publicId}/recording/${action}`, 'POST', {
            occurrence_date: props.meeting.startsAt.slice(0, 10),
        });
        recording.value = response.recording;
    } finally {
        recordingBusy.value = false;
    }
}
onUnmounted(() => {
    if (recordingPoll !== null) clearInterval(recordingPoll);
});
onMounted(() => void refreshRecordingDetails());
async function moderate(
    participant: string,
    action: 'mute' | 'disable_microphone' | 'restore_microphone' | 'camera_off' | 'stop_screen_share' | 'kick',
): Promise<void> {
    await chatJson(`/meetings/${props.meeting.publicId}/rtc/participants/${participant}/moderate`, 'POST', {
        occurrence_date: props.meeting.startsAt.slice(0, 10),
        action,
    });
}
async function lockMeeting(locked: boolean): Promise<void> {
    await chatJson(`/meetings/${props.meeting.publicId}/rtc/lock`, 'PATCH', {
        occurrence_date: props.meeting.startsAt.slice(0, 10),
        locked,
    });
}
async function endMeeting(): Promise<void> {
    await chatJson(`/meetings/${props.meeting.publicId}/rtc/end`, 'POST', { occurrence_date: props.meeting.startsAt.slice(0, 10) });
    await leaveOnline();
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
                        <div v-if="canUseRtcSession" class="contents">
                            <FormButton :icon="IconVideo" @click="openPreCall">
                                {{ meeting.canRejoinOnline ? t('meetings.rtc.rejoin') : t('meetings.actions.join_online') }}
                            </FormButton>
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
                        <div
                            v-if="meeting.role === 'organizer' && participant.role !== 'organizer' && rtcActive"
                            class="flex flex-wrap gap-1"
                        >
                            <FormButton tone="neutral" @click="moderate(participant.publicId, 'mute')">
                                {{ t('meetings.rtc.mute') }}
                            </FormButton>
                            <FormButton tone="neutral" @click="moderate(participant.publicId, 'disable_microphone')">
                                {{ t('meetings.rtc.disable_microphone') }}
                            </FormButton>
                            <FormButton tone="neutral" @click="moderate(participant.publicId, 'restore_microphone')">
                                {{ t('meetings.rtc.restore_microphone') }}
                            </FormButton>
                            <FormButton tone="neutral" @click="moderate(participant.publicId, 'camera_off')">
                                {{ t('meetings.rtc.camera_off') }}
                            </FormButton>
                            <FormButton tone="neutral" @click="moderate(participant.publicId, 'stop_screen_share')">
                                {{ t('meetings.rtc.stop_share') }}
                            </FormButton>
                            <FormButton tone="danger" @click="moderate(participant.publicId, 'kick')">
                                {{ t('meetings.rtc.kick') }}
                            </FormButton>
                        </div>
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
            <SurfaceCard
                v-if="meeting.mode !== 'in_person' && recording.publicId"
                :title="t('meetings.recording.title')"
                :icon="IconPlayerRecord"
            >
                <div class="space-y-4">
                    <StatusBadge
                        :label="t(`meetings.recording.statuses.${recording.status}`)"
                        :tone="recording.status === 'ready' ? 'success' : recording.status === 'failed' ? 'danger' : 'warning'"
                    />
                    <p v-if="recording.status === 'processing'" class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{ t('meetings.recording.processing') }}
                    </p>
                    <template v-if="recording.status === 'ready'">
                        <video
                            class="w-full rounded-lg bg-black"
                            controls
                            preload="metadata"
                            :src="`/meeting-recordings/${recording.publicId}/download?preview=1`"
                        />
                        <ActionLink :href="`/meeting-recordings/${recording.publicId}/download`" :icon="IconDownload">
                            {{ t('meetings.recording.actions.download') }}
                        </ActionLink>
                        <div v-if="recordingDetails?.canShare" class="space-y-3 border-t border-zinc-200 pt-4 dark:border-zinc-800">
                            <div class="flex items-end gap-2">
                                <div class="max-w-md flex-1">
                                    <FormSelect
                                        v-model="shareRecipient"
                                        :label="t('meetings.recording.share.recipient')"
                                        :options="
                                            users
                                                .filter(
                                                    (user) =>
                                                        !meeting.participants.some((participant) => participant.publicId === user.publicId),
                                                )
                                                .map((user) => ({ value: user.publicId, label: user.name, description: user.email }))
                                        "
                                    />
                                </div>
                                <FormButton :loading="shareBusy" :disabled="!shareRecipient" @click="shareRecording">
                                    {{ t('meetings.recording.share.action') }}
                                </FormButton>
                            </div>
                            <ul v-if="recordingDetails.shares.length" class="space-y-2">
                                <li
                                    v-for="share in recordingDetails.shares"
                                    :key="share.publicId"
                                    class="flex items-center justify-between gap-3 text-sm"
                                >
                                    <span>{{ share.recipientName }}</span>
                                    <FormButton tone="danger" @click="revokeRecordingShare(share.publicId)">
                                        {{ t('meetings.recording.share.revoke') }}
                                    </FormButton>
                                </li>
                            </ul>
                        </div>
                    </template>
                </div>
            </SurfaceCard>
            <SurfaceCard v-if="meeting.attendance.length" :title="t('meetings.attendance.title')" :icon="IconUsers">
                <ul class="space-y-2">
                    <li
                        v-for="entry in meeting.attendance"
                        :key="`${entry.publicId}-${entry.joinedAt}`"
                        class="flex justify-between gap-3 text-sm"
                    >
                        <span>{{ entry.name }}</span>
                        <span>
                            {{ new Date(entry.joinedAt).toLocaleString() }} ·
                            {{
                                entry.durationSeconds === null
                                    ? t('meetings.attendance.active')
                                    : `${Math.floor(entry.durationSeconds / 60)} min`
                            }}
                        </span>
                    </li>
                </ul>
                <p class="mt-3 text-xs text-zinc-500 dark:text-zinc-400">{{ t('meetings.attendance.online_only') }}</p>
            </SurfaceCard>
        </PageStack>
        <aside
            v-if="rtcMinimized"
            class="fixed right-4 bottom-4 z-70 rounded-lg border bg-white p-3 shadow-xl dark:bg-zinc-950"
            data-testid="minimized-meeting-session"
        >
            <div class="flex gap-2">
                <FormButton
                    :icon="IconVideo"
                    @click="
                        rtcMinimized = false;
                        preCallOpen = true;
                    "
                >
                    {{ t('meetings.rtc.rejoin') }}
                </FormButton>
                <FormButton tone="danger" @click="leaveOnline">
                    {{ t('meetings.rtc.leave') }}
                </FormButton>
            </div>
        </aside>
        <DialogPanel
            v-if="canUseRtcSession"
            :open="preCallOpen"
            :title="t('meetings.pre_call.title')"
            :icon="IconVideo"
            size="3xl"
            :close-label="t('modal.close')"
            @update:open="closePreCall"
        >
            <div
                v-if="rtcActive && ['starting', 'recording', 'pausing', 'paused', 'resuming', 'stopping'].includes(recording.status)"
                class="mb-4 flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-semibold"
                :class="
                    recording.status === 'paused'
                        ? 'border-amber-400 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-200'
                        : 'border-rose-400 bg-rose-50 text-rose-800 dark:bg-rose-950/40 dark:text-rose-200'
                "
                role="status"
                aria-live="polite"
                data-testid="meeting-recording-state"
            >
                <span
                    class="h-2.5 w-2.5 rounded-full"
                    :class="recording.status === 'paused' ? 'bg-amber-500' : 'animate-pulse bg-rose-600'"
                />
                {{ recording.status === 'paused' ? t('meetings.recording.indicator.paused') : t('meetings.recording.indicator.active') }}
            </div>
            <p class="mb-4">{{ t('meetings.pre_call.description') }}</p>
            <MediaDeviceSetup ref="deviceSetup" test-id="meeting-preflight" @update:busy="preCallBusy = $event" />
            <template #actions>
                <FormButton v-if="!rtcActive" tone="neutral" :disabled="preCallBusy" @click="closePreCall">
                    {{ t('actions.close') }}
                </FormButton>
                <FormButton v-if="!rtcActive" :loading="preCallBusy" @click="joinOnline">{{ t('meetings.rtc.join') }}</FormButton>
                <template v-else>
                    <FormButton tone="neutral" :icon="cameraEnabled ? IconVideo : IconVideoOff" @click="toggleMedia('camera')">
                        {{ t('meetings.rtc.camera') }}
                    </FormButton>
                    <FormButton
                        tone="neutral"
                        :icon="microphoneEnabled ? IconMicrophone : IconMicrophoneOff"
                        @click="toggleMedia('microphone')"
                    >
                        {{ t('meetings.rtc.microphone') }}
                    </FormButton>
                    <FormButton tone="neutral" :icon="IconDeviceDesktopShare" @click="toggleScreenShare">
                        {{ t('meetings.rtc.share') }}
                    </FormButton>
                    <FormButton
                        v-if="meeting.canManageRecording && recording.status === 'not_recording'"
                        tone="danger"
                        :icon="IconPlayerRecord"
                        :loading="recordingBusy"
                        @click="controlRecording('start')"
                    >
                        {{ t('meetings.recording.actions.start') }}
                    </FormButton>
                    <FormButton
                        v-if="meeting.canManageRecording && recording.status === 'recording'"
                        tone="neutral"
                        :icon="IconPlayerPause"
                        :loading="recordingBusy"
                        @click="controlRecording('pause')"
                    >
                        {{ t('meetings.recording.actions.pause') }}
                    </FormButton>
                    <FormButton
                        v-if="meeting.canManageRecording && recording.status === 'paused'"
                        tone="danger"
                        :icon="IconPlayerPlay"
                        :loading="recordingBusy"
                        @click="controlRecording('resume')"
                    >
                        {{ t('meetings.recording.actions.resume') }}
                    </FormButton>
                    <FormButton
                        v-if="meeting.canManageRecording && ['recording', 'paused'].includes(recording.status)"
                        tone="neutral"
                        :icon="IconPlayerStop"
                        :loading="recordingBusy"
                        @click="controlRecording('stop')"
                    >
                        {{ t('meetings.recording.actions.stop') }}
                    </FormButton>
                    <FormButton tone="danger" :icon="IconPhoneOff" @click="leaveOnline">{{ t('meetings.rtc.leave') }}</FormButton>
                    <FormButton v-if="meeting.role === 'organizer'" tone="neutral" :icon="IconLock" @click="lockMeeting(true)">
                        {{ t('meetings.rtc.lock') }}
                    </FormButton>
                    <FormButton v-if="meeting.role === 'organizer'" tone="neutral" @click="lockMeeting(false)">
                        {{ t('meetings.rtc.unlock') }}
                    </FormButton>
                    <FormButton v-if="meeting.role === 'organizer'" tone="danger" @click="endMeeting">
                        {{ t('meetings.rtc.end') }}
                    </FormButton>
                </template>
            </template>
            <div v-if="rtcActive" ref="remoteMedia" class="mt-4 grid min-h-48 gap-3 rounded-lg bg-zinc-950 p-3" />
        </DialogPanel>
    </AppLayout>
</template>
