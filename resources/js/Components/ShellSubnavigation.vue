<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type { ShellSubnavigationItem } from '../Types/navigation';

defineProps<{
    items: ShellSubnavigationItem[];
    label: string;
}>();
</script>

<template>
    <nav v-if="items.some((item) => item.visible !== false)" class="border-t border-zinc-200 dark:border-zinc-800" :aria-label="label">
        <div class="flex gap-1 overflow-x-auto px-4 py-2 sm:px-6 lg:px-8">
            <Link
                v-for="item in items.filter((entry) => entry.visible !== false)"
                :key="item.key"
                :href="item.href"
                class="inline-flex h-9 shrink-0 items-center gap-2 rounded-md px-3 text-sm font-medium transition focus-visible:outline focus-visible:outline-amber-500"
                :class="
                    item.active
                        ? 'bg-teal-700 text-white dark:bg-teal-600'
                        : 'text-zinc-600 hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-300 dark:hover:bg-zinc-900 dark:hover:text-zinc-50'
                "
                :aria-current="item.active ? 'page' : undefined"
            >
                <component v-if="item.icon" :is="item.icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                {{ item.label }}
            </Link>
        </div>
    </nav>
</template>
