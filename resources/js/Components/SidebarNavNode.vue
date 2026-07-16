<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconChevronDown } from '@tabler/icons-vue';

import Tooltip from './Tooltip.vue';
import { useSidebar } from '../Composables/useSidebar';
import type { NavigationNode } from '../Types/navigation';

const props = withDefaults(
    defineProps<{
        node: NavigationNode;
        depth?: number;
        collapsed: boolean;
        textVisible: boolean;
    }>(),
    {
        depth: 0,
    },
);

function expandedPadding(depth: number): string {
    return `${0.75 + depth * 0.85}rem`;
}

function navigationItemStyle(depth: number, textVisible: boolean): Record<string, string> | undefined {
    if (textVisible) {
        return { paddingLeft: expandedPadding(depth), paddingRight: '0.75rem' };
    }

    return undefined;
}

const { isNavigationNodeExpanded, setNavigationNodeExpanded } = useSidebar();

function updateExpandedNavigationState(event: Event): void {
    const target = event.currentTarget;

    if (target instanceof HTMLDetailsElement) {
        setNavigationNodeExpanded(props.node.key, target.open);
    }
}
</script>

<template>
    <details
        v-if="node.children?.length"
        class="group/nav-node space-y-1"
        :open="isNavigationNodeExpanded(node.key)"
        @toggle="updateExpandedNavigationState"
    >
        <summary
            class="flex h-10 w-full cursor-pointer list-none items-center rounded-lg text-sm font-medium text-zinc-500 transition-[padding,color,background-color,border-color] duration-300 ease-in-out hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-50"
            :class="textVisible ? 'justify-between' : 'justify-center gap-1 px-0'"
            :style="navigationItemStyle(depth, textVisible)"
        >
            <Tooltip
                :text="node.label"
                :disabled="textVisible"
                :full-width="textVisible"
                placement="right"
                :class="textVisible ? 'w-full' : ''"
            >
                <span
                    class="flex items-center transition-[gap] duration-300 ease-in-out"
                    :class="textVisible ? 'w-full justify-between' : 'justify-center gap-1'"
                >
                    <span
                        class="flex min-w-0 items-center transition-[gap] duration-300 ease-in-out"
                        :class="textVisible ? 'gap-3' : 'gap-0'"
                    >
                        <component
                            :is="node.icon"
                            aria-hidden="true"
                            class="h-5 w-5 shrink-0 transition-transform duration-300 ease-in-out"
                            :stroke-width="1.8"
                        />
                        <span
                            class="overflow-hidden truncate whitespace-nowrap transition-[max-width,opacity,transform] duration-300 ease-in-out"
                            :class="
                                textVisible ? 'max-w-40 translate-x-0 opacity-100' : 'pointer-events-none max-w-0 -translate-x-1 opacity-0'
                            "
                        >
                            {{ node.label }}
                        </span>
                    </span>
                    <IconChevronDown
                        aria-hidden="true"
                        class="h-4 w-4 shrink-0 text-zinc-400 transition-transform duration-300 ease-in-out dark:text-zinc-500"
                        :class="isNavigationNodeExpanded(node.key) ? 'rotate-180' : 'rotate-0'"
                        :stroke-width="1.8"
                    />
                </span>
            </Tooltip>
        </summary>
        <div class="space-y-1 pt-2">
            <SidebarNavNode
                v-for="child in node.children"
                :key="child.key"
                :node="child"
                :depth="depth + 1"
                :collapsed="collapsed"
                :text-visible="textVisible"
            />
        </div>
    </details>

    <Tooltip v-else :text="node.label" :disabled="textVisible" placement="right" class="w-full">
        <Link
            :href="node.href ?? '#'"
            class="group relative flex h-11 w-full items-center rounded-lg text-sm font-medium transition-[padding,color,background-color] duration-300 ease-in-out"
            :class="[
                node.active
                    ? 'bg-teal-50 text-teal-900 ring-1 ring-teal-100 dark:bg-teal-950 dark:text-teal-100 dark:ring-teal-900'
                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-50',
                textVisible ? 'gap-3' : 'justify-center gap-0 px-0',
            ]"
            :style="navigationItemStyle(depth, textVisible)"
        >
            <component
                :is="node.icon"
                aria-hidden="true"
                class="h-5 w-5 shrink-0 transition-transform duration-300 ease-in-out"
                :class="{ 'translate-x-0': collapsed }"
                :stroke-width="1.8"
            />
            <span
                class="overflow-hidden truncate whitespace-nowrap transition-[max-width,opacity,transform] duration-300 ease-in-out"
                :class="textVisible ? 'max-w-40 translate-x-0 opacity-100' : 'pointer-events-none max-w-0 -translate-x-1 opacity-0'"
            >
                {{ node.label }}
            </span>
        </Link>
    </Tooltip>
</template>
