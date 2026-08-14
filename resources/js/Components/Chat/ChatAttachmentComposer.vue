<script setup lang="ts">
import { IconRefresh, IconSend, IconTrash } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, reactive, ref } from 'vue';

import { useTranslator } from '../../Localization/translator';
import { chatJson, type ChatAttachmentPayload, type ChatAttachmentState, uploadChatAttachment } from '../../Services/chatAttachments';
import FormAttachmentInput from '../Form/FormAttachmentInput.vue';
import FormButton from '../Form/FormButton.vue';
import FormVoiceRecorder from '../Form/FormVoiceRecorder.vue';
import StatusBadge from '../StatusBadge.vue';

interface UploadRow extends Partial<ChatAttachmentPayload> {
    localId: string;
    name: string;
    state: ChatAttachmentState;
    progress: number;
    error: string;
}

const props = defineProps<{ conversationPublicId: string; allowAttachments: boolean; allowVoice: boolean }>();
const emit = defineEmits<{ sent: [messagePublicId: string] }>();
const { t } = useTranslator();
const rows = ref<UploadRow[]>([]);
const sending = ref(false);
const sendError = ref('');
const timers = new Map<string, number>();
const base = computed(() => `/chat/conversations/${encodeURIComponent(props.conversationPublicId)}`);
const sendable = computed(() =>
    rows.value.filter(
        (row) => row.publicId && !row.attached && row.state !== 'uploading' && !['infected', 'failed', 'unsupported'].includes(row.state),
    ),
);

async function addFiles(files: File[]): Promise<void> {
    await Promise.all(files.map((file) => upload(file, null)));
}

async function addVoice(blob: Blob, durationSeconds: number): Promise<void> {
    const extension = blob.type.includes('ogg') ? 'ogg' : blob.type.includes('wav') ? 'wav' : blob.type.includes('mp4') ? 'm4a' : 'webm';
    const file = new File([blob], `voice-message.${extension}`, { type: blob.type || 'audio/webm' });
    const row = await upload(file, durationSeconds);
    if (row?.publicId) await send([row.publicId]);
}

async function upload(file: File, durationSeconds: number | null): Promise<UploadRow | null> {
    const row = reactive<UploadRow>({
        localId: crypto.randomUUID(),
        name: file.name,
        state: 'uploading',
        progress: 0,
        error: '',
    });
    rows.value.push(row);
    try {
        const url = durationSeconds === null ? `${base.value}/attachments` : `${base.value}/voice-messages`;
        const payload = await uploadChatAttachment(url, file, durationSeconds, (progress) => (row.progress = progress));
        Object.assign(row, payload, { state: payload.scanState, progress: 100 });
        scheduleStatus(row);
        return row;
    } catch {
        row.state = 'failed';
        row.error = t('chat.attachments.upload_failed');
        return null;
    }
}

function scheduleStatus(row: UploadRow): void {
    if (!row.publicId || !['pending', 'scanning'].includes(row.state)) return;
    const timer = window.setTimeout(async () => {
        timers.delete(row.localId);
        try {
            const payload = await chatJson<ChatAttachmentPayload>(`${base.value}/attachments/${encodeURIComponent(row.publicId!)}`);
            Object.assign(row, payload, { state: payload.scanState });
            scheduleStatus(row);
        } catch {
            row.error = t('chat.attachments.status_failed');
        }
    }, 1500);
    timers.set(row.localId, timer);
}

async function retry(row: UploadRow): Promise<void> {
    if (!row.publicId) return;
    row.error = '';
    try {
        const payload = await chatJson<ChatAttachmentPayload>(
            `${base.value}/attachments/${encodeURIComponent(row.publicId)}/retry`,
            'POST',
            {},
        );
        Object.assign(row, payload, { state: payload.scanState });
        scheduleStatus(row);
    } catch {
        row.error = t('chat.attachments.retry_failed');
    }
}

async function discard(row: UploadRow): Promise<void> {
    if (row.publicId && !row.attached) {
        await chatJson(`${base.value}/attachments/${encodeURIComponent(row.publicId)}`, 'DELETE');
    }
    const timer = timers.get(row.localId);
    if (timer !== undefined) window.clearTimeout(timer);
    timers.delete(row.localId);
    rows.value = rows.value.filter((candidate) => candidate.localId !== row.localId);
}

async function send(publicIds = sendable.value.map((row) => row.publicId!)): Promise<void> {
    if (publicIds.length === 0 || sending.value) return;
    sending.value = true;
    sendError.value = '';
    try {
        const message = await chatJson<{ publicId: string }>(`${base.value}/messages`, 'POST', {
            body: '',
            client_message_key: crypto.randomUUID(),
            attachment_public_ids: publicIds,
        });
        rows.value.forEach((row) => {
            if (row.publicId && publicIds.includes(row.publicId)) row.attached = true;
        });
        emit('sent', message.publicId);
    } catch {
        sendError.value = t('chat.attachments.send_failed');
    } finally {
        sending.value = false;
    }
}

function stateLabel(state: ChatAttachmentState): string {
    return t(`chat.attachments.state.${state}`);
}

onBeforeUnmount(() => timers.forEach((timer) => window.clearTimeout(timer)));
</script>

<template>
    <div class="space-y-4">
        <FormAttachmentInput v-if="allowAttachments" :disabled="sending" @selected="addFiles" />
        <FormVoiceRecorder v-if="allowVoice" :disabled="sending" :max-seconds="900" @send="addVoice" />

        <ul v-if="rows.length" class="space-y-2" :aria-label="t('chat.attachments.queue')" aria-live="polite">
            <li v-for="row in rows" :key="row.localId" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="min-w-0 flex-1 truncate text-sm font-medium">{{ row.name }}</span>
                    <StatusBadge :value="row.state" :label="stateLabel(row.state)" />
                    <FormButton
                        v-if="!row.attached && ['failed', 'pending'].includes(row.state) && row.publicId"
                        tone="neutral"
                        :icon="IconRefresh"
                        @click="retry(row)"
                    >
                        {{ t('chat.attachments.retry') }}
                    </FormButton>
                    <FormButton v-if="!row.attached && row.state !== 'uploading'" tone="danger" :icon="IconTrash" @click="discard(row)">
                        {{ t('chat.attachments.discard') }}
                    </FormButton>
                </div>
                <div
                    v-if="row.state === 'uploading'"
                    class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-800"
                    role="progressbar"
                    :aria-valuenow="row.progress"
                    aria-valuemin="0"
                    aria-valuemax="100"
                >
                    <div class="h-full bg-teal-600 transition-all" :style="{ width: `${row.progress}%` }" />
                </div>
                <p v-if="row.error" role="alert" class="mt-2 text-sm text-rose-700 dark:text-rose-300">{{ row.error }}</p>
            </li>
        </ul>

        <div v-if="sendable.length" class="flex justify-end">
            <FormButton :icon="IconSend" :loading="sending" @click="send()">{{ t('chat.attachments.send') }}</FormButton>
        </div>
        <p v-if="sendError" role="alert" class="text-sm text-rose-700 dark:text-rose-300">{{ sendError }}</p>
    </div>
</template>
