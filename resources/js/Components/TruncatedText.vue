<script setup lang="ts">
import { computed } from 'vue';

import Tooltip from './Tooltip.vue';

const props = withDefaults(
    defineProps<{
        text: string | number | null | undefined;
        lines?: 1 | 2;
        textClass?: string;
        disabled?: boolean;
    }>(),
    {
        lines: 1,
        textClass: '',
        disabled: false,
    },
);

const displayText = computed(() => (props.text === null || props.text === undefined ? '' : String(props.text)));
const contentClass = computed(() => (props.lines === 1 ? 'block min-w-0 max-w-full truncate' : 'block min-w-0 max-w-full line-clamp-2'));
</script>

<template>
    <Tooltip :text="displayText" :disabled="disabled || displayText === ''" full-width align="start" placement="top">
        <span :class="[contentClass, textClass]">{{ displayText }}</span>
    </Tooltip>
</template>
