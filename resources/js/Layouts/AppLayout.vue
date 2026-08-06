<script setup lang="ts">
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { IconActivityHeartbeat, IconLogout, IconWifi, IconWifiOff } from '@tabler/icons-vue';

import DialogPanel from '../Components/DialogPanel.vue';
import FormButton from '../Components/Form/FormButton.vue';
import MobileNavigation from '../Components/MobileNavigation.vue';
import FullscreenTransitionLoader from '../Components/FullscreenTransitionLoader.vue';
import ModalHost from '../Components/ModalHost.vue';
import Sidebar from '../Components/Sidebar.vue';
import TopBar from '../Components/TopBar.vue';
import ToastViewport from '../Components/ToastViewport.vue';
import { useTimeTrackingActivityTracker } from '../Composables/useTimeTrackingActivityTracker';
import { useTranslator } from '../Localization/translator';
import type { AtlasPageProps } from '../Types/inertia';
import type { ShellMode, ShellSubnavigationItem } from '../Types/navigation';

const props = withDefaults(
    defineProps<{
        title: string;
        titleIcon?: Component;
        mode?: ShellMode;
        showLocaleSwitcher?: boolean;
        uiLocale?: string;
        subnavigation?: ShellSubnavigationItem[];
        subnavigationLabel?: string;
    }>(),
    {
        titleIcon: undefined,
        mode: 'app',
        showLocaleSwitcher: true,
        uiLocale: undefined,
        subnavigation: () => [],
        subnavigationLabel: 'Section navigation',
    },
);

const page = usePage<AtlasPageProps>();
const { t } = useTranslator(props.uiLocale);
const mobileMenuOpen = ref(false);
const impersonation = computed(() => page.props.auth?.impersonation);
const activityConfig = computed(() => page.props.timeTracking.activity);
const activity = useTimeTrackingActivityTracker(activityConfig);
const reconnectTitle = computed(() => {
    if (activity.reconnectStatus === 'ended') {
        return t('pages.time_tracking.offline.ended_title');
    }

    if (activity.reconnectStatus === 'failed') {
        return t('pages.time_tracking.offline.failed_title');
    }

    return t('pages.time_tracking.offline.active_title');
});
const reconnectBody = computed(() => {
    if (activity.reconnectStatus === 'ended') {
        return t('pages.time_tracking.offline.ended_body');
    }

    if (activity.reconnectStatus === 'failed') {
        return t('pages.time_tracking.offline.failed_body');
    }

    return t('pages.time_tracking.offline.active_body');
});
const impersonationBannerText = computed(() =>
    t('pages.admin.impersonation.banner.text', {
        user: impersonation.value?.userName ?? t('pages.admin.impersonation.banner.unknown_user'),
        team: impersonation.value?.teamName ?? t('pages.admin.impersonation.banner.unknown_team'),
        reason: impersonation.value?.reason ?? t('pages.admin.impersonation.banner.no_reason'),
    }),
);
</script>

<template>
    <div class="min-h-screen bg-zinc-50 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
        <div class="flex min-h-screen">
            <Sidebar :current-path="page.url" :mode="mode" :ui-locale="uiLocale" />
            <div class="flex min-w-0 flex-1 flex-col">
                <div
                    v-if="impersonation?.active"
                    class="border-b border-amber-300 bg-amber-100 px-4 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100 sm:px-6 lg:px-8"
                >
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <p>{{ impersonationBannerText }}</p>
                        <Link
                            href="/impersonation"
                            method="delete"
                            as="button"
                            class="inline-flex h-8 items-center gap-2 rounded-md bg-amber-900 px-3 text-sm font-medium text-white transition hover:bg-amber-950 dark:bg-amber-200 dark:text-amber-950 dark:hover:bg-amber-100"
                        >
                            <IconLogout aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                            {{ t('pages.admin.impersonation.banner.exit') }}
                        </Link>
                    </div>
                </div>
                <TopBar
                    :title="title"
                    :title-icon="titleIcon"
                    :mode="mode"
                    :show-locale-switcher="showLocaleSwitcher"
                    :ui-locale="uiLocale"
                    :subnavigation="subnavigation"
                    :subnavigation-label="subnavigationLabel"
                    @open-mobile-menu="mobileMenuOpen = true"
                />
                <div
                    v-if="activity.offline"
                    class="border-b border-amber-300 bg-amber-100 px-4 py-2 text-sm text-amber-950 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100 sm:px-6 lg:px-8"
                >
                    <div class="flex items-center gap-2">
                        <IconWifiOff aria-hidden="true" class="h-4 w-4 shrink-0" :stroke-width="1.8" />
                        <p>{{ t('pages.time_tracking.offline.banner') }}</p>
                    </div>
                </div>
                <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8">
                    <slot />
                </main>
            </div>
        </div>
        <MobileNavigation :open="mobileMenuOpen" :mode="mode" :ui-locale="uiLocale" @close="mobileMenuOpen = false" />
        <FullscreenTransitionLoader />
        <ModalHost :ui-locale="uiLocale" />
        <DialogPanel
            v-model:open="activity.warningOpen"
            :title="t('pages.time_tracking.activity_warning.title')"
            :icon="IconActivityHeartbeat"
            tone="amber"
            size="md"
            :close-label="t('modal.close')"
        >
            <p class="leading-6">
                {{
                    t('pages.time_tracking.activity_warning.body', {
                        seconds: activity.countdownSeconds,
                    })
                }}
            </p>
        </DialogPanel>
        <DialogPanel
            v-model:open="activity.reconnectOpen"
            :title="reconnectTitle"
            :icon="activity.reconnectStatus === 'active' ? IconWifi : IconWifiOff"
            :tone="activity.reconnectStatus === 'active' ? 'emerald' : 'amber'"
            size="md"
            :close-label="t('pages.time_tracking.offline.dismiss')"
        >
            <p class="leading-6">
                {{ reconnectBody }}
            </p>
            <template #actions>
                <FormButton
                    type="button"
                    tone="primary"
                    :icon="activity.reconnectStatus === 'active' ? IconWifi : IconWifiOff"
                    @click="activity.reconnectOpen = false"
                >
                    {{ t('pages.time_tracking.offline.dismiss') }}
                </FormButton>
            </template>
        </DialogPanel>
        <ToastViewport :ui-locale="uiLocale" />
    </div>
</template>
