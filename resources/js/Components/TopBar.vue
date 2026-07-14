<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    IconBell,
    IconChevronDown,
    IconLogout,
    IconMenu2,
    IconMoon,
    IconSettings,
    IconShieldLock,
    IconSun,
    IconUsersGroup,
} from '@tabler/icons-vue';
import { computed } from 'vue';

import IconButton from './IconButton.vue';
import StatusPill from './StatusPill.vue';
import { useTheme } from '../Composables/useTheme';
import type { AtlasPageProps } from '../Types/inertia';

defineProps<{
    title: string;
    section: string;
}>();

const emit = defineEmits<{
    openMobileMenu: [];
}>();

const page = usePage<AtlasPageProps>();
const { isDark, toggleTheme } = useTheme();

const userInitials = computed(() => {
    const name = page.props.auth.user?.name ?? 'Atlas User';

    return name
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');
});

const logout = (): void => {
    router.post('/logout');
};
</script>

<template>
    <header class="sticky top-0 z-30 border-b border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-800 dark:bg-zinc-950/90">
        <div class="flex min-h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 text-zinc-700 lg:hidden dark:border-zinc-800 dark:text-zinc-200"
                    aria-label="Otwórz nawigację"
                    @click="emit('openMobileMenu')"
                >
                    <IconMenu2 aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                </button>
                <div class="min-w-0">
                    <div class="flex items-center gap-2 text-xs text-zinc-500 dark:text-zinc-400">
                        <span>{{ section }}</span>
                        <span aria-hidden="true">/</span>
                        <span>Atlas</span>
                    </div>
                    <h1 class="truncate text-base font-semibold text-zinc-950 dark:text-zinc-50 sm:text-lg">{{ title }}</h1>
                </div>
            </div>

            <div class="flex min-w-0 items-center gap-2">
                <div
                    class="hidden items-center gap-2 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700 md:flex dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200"
                >
                    <IconUsersGroup aria-hidden="true" class="h-4 w-4 text-teal-700 dark:text-teal-300" :stroke-width="1.8" />
                    <span class="font-medium">Atlas Operations</span>
                    <IconChevronDown aria-hidden="true" class="h-4 w-4 text-zinc-400" :stroke-width="1.8" />
                </div>

                <StatusPill tone="info">
                    <span class="hidden sm:inline">Active team</span>
                    <span class="sm:hidden">Team</span>
                </StatusPill>

                <IconButton
                    :label="isDark ? 'Włącz jasny motyw' : 'Włącz ciemny motyw'"
                    :icon="isDark ? IconSun : IconMoon"
                    :active="isDark"
                    @click="toggleTheme"
                />

                <IconButton label="Powiadomienia" :icon="IconBell" />
                <IconButton label="Ustawienia" :icon="IconSettings" />

                <Link
                    href="/admin"
                    class="hidden h-10 items-center gap-2 rounded-lg border border-zinc-200 bg-white px-3 text-sm font-medium text-zinc-700 transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-800 lg:inline-flex dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-teal-700 dark:hover:bg-teal-950"
                >
                    <IconShieldLock aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    Admin
                </Link>

                <div class="hidden items-center gap-2 pl-1 sm:flex">
                    <div
                        class="flex h-10 w-10 items-center justify-center rounded-lg bg-zinc-900 text-sm font-semibold text-white dark:bg-zinc-100 dark:text-zinc-950"
                    >
                        {{ userInitials }}
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-zinc-100"
                        aria-label="Wyloguj"
                        @click="logout"
                    >
                        <IconLogout aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>
