<script setup lang="ts">
import { IconCalendarEvent } from '@tabler/icons-vue';

import FormInput from './FormInput.vue';

const model = defineModel<string>({ required: true });

defineProps<{
    label?: string;
    ariaLabel?: string;
    id?: string;
    error?: string;
}>();
</script>

<template>
    <FormInput
        :id="id"
        v-model="model"
        type="date"
        :label="label"
        :aria-label="ariaLabel"
        :error="error"
        :leading-icon="IconCalendarEvent"
    />
</template>
