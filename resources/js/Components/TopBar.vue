<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    IconAlertTriangle,
    IconBell,
    IconCircleCheck,
    IconCircleX,
    IconInfoCircle,
    IconLanguage,
    IconLayoutSidebarLeftCollapse,
    IconLayoutSidebarLeftExpand,
    IconLogout,
    IconMenu2,
    IconMoon,
    IconShieldLock,
    IconSun,
} from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { Component } from 'vue';

import IconButton from './IconButton.vue';
import FormSelect from './Form/FormSelect.vue';
import ShellSubnavigation from './ShellSubnavigation.vue';
import TruncatedText from './TruncatedText.vue';
import { useLocaleSwitcher } from '../Composables/useLocaleSwitcher';
import { useSidebar } from '../Composables/useSidebar';
import { useTheme } from '../Composables/useTheme';
import { useTranslator } from '../Localization/translator';
import { clearTeamScopedState } from '../Services/teamScopedState';
import type { AtlasPageProps } from '../Types/inertia';
import type { ShellSubnavigationItem } from '../Types/navigation';
import { formatTimestamp } from '../Utils/formatters';

const props = withDefaults(
    defineProps<{
        title: string;
        titleIcon?: Component;
        mode?: 'app' | 'admin';
        showLocaleSwitcher?: boolean;
        uiLocale?: string;
        subnavigation?: ShellSubnavigationItem[];
        subnavigationLabel?: string;
    }>(),
    {
        mode: 'app',
        titleIcon: undefined,
        showLocaleSwitcher: true,
        uiLocale: undefined,
        subnavigation: () => [],
        subnavigationLabel: 'Section navigation',
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
const notificationSoundArmed = ref(false);
const previousUnreadCount = ref(page.props.notifications.unreadCount);

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

const notificationHref = (deepLinkUrl: string | null): string => deepLinkUrl ?? '/notifications';

const isNativeNotificationHref = (deepLinkUrl: string | null): boolean => notificationHref(deepLinkUrl).startsWith('/exports/');

const breadcrumbs = computed(() => page.props.navigation.breadcrumbs);
const isAdminMode = computed(() => props.mode === 'admin');
const canEnterAdmin = computed(() => page.props.auth.availableAdminRoutes.includes('admin.system-status'));
const activeTeamPublicId = computed(() => page.props.auth.teams.active?.publicId ?? '');
const availableTeamOptions = computed(() => page.props.auth.teams.available.map((team) => ({ value: team.publicId, label: team.name })));
const latestNotifications = computed(() => page.props.notifications.latest);
const unreadCountLabel = computed(() => t('notifications.unread_count').replace('{count}', String(page.props.notifications.unreadCount)));
const avatarBadgeLabel = computed(() => String(Math.min(page.props.notifications.unreadCount, 99)));

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

const switchTeam = (teamPublicId: string | number): void => {
    if (typeof teamPublicId !== 'string' || teamPublicId === activeTeamPublicId.value) {
        return;
    }

    clearTeamScopedState();

    router.post(
        '/team/switch',
        { team_public_id: teamPublicId },
        {
            preserveScroll: false,
            preserveState: false,
        },
    );
};

const markNotificationRead = (notificationPublicId: string): void => {
    router.post(
        `/notifications/${notificationPublicId}/read`,
        {},
        {
            preserveScroll: true,
            preserveState: false,
        },
    );
};

const armNotificationSound = (): void => {
    notificationSoundArmed.value = true;
};

const playNotificationSound = (): void => {
    if (!notificationSoundArmed.value || typeof window === 'undefined') {
        return;
    }

    const audio = new Audio('/sounds/notification.wav');

    audio.play().catch(() => {
        notificationSoundArmed.value = false;
    });
};

const notificationSeverityIcon = (severity: string): Component => {
    const normalized = severity.toLowerCase();

    if (['warning', 'warn'].includes(normalized)) {
        return IconAlertTriangle;
    }

    if (['error', 'danger', 'failed', 'failure'].includes(normalized)) {
        return IconCircleX;
    }

    if (['success', 'ok', 'resolved'].includes(normalized)) {
        return IconCircleCheck;
    }

    return IconInfoCircle;
};

const notificationSeverityClass = (severity: string): string => {
    const normalized = severity.toLowerCase();

    if (['warning', 'warn'].includes(normalized)) {
        return 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-300 dark:ring-amber-900';
    }

    if (['error', 'danger', 'failed', 'failure'].includes(normalized)) {
        return 'bg-rose-50 text-rose-700 ring-rose-200 dark:bg-rose-950/40 dark:text-rose-300 dark:ring-rose-900';
    }

    if (['success', 'ok', 'resolved'].includes(normalized)) {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:ring-emerald-900';
    }

    return 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/40 dark:text-sky-300 dark:ring-sky-900';
};

const notificationTimestamp = (value: string): string => formatTimestamp(value, props.uiLocale ?? page.props.locale);

onMounted(() => {
    document.addEventListener('pointerdown', handleOutsidePointerDown);
    document.addEventListener('keydown', handleEscape);
    document.addEventListener('pointerdown', armNotificationSound, { once: true });
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', handleOutsidePointerDown);
    document.removeEventListener('keydown', handleEscape);
    document.removeEventListener('pointerdown', armNotificationSound);
});

watch(
    () => page.props.notifications.unreadCount,
    (nextCount) => {
        if (nextCount > previousUnreadCount.value) {
            playNotificationSound();
        }

        previousUnreadCount.value = nextCount;
    },
);
</script>

<template>
    <header
        class="sticky top-0 z-30 border-b backdrop-blur"
        :class="
            isAdminMode
                ? 'border-zinc-200 bg-white/95 text-zinc-950 dark:border-zinc-800 dark:bg-black/95 dark:text-zinc-50'
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
                        class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs"
                        :class="isAdminMode ? 'text-zinc-500 dark:text-zinc-400' : 'text-zinc-500 dark:text-zinc-400'"
                        :aria-label="t('navigation.aria.breadcrumb')"
                    >
                        <template v-for="(breadcrumb, index) in breadcrumbs" :key="`${breadcrumb.label}-${index}`">
                            <span v-if="index > 0" aria-hidden="true">/</span>
                            <Link
                                v-if="breadcrumb.url !== null && index < breadcrumbs.length - 1"
                                :href="breadcrumb.url"
                                class="hover:text-teal-700 focus-visible:outline focus-visible:outline-amber-500 dark:hover:text-teal-300"
                            >
                                {{ breadcrumb.label }}
                            </Link>
                            <span v-else :aria-current="index === breadcrumbs.length - 1 ? 'page' : undefined">
                                {{ breadcrumb.label }}
                            </span>
                        </template>
                    </nav>
                    <h1
                        class="flex min-w-0 items-center gap-2 text-base font-semibold sm:text-lg"
                        :class="isAdminMode ? 'text-zinc-950 dark:text-zinc-50' : 'text-zinc-950 dark:text-zinc-50'"
                    >
                        <component
                            :is="titleIcon"
                            v-if="titleIcon"
                            aria-hidden="true"
                            class="h-5 w-5 shrink-0 text-zinc-400"
                            :stroke-width="1.8"
                        />
                        <TruncatedText :text="title" text-class="text-inherit" />
                    </h1>
                </div>

                <ShellSubnavigation
                    v-if="subnavigation.length > 0"
                    class="hidden min-w-0 shrink lg:flex"
                    :items="subnavigation"
                    :label="subnavigationLabel"
                    variant="inline"
                />
            </div>

            <div class="flex min-w-0 items-center gap-2">
                <IconButton
                    :label="isDark ? t('actions.switch_light_theme') : t('actions.switch_dark_theme')"
                    :icon="isDark ? IconSun : IconMoon"
                    :active="isDark"
                    @click="toggleTheme"
                />

                <IconButton v-if="showLocaleSwitcher" :label="t('actions.change_language')" :icon="IconLanguage" @click="switchLocale" />

                <FormSelect
                    v-if="availableTeamOptions.length > 1"
                    :model-value="activeTeamPublicId"
                    :options="availableTeamOptions"
                    :aria-label="t('team.active')"
                    button-class="w-40 sm:w-52"
                    @update:model-value="switchTeam"
                />

                <div class="relative pl-1">
                    <button
                        ref="userMenuButton"
                        type="button"
                        class="relative flex h-10 w-10 items-center justify-center rounded-full bg-zinc-950 text-sm font-semibold text-white shadow-sm ring-2 ring-white transition hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 dark:bg-zinc-100 dark:text-zinc-950 dark:ring-zinc-950 dark:hover:bg-teal-200"
                        aria-haspopup="menu"
                        :aria-expanded="userMenuOpen"
                        :aria-label="t('user.menu')"
                        @click="toggleUserMenu"
                    >
                        {{ userInitials }}
                        <span
                            v-if="page.props.notifications.unreadCount > 0"
                            class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-rose-600 px-1.5 text-[0.65rem] font-bold leading-5 text-white ring-2 ring-white dark:ring-zinc-950"
                        >
                            {{ avatarBadgeLabel }}
                        </span>
                    </button>

                    <div
                        v-if="userMenuOpen"
                        ref="userMenuPanel"
                        class="absolute right-0 top-12 z-50 w-80 overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xl shadow-zinc-950/10 dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-black/30"
                        role="menu"
                        :aria-label="t('user.menu')"
                    >
                        <div class="border-b border-zinc-200 px-4 py-3 dark:border-zinc-800">
                            <TruncatedText
                                :text="page.props.auth.user?.name ?? t('user.default_name')"
                                text-class="text-sm font-semibold text-zinc-950 dark:text-zinc-50"
                            />
                            <TruncatedText
                                v-if="page.props.auth.user?.email"
                                :text="page.props.auth.user.email"
                                text-class="text-xs text-zinc-500 dark:text-zinc-400"
                            />
                        </div>

                        <div class="p-2">
                            <div class="border-b border-zinc-200 pb-2 dark:border-zinc-800">
                                <div class="mb-2 flex items-center justify-between gap-2 px-2">
                                    <div class="flex min-w-0 items-center gap-2">
                                        <IconBell
                                            aria-hidden="true"
                                            class="h-4 w-4 shrink-0 text-zinc-500 dark:text-zinc-400"
                                            :stroke-width="1.8"
                                        />
                                        <TruncatedText
                                            :text="t('notifications.dropdown.latest')"
                                            text-class="text-xs font-semibold uppercase text-zinc-500 dark:text-zinc-400"
                                        />
                                    </div>
                                    <span class="shrink-0 text-xs font-medium text-teal-700 dark:text-teal-300">
                                        {{ unreadCountLabel }}
                                    </span>
                                </div>
                                <div v-if="latestNotifications.length > 0" class="max-h-80 space-y-2 overflow-y-auto py-1">
                                    <div
                                        v-for="notification in latestNotifications"
                                        :key="notification.publicId"
                                        class="rounded-lg px-2 py-2 ring-1"
                                        :class="notificationSeverityClass(notification.severity)"
                                    >
                                        <div class="flex items-start justify-between gap-2">
                                            <component
                                                :is="notificationSeverityIcon(notification.severity)"
                                                aria-hidden="true"
                                                class="mt-0.5 h-4 w-4 shrink-0"
                                                :stroke-width="1.8"
                                            />
                                            <a
                                                v-if="isNativeNotificationHref(notification.deepLinkUrl)"
                                                :href="notificationHref(notification.deepLinkUrl)"
                                                class="min-w-0 flex-1 focus-visible:outline focus-visible:outline-amber-500"
                                                role="menuitem"
                                                @click="closeUserMenu"
                                            >
                                                <TruncatedText
                                                    :text="notification.title"
                                                    text-class="text-sm font-medium text-zinc-900 dark:text-zinc-100"
                                                />
                                                <TruncatedText
                                                    v-if="notification.body"
                                                    :text="notification.body"
                                                    :lines="2"
                                                    text-class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400"
                                                />
                                                <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                    {{ notificationTimestamp(notification.createdAt) }}
                                                </p>
                                            </a>
                                            <Link
                                                v-else
                                                :href="notificationHref(notification.deepLinkUrl)"
                                                class="min-w-0 flex-1 focus-visible:outline focus-visible:outline-amber-500"
                                                role="menuitem"
                                                @click="closeUserMenu"
                                            >
                                                <TruncatedText
                                                    :text="notification.title"
                                                    text-class="text-sm font-medium text-zinc-900 dark:text-zinc-100"
                                                />
                                                <TruncatedText
                                                    v-if="notification.body"
                                                    :text="notification.body"
                                                    :lines="2"
                                                    text-class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400"
                                                />
                                                <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                                    {{ notificationTimestamp(notification.createdAt) }}
                                                </p>
                                            </Link>
                                            <button
                                                v-if="!notification.read"
                                                type="button"
                                                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-white hover:text-teal-700 focus-visible:outline focus-visible:outline-amber-500 dark:text-zinc-400 dark:hover:bg-zinc-800 dark:hover:text-teal-300"
                                                :aria-label="t('notifications.mark_read')"
                                                @click="markNotificationRead(notification.publicId)"
                                            >
                                                <IconCircleCheck aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <p v-else class="px-2 py-3 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ t('notifications.dropdown.empty') }}
                                </p>
                                <Link
                                    href="/notifications"
                                    class="mt-2 flex h-8 items-center justify-center rounded-md text-sm font-medium text-teal-700 transition hover:bg-teal-50 focus-visible:outline focus-visible:outline-amber-500 dark:text-teal-300 dark:hover:bg-teal-950/40"
                                    role="menuitem"
                                    @click="closeUserMenu"
                                >
                                    {{ t('notifications.dropdown.open_all') }}
                                </Link>
                            </div>
                            <Link
                                v-if="canEnterAdmin"
                                href="/admin"
                                class="mt-2 flex h-10 items-center gap-3 rounded-lg px-2 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 hover:text-zinc-950 dark:text-zinc-200 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
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
