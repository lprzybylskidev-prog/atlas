<script setup lang="ts">
defineProps<{
    id?: string;
    error?: string;
}>();
</script>

<template>
    <p v-if="error" :id="id" class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>
</template>
