<script setup lang="ts">
import { computed, ref } from 'vue';

import { useTranslator } from '../../Localization/translator';
import type { ChatAttachmentPayload } from '../../Services/chatAttachments';
import StatusBadge from '../StatusBadge.vue';

const props = defineProps<{
    conversationPublicId: string;
    media: ChatAttachmentPayload[];
    files: ChatAttachmentPayload[];
    links: string[];
}>();
const { t } = useTranslator();
const tab = ref<'media' | 'files' | 'links'>('media');
const selected = ref<ChatAttachmentPayload | null>(null);
const items = computed(() => (tab.value === 'media' ? props.media : props.files));

function contentUrl(attachment: ChatAttachmentPayload, preview = false): string {
    return `/chat/conversations/${encodeURIComponent(props.conversationPublicId)}/attachments/${encodeURIComponent(attachment.publicId)}/download${preview ? '?preview=1' : ''}`;
}
</script>

<template>
    <section :aria-label="t('chat.content.title')" class="space-y-4">
        <div role="tablist" class="flex gap-2 border-b border-zinc-200 dark:border-zinc-800">
            <button
                v-for="value in ['media', 'files', 'links'] as const"
                :key="value"
                type="button"
                role="tab"
                :aria-selected="tab === value"
                class="border-b-2 px-3 py-2 text-sm font-medium"
                :class="tab === value ? 'border-teal-600 text-teal-700 dark:text-teal-300' : 'border-transparent text-zinc-500'"
                @click="tab = value"
            >
                {{ t(`chat.content.${value}`) }}
            </button>
        </div>

        <ul v-if="tab !== 'links' && items.length" class="grid gap-3 sm:grid-cols-2">
            <li v-for="attachment in items" :key="attachment.publicId" class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                <button
                    v-if="attachment.previewable && attachment.available"
                    type="button"
                    class="w-full text-left"
                    @click="selected = attachment"
                >
                    <span class="block truncate text-sm font-medium">{{ attachment.name }}</span>
                    <StatusBadge class="mt-2" value="clean" :label="t('chat.attachments.state.clean')" />
                </button>
                <div v-else>
                    <span class="block truncate text-sm font-medium">{{ attachment.name }}</span>
                    <StatusBadge class="mt-2" :value="attachment.scanState" :label="t(`chat.attachments.state.${attachment.scanState}`)" />
                </div>
                <a
                    v-if="attachment.available"
                    :href="contentUrl(attachment)"
                    class="mt-3 inline-block text-sm font-medium text-teal-700 underline dark:text-teal-300"
                >
                    {{ t('chat.attachments.download') }}
                </a>
            </li>
        </ul>
        <ul v-else-if="tab === 'links' && links.length" class="space-y-2">
            <li v-for="link in links" :key="link" class="truncate">
                <a
                    :href="link"
                    target="_blank"
                    rel="noopener noreferrer nofollow"
                    class="text-sm text-teal-700 underline dark:text-teal-300"
                >
                    {{ link }}
                </a>
            </li>
        </ul>
        <p v-else class="text-sm text-zinc-500 dark:text-zinc-400">{{ t('chat.content.empty') }}</p>

        <div
            v-if="selected"
            role="dialog"
            aria-modal="true"
            :aria-label="selected.name"
            class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
            @click.self="selected = null"
        >
            <div class="max-h-full w-full max-w-4xl rounded-xl bg-white p-4 dark:bg-zinc-950">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h3 class="truncate font-semibold">{{ selected.name }}</h3>
                    <button type="button" class="rounded-lg px-3 py-2 text-sm font-medium" @click="selected = null">
                        {{ t('actions.close') }}
                    </button>
                </div>
                <img
                    v-if="selected.mimeType.startsWith('image/')"
                    :src="contentUrl(selected, true)"
                    :alt="selected.name"
                    class="mx-auto max-h-[70vh] max-w-full object-contain"
                />
                <audio
                    v-else-if="selected.mimeType.startsWith('audio/')"
                    :src="contentUrl(selected, true)"
                    controls
                    class="w-full"
                    :aria-label="selected.name"
                />
                <video
                    v-else-if="selected.mimeType.startsWith('video/')"
                    :src="contentUrl(selected, true)"
                    controls
                    class="max-h-[70vh] w-full"
                    :aria-label="selected.name"
                />
            </div>
        </div>
    </section>
</template>
