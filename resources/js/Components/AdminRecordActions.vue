<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import {
    IconKey,
    IconLockOpen,
    IconMailCheck,
    IconPencil,
    IconRefresh,
    IconTrash,
    IconUserCheck,
    IconUserOff,
    IconUserScan,
} from '@tabler/icons-vue';
import type { Component } from 'vue';

interface RecordAction {
    key: string;
    label: string;
    href: string;
    method?: 'get' | 'post' | 'patch' | 'delete';
    tone?: 'neutral' | 'success' | 'warning' | 'danger';
}

defineProps<{
    actions: RecordAction[];
}>();

function actionIcon(action: RecordAction): Component {
    if (action.key.includes('deactivate')) {
        return IconUserOff;
    }

    if (action.key.includes('activate')) {
        return IconUserCheck;
    }

    if (action.key.includes('verify') || action.key.includes('verification')) {
        return IconMailCheck;
    }

    if (action.key.includes('password') || action.key.includes('link')) {
        return IconKey;
    }

    if (action.key.includes('unlock')) {
        return IconLockOpen;
    }

    if (action.key.includes('reset')) {
        return IconRefresh;
    }

    if (action.key.includes('impersonate')) {
        return IconUserScan;
    }

    if (action.key.includes('delete') || action.key.includes('destroy') || action.key.includes('deactivate-preset')) {
        return IconTrash;
    }

    return IconPencil;
}

function actionTone(action: RecordAction): RecordAction['tone'] {
    if (action.tone !== undefined) {
        return action.tone;
    }

    if (
        action.method === 'delete' ||
        action.key.includes('delete') ||
        action.key.includes('destroy') ||
        action.key.includes('deactivate')
    ) {
        return 'danger';
    }

    if (action.key.includes('require') && action.key.includes('verification')) {
        return 'warning';
    }

    if (action.key.includes('activate') || action.key.includes('verify') || action.key.includes('unlock')) {
        return 'success';
    }

    if (action.key.includes('reset') || action.key.includes('password')) {
        return 'warning';
    }

    return 'neutral';
}

function actionClass(action: RecordAction): string {
    const tone = actionTone(action);

    if (tone === 'success') {
        return 'border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-900 dark:text-emerald-300 dark:hover:bg-emerald-950';
    }

    if (tone === 'warning') {
        return 'border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-900 dark:text-amber-300 dark:hover:bg-amber-950';
    }

    if (tone === 'danger') {
        return 'border-rose-200 text-rose-700 hover:bg-rose-50 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950';
    }

    return 'border-zinc-300 text-zinc-600 hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:text-zinc-300 dark:hover:border-zinc-600 dark:hover:bg-zinc-900 dark:hover:text-zinc-50';
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Link
            v-for="action in actions"
            :key="action.key"
            :href="action.href"
            :method="action.method ?? 'get'"
            as="button"
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-medium transition"
            :class="actionClass(action)"
            :preserve-scroll="true"
        >
            <component :is="actionIcon(action)" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
            {{ action.label }}
        </Link>
    </div>
</template>
