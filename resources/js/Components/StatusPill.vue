<script setup lang="ts">
defineProps<{
    tone: 'success' | 'warning' | 'info' | 'neutral';
}>();
</script>

<template>
    <span
        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium"
        :class="{
            'bg-emerald-50 text-emerald-800 ring-1 ring-emerald-200 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800':
                tone === 'success',
            'bg-amber-50 text-amber-800 ring-1 ring-amber-200 dark:bg-amber-950 dark:text-amber-200 dark:ring-amber-800':
                tone === 'warning',
            'bg-teal-50 text-teal-800 ring-1 ring-teal-200 dark:bg-teal-950 dark:text-teal-100 dark:ring-teal-800': tone === 'info',
            'bg-zinc-100 text-zinc-700 ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-200 dark:ring-zinc-700': tone === 'neutral',
        }"
    >
        <slot />
    </span>
</template>
