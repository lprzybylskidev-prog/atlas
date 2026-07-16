<script setup lang="ts">
import FormFieldError from './FormFieldError.vue';

const model = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        label?: string;
        ariaLabel?: string;
        id?: string;
        error?: string;
        placeholder?: string;
        rows?: number;
    }>(),
    {
        label: undefined,
        ariaLabel: undefined,
        id: undefined,
        error: undefined,
        placeholder: undefined,
        rows: 4,
    },
);

const inputId = props.id ?? `form-textarea-${crypto.randomUUID()}`;
const errorId = `${inputId}-error`;
</script>

<template>
    <label class="block" :for="inputId">
        <span v-if="label" class="text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ label }}</span>
        <textarea
            :id="inputId"
            v-model="model"
            :rows="rows"
            :placeholder="placeholder"
            :aria-label="ariaLabel"
            :aria-invalid="error ? 'true' : 'false'"
            :aria-describedby="error ? errorId : undefined"
            class="mt-1 w-full rounded-lg border border-zinc-300 bg-white px-3 py-2 text-sm text-zinc-950 outline-none transition placeholder:text-zinc-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:placeholder:text-zinc-500 dark:focus:ring-teal-950"
        />
        <FormFieldError :id="errorId" :error="error" />
    </label>
</template>
