<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    IconLanguage,
    IconLayoutSidebarLeftCollapse,
    IconLayoutSidebarLeftExpand,
    IconLogout,
    IconMenu2,
    IconMoon,
    IconShieldLock,
    IconSun,
} from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import IconButton from './IconButton.vue';
import { useLocaleSwitcher } from '../Composables/useLocaleSwitcher';
import { useSidebar } from '../Composables/useSidebar';
import { useTheme } from '../Composables/useTheme';
import { useTranslator } from '../Localization/translator';
import type { AtlasPageProps } from '../Types/inertia';

const props = withDefaults(
    defineProps<{
        title: string;
        mode?: 'app' | 'admin';
        showLocaleSwitcher?: boolean;
        uiLocale?: string;
    }>(),
    {
        mode: 'app',
        showLocaleSwitcher: true,
        uiLocale: undefined,
    },
);

const emit = defineEmits<{
    openMobileMenu: [];
}>();

const page = usePage<AtlasPageProps>();
const { isSidebarCollapsed, toggleSidebar } = useSidebar();
const { isDark, toggleTheme } = useTheme();
const { switchLocale } = useLocaleSwitcher();
const { t } = useTranslator(props.uiLocale);
const userMenuOpen = ref(false);
const userMenuButton = ref<HTMLElement | null>(null);
const userMenuPanel = ref<HTMLElement | null>(null);

const userInitials = computed(() => {
    const name = page.props.auth.user?.name ?? t('user.default_name');

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

const toggleUserMenu = (): void => {
    userMenuOpen.value = !userMenuOpen.value;
};

const closeUserMenu = (): void => {
    userMenuOpen.value = false;
};

const breadcrumbs = computed(() => page.props.navigation.breadcrumbs);
const isAdminMode = computed(() => props.mode === 'admin');

const handleOutsidePointerDown = (event: PointerEvent): void => {
    const target = event.target;

    if (!(target instanceof Node)) {
        return;
    }

    if (userMenuButton.value?.contains(target) || userMenuPanel.value?.contains(target)) {
        return;
    }

    closeUserMenu();
};

const handleEscape = (event: KeyboardEvent): void => {
    if (event.key === 'Escape') {
        closeUserMenu();
    }
};

onMounted(() => {
    document.addEventListener('pointerdown', handleOutsidePointerDown);
    document.addEventListener('keydown', handleEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleOutsidePointerDown);
    document.removeEventListener('keydown', handleEscape);
});
</script>

<template>
    <header
        class="sticky top-0 z-30 border-b backdrop-blur"
        :class="
            isAdminMode
                ? 'border-zinc-300 bg-zinc-950 text-zinc-50 dark:border-zinc-800 dark:bg-black'
                : 'border-zinc-200 bg-white/95 dark:border-zinc-800 dark:bg-zinc-950/90'
        "
    >
        <div class="flex min-h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-zinc-200 text-zinc-700 lg:hidden dark:border-zinc-800 dark:text-zinc-200"
                    :aria-label="t('actions.open_navigation')"
                    @click="emit('openMobileMenu')"
                >
                    <IconMenu2 aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                </button>
                <button
                    type="button"
                    class="hidden h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-zinc-200 text-zinc-600 transition hover:border-teal-200 hover:bg-teal-50 hover:text-teal-800 lg:inline-flex dark:border-zinc-800 dark:text-zinc-300 dark:hover:border-teal-700 dark:hover:bg-teal-950/50 dark:hover:text-teal-100"
                    :aria-label="isSidebarCollapsed ? t('actions.expand_sidebar') : t('actions.collapse_sidebar')"
                    @click="toggleSidebar"
                >
                    <component
                        :is="isSidebarCollapsed ? IconLayoutSidebarLeftExpand : IconLayoutSidebarLeftCollapse"
                        aria-hidden="true"
                        class="h-5 w-5"
                        :stroke-width="1.8"
                    />
                </button>
                <div class="min-w-0">
                    <nav
                        class="flex min-w-0 items-center gap-2 text-xs"
                        :class="isAdminMode ? 'text-zinc-400' : 'text-zinc-500 dark:text-zinc-400'"
                        :aria-label="t('navigation.aria.breadcrumb')"
                    >
                        <template v-for="(breadcrumb, index) in breadcrumbs" :key="`${breadcrumb.label}-${index}`">
                            <span v-if="index > 0" aria-hidden="true">/</span>
                            <Link
                                v-if="breadcrumb.url !== null && index < breadcrumbs.length - 1"
                                :href="breadcrumb.url"
                                class="truncate hover:text-teal-700 focus-visible:outline focus-visible:outline-amber-500 dark:hover:text-teal-300"
                            >
                                {{ breadcrumb.label }}
                            </Link>
                            <span v-else class="truncate" :aria-current="index === breadcrumbs.length - 1 ? 'page' : undefined">
                                {{ breadcrumb.label }}
                            </span>
                        </template>
                    </nav>
                    <h1 class="truncate text-base font-semibold sm:text-lg" :class="isAdminMode ? 'text-zinc-50' : 'text-zinc-950 dark:text-zinc-50'">
                        {{ title }}
                    </h1>
                </div>
            </div>

            <div class="flex min-w-0 items-center gap-2">
                <IconButton
                    :label="isDark ? t('actions.switch_light_theme') : t('actions.switch_dark_theme')"
                    :icon="isDark ? IconSun : IconMoon"
                    :active="isDark"
                    @click="toggleTheme"
                />

                <IconButton v-if="showLocaleSwitcher" :label="t('actions.change_language')" :icon="IconLanguage" @click="switchLocale" />

                <div class="relative pl-1">
                    <button
                        ref="userMenuButton"
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-full bg-zinc-950 text-sm font-semibold text-white shadow-sm ring-2 ring-white transition hover:bg-teal-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 dark:bg-zinc-100 dark:text-zinc-950 dark:ring-zinc-950 dark:hover:bg-teal-200"
                        aria-haspopup="menu"
                        :aria-expanded="userMenuOpen"
                        :aria-label="t('user.menu')"
                        @click="toggleUserMenu"
                    >
                        {{ userInitials }}
                    </button>

                    <div
                        v-if="userMenuOpen"
                        ref="userMenuPanel"
                        class="absolute right-0 top-12 z-50 w-80 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xl shadow-zinc-950/10 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30"
                        role="menu"
                        :aria-label="t('user.menu')"
                    >
                        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <p class="truncate text-sm font-semibold text-zinc-950 dark:text-zinc-50">
                                {{ page.props.auth.user?.name ?? t('user.default_name') }}
                            </p>
                            <p class="truncate text-xs text-zinc-500 dark:text-zinc-400">{{ page.props.auth.user?.email }}</p>
                        </div>

                        <div class="p-2">
                            <Link
                                href="/admin"
                                class="flex h-10 items-center gap-3 rounded-lg px-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                                role="menuitem"
                                @click="closeUserMenu"
                            >
                                <IconShieldLock aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                                {{ t('navigation.admin_panel') }}
                            </Link>
                            <button
                                type="button"
                                class="flex h-10 w-full items-center gap-3 rounded-lg px-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                                role="menuitem"
                                @click="logout"
                            >
                                <IconLogout aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                                {{ t('actions.logout') }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>
