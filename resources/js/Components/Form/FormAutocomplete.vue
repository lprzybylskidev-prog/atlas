<script setup lang="ts">
import { IconChevronDown, IconSearch } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import { useTranslator } from '../../Localization/translator';
import Popover from '../Popover.vue';
import FormFieldError from './FormFieldError.vue';
import type { FormSelectOption } from './FormSelect.vue';

const model = defineModel<string | number>({ required: true });

const props = withDefaults(
    defineProps<{
        label?: string;
        ariaLabel?: string;
        options: FormSelectOption[];
        placeholder?: string;
        error?: string;
    }>(),
    {
        label: undefined,
        ariaLabel: undefined,
        placeholder: undefined,
        error: undefined,
    },
);

const open = ref(false);
const query = ref('');
const controlId = `form-autocomplete-${crypto.randomUUID()}`;
const errorId = `${controlId}-error`;
const { t } = useTranslator();

const selectedOption = computed(() => props.options.find((option) => option.value === model.value) ?? null);
const filteredOptions = computed(() => {
    const normalizedQuery = query.value.trim().toLowerCase();

    if (normalizedQuery === '') {
        return props.options;
    }

    return props.options.filter((option) => option.label.toLowerCase().includes(normalizedQuery));
});

function selectOption(option: FormSelectOption): void {
    model.value = option.value;
    query.value = option.label;
    open.value = false;
}
</script>

<template>
    <div>
        <label v-if="label" :for="controlId" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ label }}</label>
        <Popover v-model:open="open">
            <template #trigger>
                <div class="relative">
                    <IconSearch
                        aria-hidden="true"
                        class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-zinc-400"
                        :stroke-width="1.8"
                    />
                    <input
                        :id="controlId"
                        v-model="query"
                        type="text"
                        class="h-10 w-full rounded-lg border border-zinc-300 bg-white pr-9 pl-9 text-sm text-zinc-950 outline-none transition placeholder:text-zinc-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:placeholder:text-zinc-500 dark:focus:ring-teal-950"
                        :placeholder="selectedOption?.label ?? placeholder ?? t('form.autocomplete.placeholder')"
                        :aria-label="ariaLabel ?? label"
                        :aria-invalid="error ? 'true' : 'false'"
                        :aria-describedby="error ? errorId : undefined"
                        role="combobox"
                        :aria-expanded="open"
                        @focus="open = true"
                    />
                    <IconChevronDown
                        aria-hidden="true"
                        class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-zinc-400"
                        :class="{ 'rotate-180': open }"
                        :stroke-width="1.8"
                    />
                </div>
            </template>

            <div role="listbox" class="max-h-64 overflow-auto">
                <button
                    v-for="option in filteredOptions"
                    :key="`${option.value}`"
                    type="button"
                    role="option"
                    :aria-selected="model === option.value"
                    class="block h-9 w-full truncate rounded-md px-2 text-left text-sm text-zinc-700 outline-none transition hover:bg-teal-50 hover:text-teal-900 focus:bg-teal-50 focus:text-teal-900 dark:text-zinc-200 dark:hover:bg-teal-950 dark:hover:text-teal-100 dark:focus:bg-teal-950 dark:focus:text-teal-100"
                    @click="selectOption(option)"
                >
                    {{ option.label }}
                </button>
                <p v-if="filteredOptions.length === 0" class="px-2 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                    {{ t('form.autocomplete.no_results') }}
                </p>
            </div>
        </Popover>
        <FormFieldError :id="errorId" :error="error" />
    </div>
</template>
