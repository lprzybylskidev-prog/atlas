<script setup lang="ts">
import { IconFilter, IconRefresh, IconX } from '@tabler/icons-vue';

import SurfaceCard from './SurfaceCard.vue';
import AtlasForm from './Form/AtlasForm.vue';
import FormButton from './Form/FormButton.vue';
import { useTranslator } from '../Localization/translator';
import type { ActiveFilter } from '../Types/ui-state';

withDefaults(
    defineProps<{
        title?: string;
        summary?: string;
        applyLabel?: string;
        clearLabel?: string;
        activeFilters?: ActiveFilter[];
    }>(),
    {
        title: undefined,
        summary: undefined,
        applyLabel: undefined,
        clearLabel: undefined,
        activeFilters: () => [],
    },
);

const emit = defineEmits<{
    apply: [];
    clear: [];
    clearFilter: [key: string];
}>();

const { t } = useTranslator();
</script>

<template>
    <AtlasForm @submit="emit('apply')">
        <SurfaceCard :title="title ?? t('filters.title')" :icon="IconFilter" tone="zinc">
            <template #actions>
                <div class="flex flex-wrap justify-end gap-2">
                    <FormButton type="button" tone="neutral" :icon="IconRefresh" @click="emit('clear')">
                        {{ clearLabel ?? t('filters.clear_all') }}
                    </FormButton>
                    <FormButton type="submit" :icon="IconFilter">
                        {{ applyLabel ?? t('filters.apply') }}
                    </FormButton>
                </div>
            </template>

            <div class="space-y-4">
                <slot />

                <div v-if="activeFilters.length" class="flex flex-wrap gap-2" :aria-label="t('filters.active')">
                    <button
                        v-for="filter in activeFilters"
                        :key="filter.key"
                        type="button"
                        class="inline-flex min-h-8 items-center gap-1.5 rounded-full bg-teal-50 px-3 text-xs font-medium text-teal-800 ring-1 ring-teal-200 transition hover:bg-teal-100 dark:bg-teal-950/60 dark:text-teal-200 dark:ring-teal-900 dark:hover:bg-teal-950"
                        :aria-label="t('filters.clear_one', { filter: filter.label })"
                        @click="emit('clearFilter', filter.key)"
                    >
                        <span>{{ filter.label }}: {{ filter.valueLabel }}</span>
                        <IconX aria-hidden="true" class="h-3.5 w-3.5" :stroke-width="1.8" />
                    </button>
                </div>

                <p v-if="summary" class="text-sm text-zinc-500 dark:text-zinc-400">{{ summary }}</p>
            </div>
        </SurfaceCard>
    </AtlasForm>
</template>
