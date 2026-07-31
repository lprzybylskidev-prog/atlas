<script setup lang="ts">
import { IconUpload } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { useTranslator } from '../../Localization/translator';
import TruncatedText from '../TruncatedText.vue';
import FormFieldError from './FormFieldError.vue';

const model = defineModel<File | null>({ required: true });

const props = withDefaults(
    defineProps<{
        label: string;
        id?: string;
        accept?: string;
        error?: string;
    }>(),
    {
        id: undefined,
        accept: undefined,
        error: undefined,
    },
);

const inputId = props.id ?? `form-file-${crypto.randomUUID()}`;
const errorId = `${inputId}-error`;
const { t } = useTranslator();
const dragging = ref(false);
const rootClass = computed(() => [
    'block rounded-lg border border-dashed bg-white p-4 text-sm transition dark:bg-zinc-900',
    dragging.value
        ? 'border-teal-500 bg-teal-50 ring-2 ring-teal-500/20 dark:border-teal-500 dark:bg-teal-950/40'
        : 'border-zinc-300 hover:border-teal-400 hover:bg-teal-50 dark:border-zinc-700 dark:hover:border-teal-700 dark:hover:bg-teal-950/40',
]);

function updateFile(event: Event): void {
    const input = event.target;

    model.value = input instanceof HTMLInputElement ? (input.files?.[0] ?? null) : null;
}

function dropFile(event: DragEvent): void {
    dragging.value = false;

    const file = event.dataTransfer?.files?.[0] ?? null;

    if (file !== null) {
        model.value = file;
    }
}
</script>

<template>
    <label
        :for="inputId"
        :class="rootClass"
        @dragenter.prevent="dragging = true"
        @dragover.prevent="dragging = true"
        @dragleave.prevent="dragging = false"
        @drop.prevent="dropFile"
    >
        <span class="flex items-center gap-3 text-zinc-700 dark:text-zinc-200">
            <span
                class="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300"
            >
                <IconUpload aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
            </span>
            <span class="min-w-0">
                <span class="block font-medium">{{ label }}</span>
                <TruncatedText :text="model?.name ?? t('form.file.none')" text-class="text-xs text-zinc-500 dark:text-zinc-400" />
            </span>
        </span>
        <input
            :id="inputId"
            type="file"
            class="sr-only"
            :accept="accept"
            :aria-invalid="error ? 'true' : 'false'"
            :aria-describedby="error ? errorId : undefined"
            @change="updateFile"
        />
        <FormFieldError :id="errorId" :error="error" />
    </label>
</template>
