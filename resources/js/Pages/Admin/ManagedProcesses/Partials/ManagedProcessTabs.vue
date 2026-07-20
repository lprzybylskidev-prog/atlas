<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconCalendarTime, IconFileImport, IconListDetails, IconRotateClockwise } from '@tabler/icons-vue';

defineProps<{
    active: 'runs' | 'imports' | 'definitions' | 'schedules';
}>();

const tabs = [
    { key: 'runs', label: 'Runs', href: '/admin/managed-processes', icon: IconRotateClockwise },
    { key: 'imports', label: 'Imports', href: '/admin/managed-processes/imports', icon: IconFileImport },
    { key: 'definitions', label: 'Definitions', href: '/admin/managed-processes/definitions', icon: IconListDetails },
    { key: 'schedules', label: 'Schedules', href: '/admin/managed-processes/schedules', icon: IconCalendarTime },
] as const;
</script>

<template>
    <nav
        class="flex flex-wrap gap-2 rounded-lg border border-zinc-200 bg-white p-1 shadow-sm dark:border-zinc-800 dark:bg-zinc-950"
        aria-label="Managed process tabs"
    >
        <Link
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.href"
            class="inline-flex h-9 items-center gap-2 rounded-md px-3 text-sm font-medium transition"
            :class="
                active === tab.key
                    ? 'bg-teal-700 text-white dark:bg-teal-600'
                    : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-50'
            "
            :aria-current="active === tab.key ? 'page' : undefined"
        >
            <component :is="tab.icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
            {{ tab.label }}
        </Link>
    </nav>
</template>
