<script setup lang="ts">
withDefaults(
    defineProps<{
        width?: 'default' | 'wide' | 'narrow' | 'full';
    }>(),
    {
        width: 'default',
    },
);

const widthClass: Record<string, string> = {
    default: 'mx-auto w-full max-w-7xl',
    wide: 'mx-auto w-full max-w-[96rem]',
    narrow: 'mx-auto w-full max-w-3xl',
    full: 'w-full',
};
</script>

<template>
    <section class="space-y-5" :class="widthClass[width]">
        <slot />
    </section>
</template>
