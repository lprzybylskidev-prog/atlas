<script setup lang="ts">
import { computed } from 'vue';

import { getComposableViewHostLayout } from '../../Services/composableViewHostLayouts';
import type { ComposableViewElementArea, ResolvedComposableHostView, ResolvedComposableViewElement } from '../../Types/composable-view';
import ComposableViewElement from './ComposableViewElement.vue';

const props = defineProps<{
    view: ResolvedComposableHostView;
}>();

const layout = computed(() => getComposableViewHostLayout(props.view.host));

function elementsForArea(area: ComposableViewElementArea): ResolvedComposableViewElement[] {
    return props.view.elements.filter((element) => element.placement.area === area);
}
</script>

<template>
    <div :class="layout.containerClass">
        <div
            v-if="view.missingStructuralElementKeys.length > 0"
            class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950 dark:text-red-200"
        >
            Missing structural elements: {{ view.missingStructuralElementKeys.join(', ') }}
        </div>

        <template v-for="areaDefinition in layout.areas" :key="areaDefinition.area">
            <section v-if="elementsForArea(areaDefinition.area).length > 0" :class="areaDefinition.wrapperClass">
                <div :class="areaDefinition.listClass">
                    <ComposableViewElement
                        v-for="element in elementsForArea(areaDefinition.area)"
                        :key="element.definition.key"
                        :element="element"
                    />
                </div>
            </section>
        </template>
    </div>
</template>
