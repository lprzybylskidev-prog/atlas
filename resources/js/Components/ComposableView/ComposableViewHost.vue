<script setup lang="ts">
import { computed } from 'vue';

import type { ResolvedComposableHostView } from '../../Types/composable-view';
import ComposableViewElement from './ComposableViewElement.vue';

const props = defineProps<{
    view: ResolvedComposableHostView;
}>();

const mainElements = computed(() => props.view.elements.filter((element) => element.placement.area === 'main'));
const asideElements = computed(() => props.view.elements.filter((element) => element.placement.area === 'aside'));
const fullElements = computed(() => props.view.elements.filter((element) => element.placement.area === 'full'));
</script>

<template>
    <div class="space-y-4">
        <div
            v-if="view.missingStructuralElementKeys.length > 0"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
        >
            Missing structural elements: {{ view.missingStructuralElementKeys.join(', ') }}
        </div>

        <div v-if="fullElements.length > 0" class="grid gap-4">
            <ComposableViewElement v-for="element in fullElements" :key="element.definition.key" :element="element" />
        </div>

        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_24rem]">
            <section class="grid gap-4 xl:grid-cols-4">
                <ComposableViewElement v-for="element in mainElements" :key="element.definition.key" :element="element" />
            </section>

            <aside v-if="asideElements.length > 0" class="space-y-4">
                <ComposableViewElement v-for="element in asideElements" :key="element.definition.key" :element="element" />
            </aside>
        </div>
    </div>
</template>
