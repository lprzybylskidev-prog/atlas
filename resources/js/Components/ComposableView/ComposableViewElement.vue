<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';

import type { ComposableViewDataProviderResult, ResolvedComposableViewElement } from '../../Types/composable-view';

const props = defineProps<{
    element: ResolvedComposableViewElement;
}>();

const loading = ref(true);
const error = ref<string | null>(null);
const result = ref<ComposableViewDataProviderResult<unknown> | null>(null);

const isPermissionDenied = computed(() => props.element.availability.reason === 'permission-denied');

onMounted(async () => {
    if (props.element.availability.reason !== 'available') {
        loading.value = false;

        return;
    }

    try {
        result.value = await props.element.definition.dataProvider();
    } catch (caughtError: unknown) {
        error.value = caughtError instanceof Error ? caughtError.message : 'Element data failed to load.';
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <article
        class="rounded-lg border border-zinc-200 bg-white dark:border-zinc-800 dark:bg-zinc-900"
        :class="[element.placement.dimensions.minHeightClass, element.placement.dimensions.spanClass]"
    >
        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
            <h2 class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ element.definition.fallbackTitle }}</h2>
            <p v-if="element.definition.fallbackDescription" class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                {{ element.definition.fallbackDescription }}
            </p>
        </div>

        <div v-if="loading" class="p-4 text-sm text-zinc-500 dark:text-zinc-400">Loading...</div>

        <div v-else-if="isPermissionDenied" class="p-4 text-sm text-zinc-600 dark:text-zinc-300">Permission required.</div>

        <div v-else-if="element.availability.reason !== 'available'" class="p-4 text-sm text-zinc-600 dark:text-zinc-300">
            Element unavailable.
        </div>

        <div v-else-if="error" class="p-4 text-sm text-red-700 dark:text-red-300">
            {{ error }}
        </div>

        <div v-else-if="result?.empty" class="p-4 text-sm text-zinc-500 dark:text-zinc-400">No data available.</div>

        <component :is="element.definition.component" v-else :data="result?.data" />
    </article>
</template>
