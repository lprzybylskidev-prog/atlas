<script setup lang="ts">
import { useTranslator } from '../../Localization/translator';
import FormAutocomplete from './FormAutocomplete.vue';
import type { FormSelectOption } from './FormSelect.vue';

const model = defineModel<string | number>({ required: true });

defineProps<{
    label?: string;
    ariaLabel?: string;
    options: FormSelectOption[];
    placeholder?: string;
    error?: string;
}>();

const { t } = useTranslator();
</script>

<template>
    <FormAutocomplete
        v-model="model"
        :label="label"
        :aria-label="ariaLabel"
        :options="options"
        :placeholder="placeholder ?? t('form.entity_search.placeholder')"
        :error="error"
    />
</template>
