<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconGauge, IconReportAnalytics, IconSearch, IconShieldLock, IconUsers } from '@tabler/icons-vue';
import type { FunctionalComponent } from 'vue';

import AtlasLogo from './AtlasLogo.vue';
import { useSidebar } from '../Composables/useSidebar';

interface NavigationItem {
    label: string;
    href: string;
    icon: FunctionalComponent;
    active: boolean;
}

const props = defineProps<{
    currentPath: string;
}>();

const { isSidebarCollapsed } = useSidebar();

const items: NavigationItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: IconGauge, active: props.currentPath === '/dashboard' },
    { label: 'Sprawy', href: '/dashboard', icon: IconReportAnalytics, active: false },
    { label: 'Dłużnicy', href: '/dashboard', icon: IconUsers, active: false },
    { label: 'Wyszukiwanie', href: '/dashboard', icon: IconSearch, active: false },
    { label: 'Admin', href: '/admin', icon: IconShieldLock, active: props.currentPath.startsWith('/admin') },
];
</script>

<template>
    <aside
        class="hidden min-h-screen shrink-0 border-r border-zinc-200 bg-white transition-[width] duration-200 lg:block dark:border-zinc-800 dark:bg-zinc-950"
        :class="isSidebarCollapsed ? 'w-[5.25rem]' : 'w-72'"
    >
        <div
            class="flex h-16 items-center border-b border-zinc-200 px-4 dark:border-zinc-800"
            :class="isSidebarCollapsed ? 'justify-center' : 'justify-start'"
        >
            <AtlasLogo :show-text="!isSidebarCollapsed" />
        </div>

        <nav class="space-y-1 px-3 py-4" aria-label="Główna nawigacja">
            <Link
                v-for="item in items"
                :key="item.label"
                :href="item.href"
                class="group relative flex h-11 items-center rounded-lg px-3 text-sm font-medium transition"
                :class="[
                    isSidebarCollapsed ? 'justify-center' : 'gap-3',
                    item.active
                        ? 'bg-teal-50 text-teal-900 ring-1 ring-teal-100 dark:bg-teal-950 dark:text-teal-100 dark:ring-teal-900'
                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-50',
                ]"
            >
                <component :is="item.icon" aria-hidden="true" class="h-5 w-5 shrink-0" :stroke-width="1.8" />
                <span v-if="!isSidebarCollapsed" class="truncate">{{ item.label }}</span>
                <span
                    v-else
                    class="pointer-events-none absolute left-full z-50 ml-2 hidden rounded-md bg-zinc-950 px-2 py-1 text-xs font-medium text-white shadow-lg group-hover:block group-focus-visible:block dark:bg-zinc-100 dark:text-zinc-950"
                >
                    {{ item.label }}
                </span>
            </Link>
        </nav>
    </aside>
</template>
