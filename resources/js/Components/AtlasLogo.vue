<script setup lang="ts">
withDefaults(
    defineProps<{
        showText?: boolean;
        markClass?: string;
    }>(),
    {
        showText: true,
        markClass: 'h-9 w-9',
    },
);

const logoPath = '/brand/atlas-logo.svg';
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <div
            class="flex shrink-0 items-center justify-center rounded-lg bg-teal-700 text-white shadow-sm shadow-teal-950/15 dark:bg-teal-700"
            :class="markClass"
        >
            <img :src="logoPath" alt="" class="h-6 w-6" />
        </div>
        <div v-if="showText" class="min-w-0">
            <p class="truncate text-sm font-semibold leading-5 text-zinc-950 dark:text-zinc-50">Atlas</p>
            <p class="truncate text-xs leading-4 text-zinc-500 dark:text-zinc-400">Debt operations</p>
        </div>
    </div>
</template>
