<script setup lang="ts">
import type { Component } from 'vue';

import FormButton from './FormButton.vue';

withDefaults(
    defineProps<{
        cancelLabel: string;
        submitLabel: string;
        submitIcon?: Component;
        submitTone?: 'primary' | 'neutral' | 'danger';
        loading?: boolean;
    }>(),
    {
        submitIcon: undefined,
        submitTone: 'primary',
        loading: false,
    },
);

const emit = defineEmits<{
    cancel: [];
}>();
</script>

<template>
    <div class="mt-5 flex flex-wrap justify-end gap-2">
        <FormButton type="button" tone="neutral" @click="emit('cancel')">
            {{ cancelLabel }}
        </FormButton>
        <FormButton type="submit" :tone="submitTone" :icon="submitIcon" :loading="loading">
            {{ submitLabel }}
        </FormButton>
    </div>
</template>
