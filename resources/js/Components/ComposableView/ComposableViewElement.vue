<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { IconLayoutDashboard } from '@tabler/icons-vue';

import { useTranslator } from '../../Localization/translator';
import type { ComposableViewDataProviderResult, ResolvedComposableViewElement } from '../../Types/composable-view';
import SurfaceCard from '../SurfaceCard.vue';

const props = defineProps<{
    element: ResolvedComposableViewElement;
}>();

const loading = ref(true);
const error = ref<string | null>(null);
const result = ref<ComposableViewDataProviderResult<unknown> | null>(null);
const { t } = useTranslator();

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
    <SurfaceCard
        :title="element.definition.fallbackTitle"
        :subtitle="element.definition.fallbackDescription ?? undefined"
        :icon="IconLayoutDashboard"
        :padded="false"
        overflow="hidden"
        :class="[element.placement.dimensions.minHeightClass, element.placement.dimensions.spanClass]"
    >
        <div v-if="loading" class="p-4 text-sm text-zinc-500 dark:text-zinc-400">{{ t('composable_view.loading') }}</div>

        <div v-else-if="isPermissionDenied" class="p-4 text-sm text-zinc-600 dark:text-zinc-300">
            {{ t('composable_view.permission_required') }}
        </div>

        <div v-else-if="element.availability.reason !== 'available'" class="p-4 text-sm text-zinc-600 dark:text-zinc-300">
            {{ t('composable_view.unavailable') }}
        </div>

        <div v-else-if="error" class="p-4 text-sm text-red-700 dark:text-red-300">
            {{ error }}
        </div>

        <div v-else-if="result?.empty" class="p-4 text-sm text-zinc-500 dark:text-zinc-400">
            {{ t('composable_view.empty') }}
        </div>

        <component :is="element.definition.component" v-else :data="result?.data" />
    </SurfaceCard>
</template>
