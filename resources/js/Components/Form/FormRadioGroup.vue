<script setup lang="ts">
import { IconCircleCheck } from '@tabler/icons-vue';

import FormFieldError from './FormFieldError.vue';
import type { FormSelectOption } from './FormSelect.vue';

const model = defineModel<string | number>({ required: true });

const props = withDefaults(
    defineProps<{
        label: string;
        options: FormSelectOption[];
        error?: string;
    }>(),
    {
        error: undefined,
    },
);

const groupId = `form-radio-${crypto.randomUUID()}`;
const errorId = `${groupId}-error`;
</script>

<template>
    <fieldset :aria-describedby="error ? errorId : undefined">
        <legend class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ label }}</legend>
        <div class="mt-2 grid gap-2">
            <button
                v-for="option in props.options"
                :key="`${option.value}`"
                type="button"
                role="radio"
                :aria-checked="model === option.value"
                class="flex min-h-10 items-center justify-between gap-3 rounded-lg border px-3 py-2 text-left text-sm outline-none transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700"
                :class="
                    model === option.value
                        ? 'border-teal-300 bg-teal-50 text-teal-900 dark:border-teal-700 dark:bg-teal-950 dark:text-teal-100'
                        : 'border-zinc-300 bg-white text-zinc-700 hover:border-zinc-400 hover:bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800'
                "
                @click="model = option.value"
            >
                <span>{{ option.label }}</span>
                <IconCircleCheck v-if="model === option.value" aria-hidden="true" class="h-4 w-4 shrink-0" :stroke-width="1.8" />
            </button>
        </div>
        <FormFieldError :id="errorId" :error="error" />
    </fieldset>
</template>
