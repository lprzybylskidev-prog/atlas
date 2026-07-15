<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconGauge } from '@tabler/icons-vue';
import type { FunctionalComponent } from 'vue';
import { computed } from 'vue';

import AtlasLogo from './AtlasLogo.vue';
import { useSidebar } from '../Composables/useSidebar';
import { useTranslator } from '../Localization/translator';

interface NavigationItem {
    label: string;
    href: string;
    icon: FunctionalComponent;
    active: boolean;
}

const props = defineProps<{
    currentPath: string;
    uiLocale?: string;
}>();

const { isSidebarCollapsed, isSidebarTextVisible } = useSidebar();
const { t } = useTranslator(props.uiLocale);

const items = computed<NavigationItem[]>(() => [
    { label: t('navigation.dashboard'), href: '/', icon: IconGauge, active: props.currentPath === '/' },
]);
</script>

<template>
    <aside
        class="hidden min-h-screen shrink-0 overflow-visible border-r border-zinc-200 bg-white transition-[width] duration-300 ease-in-out lg:block dark:border-zinc-800 dark:bg-zinc-950"
        :class="isSidebarCollapsed ? 'w-[5.25rem]' : 'w-72'"
    >
        <div class="flex h-16 items-center justify-start border-b border-zinc-200 px-4 dark:border-zinc-800">
            <AtlasLogo
                :show-text="isSidebarTextVisible"
                animate-text
                :ui-locale="uiLocale"
                class="transition-transform duration-300 ease-in-out will-change-transform"
                :style="{ transform: isSidebarCollapsed ? 'translateX(0.5rem)' : 'translateX(0)' }"
            />
        </div>

        <nav class="space-y-1 px-3 py-4" :aria-label="t('navigation.aria.main')">
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                class="group relative flex h-11 w-full items-center gap-3 rounded-lg px-3 text-sm font-medium transition"
                :class="[
                    item.active
                        ? 'bg-teal-50 text-teal-900 ring-1 ring-teal-100 dark:bg-teal-950 dark:text-teal-100 dark:ring-teal-900'
                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-50',
                ]"
            >
                <component
                    :is="item.icon"
                    aria-hidden="true"
                    class="h-5 w-5 shrink-0 transition-transform duration-300 ease-in-out"
                    :class="{ 'translate-x-2': isSidebarCollapsed }"
                    :stroke-width="1.8"
                />
                <span
                    class="overflow-hidden truncate whitespace-nowrap transition-[max-width,opacity,transform] duration-300 ease-in-out"
                    :class="
                        isSidebarTextVisible ? 'max-w-40 translate-x-0 opacity-100' : 'pointer-events-none max-w-0 -translate-x-1 opacity-0'
                    "
                >
                    {{ item.label }}
                </span>
                <span
                    v-if="isSidebarCollapsed"
                    class="pointer-events-none absolute left-full z-50 ml-2 hidden rounded-md bg-zinc-950 px-2 py-1 text-xs font-medium text-white shadow-lg group-hover:block group-focus-visible:block dark:bg-zinc-100 dark:text-zinc-950"
                >
                    {{ item.label }}
                </span>
            </Link>
        </nav>
    </aside>
</template>
