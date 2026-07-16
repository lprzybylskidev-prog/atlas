<script setup lang="ts">
defineProps<{
    data?: {
        label: string;
        value: string;
        description: string;
    } | null;
}>();
</script>

<template>
    <div class="p-4">
        <p class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ data?.label ?? 'Status' }}</p>
        <p class="mt-2 text-2xl font-semibold text-teal-700 dark:text-teal-300">{{ data?.value ?? 'Available' }}</p>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">{{ data?.description ?? 'Module gate passed.' }}</p>
    </div>
</template>
