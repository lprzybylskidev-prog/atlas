<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const open = defineModel<boolean>('open', { default: false });

const root = ref<HTMLElement | null>(null);

function closeOnOutsidePointer(event: PointerEvent): void {
    const target = event.target;

    if (target instanceof Node && !root.value?.contains(target)) {
        open.value = false;
    }
}

function closeOnEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', closeOnOutsidePointer);
    document.addEventListener('keydown', closeOnEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeOnOutsidePointer);
    document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <div ref="root" class="relative">
        <slot name="trigger" />
        <div
            v-if="open"
            class="absolute right-0 z-40 mt-1.5 w-full min-w-max rounded-lg border border-zinc-200 bg-white p-1 shadow-lg shadow-zinc-950/10 dark:border-zinc-800 dark:bg-zinc-950 dark:shadow-black/30"
        >
            <slot />
        </div>
    </div>
</template>
