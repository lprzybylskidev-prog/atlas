<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

import type { ShellSubnavigationItem } from '../Types/navigation';

withDefaults(
    defineProps<{
        items: ShellSubnavigationItem[];
        label: string;
        variant?: 'inline' | 'bar' | 'stacked';
    }>(),
    {
        variant: 'bar',
    },
);

const emit = defineEmits<{ navigate: [] }>();
</script>

<template>
    <nav
        v-if="items.some((item) => item.visible !== false)"
        :class="variant === 'bar' ? 'border-t border-zinc-200 dark:border-zinc-800' : 'min-w-0'"
        :aria-label="label"
    >
        <div
            class="gap-1"
            :class="[
                variant === 'bar' ? 'flex overflow-x-auto px-4 py-2 sm:px-6 lg:px-8' : '',
                variant === 'inline' ? 'flex max-w-full overflow-x-auto' : '',
                variant === 'stacked' ? 'grid' : '',
            ]"
        >
            <Link
                v-for="item in items.filter((entry) => entry.visible !== false)"
                :key="item.key"
                :href="item.href"
                class="inline-flex min-h-9 shrink-0 items-center gap-1.5 rounded-md px-2 text-sm font-medium transition focus-visible:outline focus-visible:outline-amber-500"
                :class="
                    item.active
                        ? 'text-teal-700 dark:text-teal-300'
                        : 'text-zinc-500 hover:text-zinc-950 dark:text-zinc-400 dark:hover:text-zinc-50'
                "
                :aria-current="item.active ? 'page' : undefined"
                @click="emit('navigate')"
            >
                <component v-if="item.icon" :is="item.icon" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                {{ item.label }}
            </Link>
        </div>
    </nav>
</template>
