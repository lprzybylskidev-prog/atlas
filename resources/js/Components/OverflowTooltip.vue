<script setup lang="ts">
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import Tooltip from './Tooltip.vue';

const props = withDefaults(
    defineProps<{
        text: string;
        contentClass?: string;
        disabled?: boolean;
        fullWidth?: boolean;
        align?: 'center' | 'end' | 'start';
        placement?: 'right' | 'top';
        wide?: boolean;
    }>(),
    {
        contentClass: '',
        disabled: false,
        fullWidth: false,
        align: 'center',
        placement: 'right',
        wide: false,
    },
);

const content = ref<HTMLElement | null>(null);
const overflowing = ref(false);
let resizeObserver: ResizeObserver | null = null;

function measureOverflow(): void {
    const element = content.value;

    if (element === null) {
        overflowing.value = false;

        return;
    }

    overflowing.value = element.scrollWidth > element.clientWidth + 1 || element.scrollHeight > element.clientHeight + 1;
}

onMounted(() => {
    void nextTick(measureOverflow);

    if (typeof ResizeObserver !== 'undefined' && content.value !== null) {
        resizeObserver = new ResizeObserver(measureOverflow);
        resizeObserver.observe(content.value);
    }

    window.addEventListener('resize', measureOverflow);
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    window.removeEventListener('resize', measureOverflow);
});

watch(
    () => [props.text, props.contentClass],
    () => {
        void nextTick(measureOverflow);
    },
);
</script>

<template>
    <Tooltip
        :text="text"
        :disabled="disabled || text === '' || !overflowing"
        :full-width="fullWidth"
        :align="align"
        :placement="placement"
        :wide="wide"
    >
        <span ref="content" :class="contentClass">
            <slot />
        </span>
    </Tooltip>
</template>
