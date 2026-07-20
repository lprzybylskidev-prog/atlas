<script setup lang="ts">
import { IconFilter, IconRefresh } from '@tabler/icons-vue';

import CardHeader from './CardHeader.vue';
import AtlasForm from './Form/AtlasForm.vue';
import FormButton from './Form/FormButton.vue';

withDefaults(
    defineProps<{
        title?: string;
        summary?: string;
        applyLabel?: string;
        clearLabel?: string;
    }>(),
    {
        title: 'Filters',
        summary: undefined,
        applyLabel: 'Apply',
        clearLabel: 'Clear',
    },
);

const emit = defineEmits<{
    apply: [];
    clear: [];
}>();
</script>

<template>
    <section class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
        <AtlasForm class="space-y-4" @submit="emit('apply')">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <CardHeader :title="title" :icon="IconFilter" tone="zinc" size="sm" />
                <div class="flex shrink-0 flex-wrap justify-end gap-2">
                    <FormButton type="button" tone="neutral" :icon="IconRefresh" @click="emit('clear')">
                        {{ clearLabel }}
                    </FormButton>
                    <FormButton type="submit" :icon="IconFilter">
                        {{ applyLabel }}
                    </FormButton>
                </div>
            </div>

            <slot />

            <p v-if="summary" class="text-sm text-zinc-500 dark:text-zinc-400">{{ summary }}</p>
        </AtlasForm>
    </section>
</template>
