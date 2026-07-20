<script setup lang="ts">
import FormCheckbox from './Form/FormCheckbox.vue';

const model = defineModel<string[]>({ required: true });

withDefaults(
    defineProps<{
        options: string[];
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
</script>

<template>
    <section>
        <p v-if="label" class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400">{{ label }}</p>
        <div
            class="grid grid-cols-1 gap-2 overflow-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
            :class="[label ? 'mt-2' : '', maxHeight]"
        >
            <p v-if="options.length === 0" class="text-sm text-zinc-500 dark:text-zinc-400">{{ emptyText }}</p>
            <FormCheckbox v-for="option in options" :key="option" v-model="model" class="w-full" :value="option" align="start">
                <span class="break-all text-xs" :class="{ 'font-mono': itemMonospace }">{{ option }}</span>
            </FormCheckbox>
        </div>
        <p v-if="error" class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>
    </section>
</template>
