<script setup lang="ts">
import FormCheckbox from './Form/FormCheckbox.vue';

const model = defineModel<string[]>({ required: true });

export interface CheckboxListOption {
    value: string;
    label: string;
    description?: string;
}

withDefaults(
    defineProps<{
        options: Array<string | CheckboxListOption>;
        label?: string;
        error?: string;
        emptyText?: string;
        maxHeight?: string;
        itemMonospace?: boolean;
    }>(),
    {
        label: undefined,
        error: undefined,
        emptyText: 'No options available.',
        maxHeight: 'max-h-56',
        itemMonospace: true,
    },
);

function optionValue(option: string | CheckboxListOption): string {
    return typeof option === 'string' ? option : option.value;
}

function optionLabel(option: string | CheckboxListOption): string {
    return typeof option === 'string' ? option : option.label;
}

function optionDescription(option: string | CheckboxListOption): string | undefined {
    return typeof option === 'string' ? undefined : option.description;
}
</script>

<template>
    <section>
        <p v-if="label" class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ label }}</p>
        <div
            class="grid grid-cols-1 gap-2 overflow-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
            :class="[label ? 'mt-2' : '', maxHeight]"
        >
            <p v-if="options.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">{{ emptyText }}</p>
            <FormCheckbox
                v-for="option in options"
                :key="optionValue(option)"
                v-model="model"
                class="w-full"
                :value="optionValue(option)"
                align="start"
            >
                <span class="flex min-w-0 flex-col gap-0.5">
                    <span class="break-words text-xs font-medium text-zinc-800 dark:text-zinc-100">{{ optionLabel(option) }}</span>
                    <span
                        v-if="optionDescription(option)"
                        class="break-all text-[0.6875rem] text-zinc-500 dark:text-zinc-400"
                        :class="{ 'font-mono': itemMonospace }"
                    >
                        {{ optionDescription(option) }}
                    </span>
                    <span
                        v-else-if="optionLabel(option) !== optionValue(option)"
                        class="break-all text-[0.6875rem] text-zinc-500 dark:text-zinc-400"
                    >
                        {{ optionValue(option) }}
                    </span>
                    <span v-else class="break-all text-xs" :class="{ 'font-mono': itemMonospace }">{{ optionValue(option) }}</span>
                </span>
            </FormCheckbox>
        </div>
        <p v-if="error" class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>
    </section>
</template>
