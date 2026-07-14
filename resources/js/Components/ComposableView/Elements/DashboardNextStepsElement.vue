<script setup lang="ts">
defineProps<{
    data?: string[];
}>();
</script>

<template>
    <ul class="space-y-3 p-4 text-sm text-zinc-600 dark:text-zinc-300">
        <li v-for="item in data" :key="item">{{ item }}</li>
    </ul>
</template>
