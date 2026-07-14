<script setup lang="ts">
interface ContractItem {
    title: string;
    body: string;
}

defineProps<{
    data?: ContractItem[];
}>();
</script>

<template>
    <div class="grid gap-0 md:grid-cols-3">
        <div
            v-for="item in data"
            :key="item.title"
            class="border-b border-zinc-200 p-4 dark:border-zinc-800 md:border-b-0 md:border-r last:md:border-r-0"
        >
            <p class="text-sm font-medium text-zinc-950 dark:text-zinc-50">{{ item.title }}</p>
            <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ item.body }}</p>
        </div>
    </div>
</template>
