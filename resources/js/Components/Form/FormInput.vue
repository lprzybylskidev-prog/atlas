<script setup lang="ts">
import type { Component } from 'vue';
import { computed } from 'vue';

const model = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        label?: string;
        ariaLabel?: string;
        id?: string;
        type?: 'date' | 'datetime-local' | 'email' | 'number' | 'password' | 'text';
        autocomplete?: string;
        error?: string;
        placeholder?: string;
        monospace?: boolean;
        leadingIcon?: Component;
        suffix?: string;
        inputmode?: 'decimal' | 'email' | 'numeric' | 'search' | 'tel' | 'text' | 'url';
        step?: string;
        min?: string;
        max?: string;
        disabled?: boolean;
    }>(),
    {
        label: undefined,
        ariaLabel: undefined,
        id: undefined,
        type: 'text',
        autocomplete: undefined,
        error: undefined,
        placeholder: undefined,
        monospace: false,
        leadingIcon: undefined,
        suffix: undefined,
        inputmode: undefined,
        step: undefined,
        min: undefined,
        max: undefined,
        disabled: false,
    },
);

const inputId = props.id ?? `form-input-${crypto.randomUUID()}`;
const errorId = `${inputId}-error`;
const nativeInputType = computed(() => (props.type === 'date' || props.type === 'datetime-local' ? 'text' : props.type));
const effectivePlaceholder = computed(() => {
    if (props.placeholder !== undefined) {
        return props.placeholder;
    }

    return props.type === 'date' ? 'YYYY-MM-DD' : props.type === 'datetime-local' ? 'YYYY-MM-DD HH:mm' : undefined;
});
const effectiveInputmode = computed(
    () => props.inputmode ?? (props.type === 'date' || props.type === 'datetime-local' ? 'numeric' : undefined),
);
</script>

<template>
    <label class="flex flex-col gap-1" :for="inputId">
        <span v-if="label" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ label }}</span>
        <span class="relative block">
            <component
                :is="leadingIcon"
                v-if="leadingIcon"
                aria-hidden="true"
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400"
                :stroke-width="1.8"
            />
            <input
                :id="inputId"
                v-model="model"
                :type="nativeInputType"
                :autocomplete="autocomplete"
                :placeholder="effectivePlaceholder"
                :inputmode="effectiveInputmode"
                :step="step"
                :min="min"
                :max="max"
                :disabled="disabled"
                :aria-label="ariaLabel"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="error ? errorId : undefined"
                class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 text-sm text-zinc-950 outline-none transition placeholder:text-zinc-400 disabled:cursor-not-allowed disabled:bg-zinc-50 disabled:text-zinc-500 focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:placeholder:text-zinc-500 dark:disabled:bg-zinc-900/60 dark:disabled:text-zinc-500 dark:focus:ring-teal-950"
                :class="[{ 'font-mono': monospace }, leadingIcon ? 'pl-9' : '', suffix ? 'pr-14' : '']"
            />
            <span
                v-if="suffix"
                class="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-xs font-semibold text-zinc-500 dark:text-zinc-400"
            >
                {{ suffix }}
            </span>
        </span>
        <p v-if="error" :id="errorId" class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>
    </label>
</template>

<style scoped>
input[type='number'] {
    -moz-appearance: textfield;
}

input[type='number']::-webkit-inner-spin-button,
input[type='number']::-webkit-outer-spin-button {
    margin: 0;
    -webkit-appearance: none;
}
</style>
