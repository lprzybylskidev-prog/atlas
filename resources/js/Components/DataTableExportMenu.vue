<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconFileExport } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import type { TranslationKey } from '../Localization/catalog';
import { useTranslator } from '../Localization/translator';
import type { DataTableExportFormat, DataTableExportMeta } from '../Types/data-table';

const props = withDefaults(
    defineProps<{
        tableKey: string;
        exports?: DataTableExportMeta;
        columns: string[];
        columnOrder?: string[];
        filters?: Record<string, string | number | boolean | null | undefined>;
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
        perPage?: number;
        uiLocale?: string;
    }>(),
    {
        exports: undefined,
        columnOrder: undefined,
        filters: undefined,
        search: '',
        sort: undefined,
        direction: 'asc',
        perPage: 250,
        uiLocale: undefined,
    },
);

const { t } = useTranslator(props.uiLocale);
const exportMenu = ref<HTMLDetailsElement | null>(null);
const exportFormats = computed(() => props.exports?.formats ?? []);
const exportAvailable = computed(() => props.exports !== undefined && exportFormats.value.length > 0);
const menuButtonClass =
    'inline-flex h-9 cursor-pointer list-none items-center gap-2 rounded-lg border border-zinc-300 bg-white px-3 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50';

function exportLabel(format: DataTableExportFormat): string {
    const labels: Record<DataTableExportFormat, TranslationKey> = {
        csv: 'datatable.exports.csv',
        xlsx: 'datatable.exports.xlsx',
        pdf: 'datatable.exports.pdf',
        browser_print: 'datatable.exports.print',
    };

    return t(labels[format]);
}

function exportFilters(): Record<string, string | number> {
    return Object.fromEntries(
        Object.entries(props.filters ?? {})
            .filter(([, value]) => value !== null && value !== undefined && String(value).trim() !== '')
            .map(([key, value]) => [key, typeof value === 'boolean' ? (value ? 1 : 0) : (value as string | number)]),
    );
}

function exportPayload(format: DataTableExportFormat): Record<string, string | number> {
    return {
        table_key: props.tableKey,
        format,
        page: 1,
        per_page: props.perPage,
        sort: props.sort ?? props.columns[0] ?? '',
        direction: props.direction,
        search: props.search ?? '',
        columns: props.columns.join(','),
        column_order: (props.columnOrder ?? props.columns).join(','),
        ...exportFilters(),
    };
}

function requestExport(format: DataTableExportFormat): void {
    if (props.exports === undefined) {
        return;
    }

    if (format === 'browser_print') {
        requestBrowserPrint();
        return;
    }

    router.post(props.exports.endpoint, exportPayload(format), {
        preserveScroll: true,
        preserveState: true,
    });
}

function requestBrowserPrint(): void {
    if (props.exports === undefined) {
        return;
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = props.exports.endpoint;
    form.target = '_blank';

    const csrfValue = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content;

    if (csrfValue !== undefined && csrfValue !== '') {
        appendHiddenInput(form, '_token', csrfValue);
    }

    for (const [key, value] of Object.entries(exportPayload('browser_print'))) {
        appendHiddenInput(form, key, String(value));
    }

    document.body.appendChild(form);
    form.submit();
    form.remove();
}

function appendHiddenInput(form: HTMLFormElement, name: string, value: string): void {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = name;
    input.value = value;
    form.appendChild(input);
}

function closeOnOutsideClick(event: MouseEvent): void {
    const target = event.target;

    if (target instanceof Node && exportMenu.value && !exportMenu.value.contains(target)) {
        exportMenu.value.open = false;
    }
}

onMounted(() => {
    document.addEventListener('click', closeOnOutsideClick);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', closeOnOutsideClick);
});
</script>

<template>
    <details v-if="exportAvailable" ref="exportMenu" class="relative">
        <summary :class="menuButtonClass">
            <IconFileExport aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
            {{ t('datatable.exports') }}
        </summary>
        <div
            class="absolute right-0 z-20 mt-2 w-48 rounded-lg border border-zinc-200 bg-white p-2 shadow-lg dark:border-zinc-800 dark:bg-zinc-950"
        >
            <button
                v-for="format in exportFormats"
                :key="format"
                type="button"
                class="flex h-9 w-full items-center gap-2 rounded-md px-2 text-left text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-900 dark:hover:text-zinc-50"
                @click="requestExport(format)"
            >
                <IconFileExport aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                {{ exportLabel(format) }}
            </button>
        </div>
    </details>
</template>
