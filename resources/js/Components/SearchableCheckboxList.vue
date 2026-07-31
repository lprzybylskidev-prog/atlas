<script setup lang="ts">
import { IconSearch } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import CheckboxList from './CheckboxList.vue';
import type { CheckboxListOption } from './CheckboxList.vue';
import FormInput from './Form/FormInput.vue';

const model = defineModel<string[]>({ required: true });

const props = withDefaults(
    defineProps<{
        options: Array<string | CheckboxListOption>;
        label: string;
        searchLabel: string;
        searchPlaceholder: string;
        selectedLabel: string;
        emptyText: string;
        error?: string;
        maxHeight?: string;
        itemMonospace?: boolean;
    }>(),
    {
        error: undefined,
        maxHeight: 'max-h-56',
        itemMonospace: true,
    },
);

const search = ref('');
const filteredOptions = computed(() => {
    const needle = search.value.trim().toLowerCase();

    if (needle === '') {
        return props.options;
    }

    return props.options.filter((option) => {
        const value = typeof option === 'string' ? option : option.value;
        const label = typeof option === 'string' ? option : option.label;
        const description = typeof option === 'string' ? '' : (option.description ?? '');

        return `${value} ${label} ${description}`.toLowerCase().includes(needle);
    });
});
</script>

<template>
    <div class="space-y-3">
        <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto] md:items-end">
            <FormInput
                v-model="search"
                :label="searchLabel"
                :placeholder="searchPlaceholder"
                :leading-icon="IconSearch"
                inputmode="search"
            />
            <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">{{ selectedLabel }}</p>
        </div>

        <CheckboxList
            v-model="model"
            :options="filteredOptions"
            :label="label"
            :empty-text="emptyText"
            :error="error"
            :max-height="maxHeight"
            :item-monospace="itemMonospace"
        />
    </div>
</template>
