<script setup lang="ts">
import { IconChevronLeft, IconChevronRight } from '@tabler/icons-vue';

import FormSelect from '../Form/FormSelect.vue';
import { useTranslator } from '../../Localization/translator';

defineProps<{
    canNext: boolean;
    canPrevious: boolean;
    pageIndex: number;
    pageSize: number;
    pageOptions: Array<{ value: number; label: string }>;
    pageSizeOptions: Array<{ value: number; label: string }>;
    pageCount: number;
}>();

const emit = defineEmits<{
    next: [];
    previous: [];
    updatePage: [page: number];
    updatePageSize: [size: number];
}>();
const { t } = useTranslator();
const buttonClass =
    'inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50';
</script>

<template>
    <div
        class="relative z-10 flex flex-col gap-3 border-t border-zinc-200 px-4 py-3 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800"
    >
        <div class="flex flex-wrap items-center gap-2">
            <button type="button" :class="buttonClass" :disabled="!canPrevious" @click="emit('previous')">
                <IconChevronLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                {{ t('datatable.previous') }}
            </button>
            <button type="button" :class="buttonClass" :disabled="!canNext" @click="emit('next')">
                {{ t('datatable.next') }}
                <IconChevronRight aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
            </button>
        </div>
        <div class="flex flex-wrap items-center gap-3 text-sm text-zinc-500 dark:text-zinc-400">
            <div class="flex items-center gap-2">
                <span>{{ t('datatable.rows_per_page') }}</span>
                <FormSelect
                    :model-value="pageSize"
                    :aria-label="t('datatable.rows_per_page')"
                    :options="pageSizeOptions"
                    button-class="h-9 w-20"
                    @update:model-value="emit('updatePageSize', Number($event))"
                />
            </div>
            <div class="flex items-center gap-2">
                <span>{{ t('datatable.page') }}</span>
                <FormSelect
                    :model-value="pageIndex"
                    :aria-label="t('datatable.page')"
                    :options="pageOptions"
                    button-class="h-9 w-20"
                    @update:model-value="emit('updatePage', Number($event))"
                />
            </div>
            <span>{{ t('datatable.page_of', { page: pageIndex + 1, pages: pageCount }) }}</span>
        </div>
    </div>
</template>
