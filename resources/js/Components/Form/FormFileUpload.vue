<script setup lang="ts">
import { IconUpload } from '@tabler/icons-vue';

import { useTranslator } from '../../Localization/translator';
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

function updateFile(event: Event): void {
    const input = event.target;

    model.value = input instanceof HTMLInputElement ? (input.files?.[0] ?? null) : null;
}
</script>

<template>
    <label
        :for="inputId"
        class="block rounded-lg border border-dashed border-zinc-300 bg-white p-4 text-sm transition hover:border-teal-400 hover:bg-teal-50 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-teal-700 dark:hover:bg-teal-950/40"
    >
        <span class="flex items-center gap-3 text-zinc-700 dark:text-zinc-200">
            <span
                class="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-100 text-zinc-500 dark:bg-zinc-800 dark:text-zinc-300"
            >
                <IconUpload aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
            </span>
            <span class="min-w-0">
                <span class="block font-medium">{{ label }}</span>
                <span class="block truncate text-xs text-zinc-500 dark:text-zinc-400">
                    {{ model?.name ?? t('form.file.none') }}
                </span>
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
