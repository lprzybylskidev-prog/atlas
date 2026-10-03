<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconDatabaseCog, IconEraser, IconMessage, IconPhone, IconVideo } from '@tabler/icons-vue';

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
    counts: { conversations: number; messages: number; activeCalls: number; activeMeetings: number; failedJobs: number };
    storage: { attachmentBytes: number; recordingsReady: number };
    health: { reverb: string; rtc: string; egress: string; search: string };
    transcription: { provider: string; available: boolean; queued: number; failed: number };
    retention: { retentionDays: number | null; messages: number; attachments: number; voiceMessages: number };
    recordingRetention: { retentionDays: number | null; eligible: number; oldestEndedAt: string | null };
}>();
const { t } = useTranslator();
const { confirm } = useModal();
const chatForm = useForm<{ retention_days: string }>({ retention_days: props.retention.retentionDays?.toString() ?? '' });
const recordingForm = useForm<{ recording_retention_days: string }>({
    recording_retention_days: props.recordingRetention.retentionDays?.toString() ?? '',
});

function saveChat(): void {
    chatForm
        .transform((data) => ({ retention_days: data.retention_days === '' ? null : Number(data.retention_days) }))
        .patch('/admin/chat/retention');
}
function saveRecording(): void {
    recordingForm
        .transform((data) => ({
            recording_retention_days: data.recording_retention_days === '' ? null : Number(data.recording_retention_days),
        }))
        .patch('/admin/chat/recording-retention');
}
async function runCleanup(kind: 'chat' | 'recording'): Promise<void> {
    const copy =
        kind === 'chat'
            ? {
                  titleKey: 'pages.admin.chat_operations.chat_confirm.title' as const,
                  descriptionKey: 'pages.admin.chat_operations.chat_confirm.body' as const,
              }
            : {
                  titleKey: 'pages.admin.chat_operations.recording_confirm.title' as const,
                  descriptionKey: 'pages.admin.chat_operations.recording_confirm.body' as const,
              };
    if (
        !(await confirm({
            titleKey: copy.titleKey,
            descriptionKey: copy.descriptionKey,
            confirmKey: 'pages.admin.chat_operations.run',
            cancelKey: 'actions.cancel',
            tone: 'danger',
        }))
    ) {
        return;
    }
    router.post(kind === 'chat' ? '/admin/chat/retention/run' : '/admin/chat/recording-retention/run');
}
</script>

<template>
    <Head :title="t('pages.admin.chat_operations.title')" />
    <AppLayout :title="t('pages.admin.chat_operations.title')" :title-icon="IconVideo" mode="admin">
        <PageStack>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.conversations')"
                    :value="counts.conversations"
                    :icon="IconMessage"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.failed_jobs')"
                    :value="counts.failedJobs"
                    :icon="IconEraser"
                    :tone="counts.failedJobs > 0 ? 'amber' : 'emerald'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.messages')"
                    :value="counts.messages"
                    :icon="IconMessage"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.active_calls')"
                    :value="counts.activeCalls"
                    :icon="IconPhone"
                    tone="emerald"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.active_meetings')"
                    :value="counts.activeMeetings"
                    :icon="IconVideo"
                    tone="emerald"
                />
            </div>
            <SurfaceCard :title="t('pages.admin.chat_operations.health')" :icon="IconVideo">
                <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-5">
                    <div>
                        <dt class="text-slate-500">Reverb</dt>
                        <dd>{{ health.reverb }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">LiveKit RTC</dt>
                        <dd>{{ health.rtc }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">LiveKit Egress</dt>
                        <dd>{{ health.egress }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">Search</dt>
                        <dd>{{ health.search }}</dd>
                    </div>
                    <div>
                        <dt class="text-slate-500">{{ t('pages.admin.chat_operations.transcription') }}</dt>
                        <dd>
                            {{ transcription.provider }} ·
                            {{
                                transcription.available
                                    ? t('pages.admin.chat_operations.available')
                                    : t('pages.admin.chat_operations.unavailable')
                            }}
                            · {{ transcription.queued }}/{{ transcription.failed }}
                        </dd>
                    </div>
                </dl>
            </SurfaceCard>
            <div class="grid gap-4 sm:grid-cols-2">
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.chat_retention_days')"
                    :value="retention.retentionDays ?? t('pages.admin.chat_operations.indefinite')"
                    :icon="IconDatabaseCog"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.chat_eligible')"
                    :value="retention.messages"
                    :icon="IconEraser"
                    :tone="retention.messages > 0 ? 'amber' : 'emerald'"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.recording_retention_days')"
                    :value="recordingRetention.retentionDays ?? t('pages.admin.chat_operations.indefinite')"
                    :icon="IconDatabaseCog"
                    tone="sky"
                />
                <OperationalMetricTile
                    :label="t('pages.admin.chat_operations.recording_eligible')"
                    :value="recordingRetention.eligible"
                    :icon="IconEraser"
                    :tone="recordingRetention.eligible > 0 ? 'amber' : 'emerald'"
                />
            </div>
            <div class="grid gap-4 lg:grid-cols-2">
                <SurfaceCard :title="t('pages.admin.chat_operations.chat_policy')" :icon="IconDatabaseCog">
                    <AtlasForm class="space-y-4" :processing="chatForm.processing" @submit="saveChat">
                        <FormInput
                            v-model="chatForm.retention_days"
                            type="number"
                            min="1"
                            max="3650"
                            :label="t('pages.admin.chat_operations.chat_retention_days')"
                            :description="t('pages.admin.chat_operations.chat_retention_help')"
                            :error="chatForm.errors.retention_days"
                        />
                        <div class="flex flex-wrap gap-2">
                            <FormButton type="submit" :loading="chatForm.processing">
                                {{ t('pages.admin.chat_operations.save') }}
                            </FormButton>
                            <FormButton tone="neutral" type="button" @click="chatForm.retention_days = ''">
                                {{ t('pages.admin.chat_operations.retain_indefinitely') }}
                            </FormButton>
                            <FormButton tone="danger" type="button" @click="runCleanup('chat')">
                                {{ t('pages.admin.chat_operations.run') }}
                            </FormButton>
                        </div>
                    </AtlasForm>
                </SurfaceCard>
                <SurfaceCard :title="t('pages.admin.chat_operations.recording_policy')" :icon="IconDatabaseCog">
                    <AtlasForm class="space-y-4" :processing="recordingForm.processing" @submit="saveRecording">
                        <FormInput
                            v-model="recordingForm.recording_retention_days"
                            type="number"
                            min="1"
                            max="3650"
                            :label="t('pages.admin.chat_operations.recording_retention_days')"
                            :description="t('pages.admin.chat_operations.recording_retention_help')"
                            :error="recordingForm.errors.recording_retention_days"
                        />
                        <div class="flex flex-wrap gap-2">
                            <FormButton type="submit" :loading="recordingForm.processing">
                                {{ t('pages.admin.chat_operations.save') }}
                            </FormButton>
                            <FormButton tone="neutral" type="button" @click="recordingForm.recording_retention_days = ''">
                                {{ t('pages.admin.chat_operations.retain_indefinitely') }}
                            </FormButton>
                            <FormButton tone="danger" type="button" @click="runCleanup('recording')">
                                {{ t('pages.admin.chat_operations.run') }}
                            </FormButton>
                        </div>
                    </AtlasForm>
                </SurfaceCard>
            </div>
        </PageStack>
    </AppLayout>
</template>
