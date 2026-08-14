<script setup lang="ts">
import { IconClipboard, IconPaperclip, IconUpload } from '@tabler/icons-vue';
import { ref } from 'vue';

import { useTranslator } from '../../Localization/translator';

withDefaults(
    defineProps<{
        id?: string;
        accept?: string;
        multiple?: boolean;
        disabled?: boolean;
    }>(),
    { id: undefined, accept: undefined, multiple: true, disabled: false },
);

const emit = defineEmits<{ selected: [files: File[]] }>();
const { t } = useTranslator();
const inputId = `form-attachments-${crypto.randomUUID()}`;
const dragging = ref(false);

function selected(files: FileList | null): void {
    if (files !== null && files.length > 0) {
        emit('selected', Array.from(files));
    }
}

function paste(event: ClipboardEvent): void {
    const files = Array.from(event.clipboardData?.items ?? [])
        .filter((item) => item.kind === 'file')
        .map((item) => item.getAsFile())
        .filter((file): file is File => file !== null);

    if (files.length > 0) {
        event.preventDefault();
        emit('selected', files);
    }
}
</script>

<template>
    <div
        :id="id"
        tabindex="0"
        role="group"
        :aria-label="t('chat.attachments.add')"
        class="rounded-lg border border-dashed p-4 outline-none transition focus-visible:ring-2 focus-visible:ring-teal-600"
        :class="dragging ? 'border-teal-500 bg-teal-50 dark:bg-teal-950/40' : 'border-zinc-300 dark:border-zinc-700'"
        @dragenter.prevent="dragging = true"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="
            dragging = false;
            selected($event.dataTransfer?.files ?? null);
        "
        @paste="paste"
    >
        <div class="flex flex-wrap items-center gap-3">
            <IconUpload aria-hidden="true" class="h-5 w-5 text-zinc-500" />
            <div class="min-w-48 flex-1">
                <p class="text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ t('chat.attachments.drop') }}</p>
                <p class="mt-1 flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                    <IconClipboard aria-hidden="true" class="h-4 w-4" />
                    {{ t('chat.attachments.clipboard') }}
                </p>
            </div>
            <label
                :for="inputId"
                class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:bg-zinc-800"
                :class="disabled ? 'pointer-events-none opacity-60' : ''"
            >
                <IconPaperclip aria-hidden="true" class="h-4 w-4" />
                {{ t('chat.attachments.choose') }}
            </label>
            <input
                :id="inputId"
                type="file"
                class="sr-only"
                :accept="accept"
                :multiple="multiple"
                :disabled="disabled"
                @change="
                    selected(($event.target as HTMLInputElement).files);
                    ($event.target as HTMLInputElement).value = '';
                "
            />
        </div>
    </div>
</template>
