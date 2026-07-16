<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { IconArrowLeft, IconHome, IconLock, IconRefresh, IconServerOff } from '@tabler/icons-vue';

import AtlasLogo from '../Components/AtlasLogo.vue';
import FullscreenTransitionLoader from '../Components/FullscreenTransitionLoader.vue';
import IconButton from '../Components/IconButton.vue';
import { useLocaleSwitcher } from '../Composables/useLocaleSwitcher';
import { useTheme } from '../Composables/useTheme';
import { useTranslator } from '../Localization/translator';
import { IconLanguage, IconMoon, IconSun } from '@tabler/icons-vue';

const props = defineProps<{
    status: number;
}>();

const { isDark, toggleTheme } = useTheme();
const { switchLocale } = useLocaleSwitcher();
const { t } = useTranslator();

function goBack(): void {
    window.history.back();
}

const content = computed(() => {
    if (props.status === 403) {
        return {
            title: t('errors.403.title'),
            description: t('errors.403.description'),
            icon: IconLock,
        };
    }

    if (props.status === 404) {
        return {
            title: t('errors.404.title'),
            description: t('errors.404.description'),
            icon: IconHome,
        };
    }

    if (props.status === 419) {
        return {
            title: t('errors.419.title'),
            description: t('errors.419.description'),
            icon: IconRefresh,
        };
    }

    return {
        title: t('errors.default.title'),
        description: t('errors.default.description'),
        icon: IconServerOff,
    };
});
</script>

<template>
    <Head :title="`${status} - ${content.title}`" />

    <main class="flex min-h-screen flex-col bg-zinc-50 px-4 py-5 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50 sm:px-6 lg:px-8">
        <header class="flex items-center justify-between">
            <AtlasLogo />
            <div class="flex items-center gap-2">
                <IconButton :label="t('actions.change_language')" :icon="IconLanguage" @click="switchLocale" />
                <IconButton
                    :label="isDark ? t('actions.switch_light_theme') : t('actions.switch_dark_theme')"
                    :icon="isDark ? IconSun : IconMoon"
                    :active="isDark"
                    @click="toggleTheme"
                />
            </div>
        </header>

        <section class="flex flex-1 items-center justify-center py-12">
            <div class="w-full max-w-xl">
                <div
                    class="mb-6 flex h-14 w-14 items-center justify-center rounded-lg border border-teal-200 bg-teal-50 text-teal-700 dark:border-teal-900/70 dark:bg-teal-950/40 dark:text-teal-200"
                >
                    <component :is="content.icon" class="h-7 w-7" aria-hidden="true" />
                </div>

                <p class="text-sm font-semibold tracking-normal text-teal-700 dark:text-teal-300">{{ status }}</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-normal text-zinc-950 dark:text-zinc-50 sm:text-4xl">
                    {{ content.title }}
                </h1>
                <p class="mt-4 max-w-lg text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    {{ content.description }}
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border border-zinc-300 px-4 py-2 text-sm font-medium text-zinc-800 transition hover:bg-zinc-100 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:border-zinc-700 dark:text-zinc-100 dark:hover:bg-zinc-900 dark:focus:ring-offset-zinc-950"
                        @click="goBack"
                    >
                        <IconArrowLeft class="h-4 w-4" aria-hidden="true" />
                        {{ t('errors.actions.back') }}
                    </button>
                    <Link
                        href="/"
                        class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2 text-sm font-medium text-white transition hover:bg-teal-800 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 dark:bg-teal-600 dark:hover:bg-teal-500 dark:focus:ring-offset-zinc-950"
                    >
                        <IconHome class="h-4 w-4" aria-hidden="true" />
                        {{ t('errors.actions.home') }}
                    </Link>
                </div>
            </div>
        </section>
    </main>
    <FullscreenTransitionLoader />
</template>
