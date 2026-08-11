<script setup lang="ts">
import { IconCopy, IconDeviceFloppy, IconPencil, IconSettings, IconStar, IconTrash } from '@tabler/icons-vue';
import { onBeforeUnmount, onMounted, ref } from 'vue';

import { useTranslator } from '../../Localization/translator';
import type { DataTableSavedView } from '../../Types/data-table';
import { tableMenuButtonClass } from '../../Utils/buttonClasses';
import FormInput from '../Form/FormInput.vue';
import FormSelect from '../Form/FormSelect.vue';

const props = defineProps<{
    selectedViewId: string;
    savedViewName: string;
    savedViewType: 'private' | 'team';
    savedViewOptions: Array<{ value: string; label: string }>;
    selectedView?: DataTableSavedView;
    uiLocale?: string;
}>();

const emit = defineEmits<{
    'update:selectedViewId': [value: string | number];
    'update:savedViewName': [value: string];
    'update:savedViewType': [value: string | number];
    save: [];
    update: [];
    copy: [];
    makeDefault: [];
    delete: [];
}>();

const { t } = useTranslator(props.uiLocale);
const menu = ref<HTMLDetailsElement | null>(null);

function closeOnOutsideClick(event: MouseEvent): void {
    if (event.target instanceof Node && menu.value && !menu.value.contains(event.target)) menu.value.open = false;
}

onMounted(() => document.addEventListener('click', closeOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', closeOnOutsideClick));
</script>

<template>
    <details ref="menu" class="relative">
        <summary :class="tableMenuButtonClass">
            <IconSettings aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
            {{ t('datatable.views') }}
        </summary>
        <div
            class="absolute right-0 z-20 mt-2 w-80 space-y-3 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg dark:border-zinc-800 dark:bg-zinc-950"
        >
            <FormSelect
                :model-value="selectedViewId"
                :aria-label="t('datatable.views.selector')"
                :options="savedViewOptions"
                button-class="h-9 w-full"
                @update:model-value="emit('update:selectedViewId', $event)"
            />
            <FormInput
                :model-value="savedViewName"
                :aria-label="t('datatable.views.name')"
                :placeholder="t('datatable.views.name_placeholder')"
                @update:model-value="emit('update:savedViewName', $event)"
            />
            <FormSelect
                :model-value="savedViewType"
                :aria-label="t('datatable.views.type')"
                :options="[
                    { value: 'private', label: t('datatable.views.private') },
                    { value: 'team', label: t('datatable.views.team') },
                ]"
                button-class="h-9 w-full"
                @update:model-value="emit('update:savedViewType', $event)"
            />
            <div class="grid grid-cols-2 gap-2">
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg bg-teal-700 px-3 text-sm font-medium text-white transition hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50 dark:bg-teal-600 dark:hover:bg-teal-500"
                    :disabled="savedViewName.trim() === ''"
                    @click="emit('save')"
                >
                    <IconDeviceFloppy aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    {{ t('datatable.views.save') }}
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-zinc-300 px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                    :disabled="selectedView === undefined || selectedView.type === 'system'"
                    @click="emit('update')"
                >
                    <IconPencil aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    {{ t('datatable.views.update') }}
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-zinc-300 px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                    :disabled="selectedView === undefined"
                    @click="emit('copy')"
                >
                    <IconCopy aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    {{ t('datatable.views.copy') }}
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-zinc-300 px-3 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 disabled:cursor-not-allowed disabled:opacity-50 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                    :disabled="selectedView === undefined"
                    @click="emit('makeDefault')"
                >
                    <IconStar aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    {{ t('datatable.views.default') }}
                </button>
                <button
                    type="button"
                    class="inline-flex h-9 items-center justify-center gap-2 rounded-lg border border-rose-200 px-3 text-sm font-medium text-rose-700 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950"
                    :disabled="selectedView === undefined || selectedView.type === 'system'"
                    @click="emit('delete')"
                >
                    <IconTrash aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    {{ t('datatable.views.delete') }}
                </button>
            </div>
        </div>
    </details>
</template>
