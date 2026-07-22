<script setup lang="ts">
import { computed, ref } from 'vue';
import type { Component } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { IconLogout } from '@tabler/icons-vue';

import MobileNavigation from '../Components/MobileNavigation.vue';
import FullscreenTransitionLoader from '../Components/FullscreenTransitionLoader.vue';
import ModalHost from '../Components/ModalHost.vue';
import Sidebar from '../Components/Sidebar.vue';
import TopBar from '../Components/TopBar.vue';
import ToastViewport from '../Components/ToastViewport.vue';
import type { AtlasPageProps } from '../Types/inertia';
import type { ShellSubnavigationItem } from '../Types/navigation';

withDefaults(
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
        titleIcon: undefined,
        mode: 'app',
        showLocaleSwitcher: true,
        uiLocale: undefined,
        subnavigation: () => [],
        subnavigationLabel: 'Section navigation',
    },
);

const page = usePage<AtlasPageProps>();
const mobileMenuOpen = ref(false);
const impersonation = computed(() => page.props.auth?.impersonation);
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
                        <p>
                            Impersonating
                            <span class="font-semibold">{{ impersonation.userName }}</span>
                            in
                            <span class="font-semibold">{{ impersonation.teamName }}</span>
                            for
                            <span class="font-semibold">{{ impersonation.reason }}</span>
                        </p>
                        <Link
                            href="/impersonation"
                            method="delete"
                            as="button"
                            class="inline-flex h-8 items-center gap-2 rounded-md bg-amber-900 px-3 text-sm font-medium text-white transition hover:bg-amber-950 dark:bg-amber-200 dark:text-amber-950 dark:hover:bg-amber-100"
                        >
                            <IconLogout aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                            Exit impersonation
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
                <main class="flex-1 px-4 py-5 sm:px-6 lg:px-8">
                    <slot />
                </main>
            </div>
        </div>
        <MobileNavigation :open="mobileMenuOpen" :mode="mode" :ui-locale="uiLocale" @close="mobileMenuOpen = false" />
        <FullscreenTransitionLoader />
        <ModalHost :ui-locale="uiLocale" />
        <ToastViewport :ui-locale="uiLocale" />
    </div>
</template>
