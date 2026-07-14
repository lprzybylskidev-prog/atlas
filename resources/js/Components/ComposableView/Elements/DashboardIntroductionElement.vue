<script setup lang="ts">
import StatusPill from '../../StatusPill.vue';

interface IntroductionData {
    eyebrow: string;
    title: string;
    body: string;
    status: string;
}

defineProps<{
    data?: IntroductionData;
}>();
</script>

<template>
    <div v-if="data" class="p-4 sm:p-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-sm font-medium text-teal-700 dark:text-teal-300">{{ data.eyebrow }}</p>
                <h2 class="mt-1 text-xl font-semibold text-zinc-950 dark:text-zinc-50">{{ data.title }}</h2>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    {{ data.body }}
                </p>
            </div>
            <StatusPill tone="success">{{ data.status }}</StatusPill>
        </div>
    </div>
</template>
