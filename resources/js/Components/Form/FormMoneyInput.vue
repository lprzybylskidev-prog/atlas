<script setup lang="ts">
import { computed } from 'vue';

import { majorToMinor, minorToMajor } from '../../Utils/formatters';
import FormInput from './FormInput.vue';

const model = defineModel<number>({ required: true });

withDefaults(
    defineProps<{
        label?: string;
        ariaLabel?: string;
        id?: string;
        error?: string;
        currency?: string;
    }>(),
    {
        label: undefined,
        ariaLabel: undefined,
        id: undefined,
        error: undefined,
        currency: 'PLN',
    },
);

const majorValue = computed({
    get: () => minorToMajor(model.value).toFixed(2),
    set: (value: string) => {
        model.value = majorToMinor(Number(value || 0));
    },
});
</script>

<template>
    <FormInput
        :id="id"
        v-model="majorValue"
        type="number"
        :label="label"
        :aria-label="ariaLabel"
        :error="error"
        :suffix="currency"
        inputmode="decimal"
        step="0.01"
    />
</template>
