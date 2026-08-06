<script setup lang="ts">
import { computed } from 'vue';
import AtlasLogo from '../Components/AtlasLogo.vue';
import FullscreenTransitionLoader from '../Components/FullscreenTransitionLoader.vue';
import IconButton from '../Components/IconButton.vue';
import SurfaceCard from '../Components/SurfaceCard.vue';
import TruncatedText from '../Components/TruncatedText.vue';
import { useLocaleSwitcher } from '../Composables/useLocaleSwitcher';
import { useTheme } from '../Composables/useTheme';
import { useTranslator } from '../Localization/translator';
import type { AtlasPageProps } from '../Types/inertia';
import { usePage } from '@inertiajs/vue3';
import { IconLanguage, IconMoon, IconSun } from '@tabler/icons-vue';

const props = withDefaults(
    defineProps<{
        title: string;
        subtitle: string;
        frameContent?: boolean;
        contentSize?: 'md' | 'xl' | '4xl';
    }>(),
    {
        frameContent: true,
        contentSize: 'md',
    },
);

const { isDark, toggleTheme } = useTheme();
const { switchLocale } = useLocaleSwitcher();
const page = usePage<AtlasPageProps>();
const { t } = useTranslator();
const contentWidthClass = computed(() => {
    const classes = {
        md: 'max-w-md',
        xl: 'max-w-xl',
        '4xl': 'max-w-4xl',
    };

    return classes[props.contentSize];
});
</script>

<template>
    <main class="flex min-h-screen bg-zinc-50 text-zinc-950 dark:bg-zinc-950 dark:text-zinc-50">
        <section
            class="hidden min-h-screen w-[42%] flex-col justify-between border-r border-zinc-200 bg-white p-8 lg:flex dark:border-zinc-800 dark:bg-zinc-900"
        >
            <AtlasLogo />
            <div class="max-w-md">
                <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Atlas</p>
                <h1 class="mt-3 text-3xl font-semibold leading-tight text-zinc-950 dark:text-zinc-50">{{ t('auth.shell.heading') }}</h1>
                <p class="mt-4 text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    {{ t('auth.shell.body') }}
                </p>
            </div>
            <div class="grid grid-cols-2 gap-3 text-xs text-zinc-500 dark:text-zinc-400">
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                    <span class="block text-[0.7rem] text-zinc-400 dark:text-zinc-500">{{ t('app.version') }}</span>
                    <TruncatedText :text="page.props.app.release.version" text-class="mt-1 font-medium text-zinc-800 dark:text-zinc-100" />
                </div>
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-800">
                    <span class="block text-[0.7rem] text-zinc-400 dark:text-zinc-500">{{ t('app.locale') }}</span>
                    <TruncatedText :text="page.props.locale" text-class="mt-1 font-medium uppercase text-zinc-800 dark:text-zinc-100" />
                </div>
            </div>
        </section>

        <section class="flex min-h-screen flex-1 flex-col">
            <div class="flex h-16 items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="lg:hidden">
                    <AtlasLogo />
                </div>
                <div class="ml-auto flex items-center gap-2">
                    <IconButton :label="t('actions.change_language')" :icon="IconLanguage" @click="switchLocale" />
                    <IconButton
                        :label="isDark ? t('actions.switch_light_theme') : t('actions.switch_dark_theme')"
                        :icon="isDark ? IconSun : IconMoon"
                        :active="isDark"
                        @click="toggleTheme"
                    />
                </div>
            </div>

            <div class="flex flex-1 items-center justify-center px-4 py-8 sm:px-6 lg:px-8">
                <div class="w-full" :class="contentWidthClass">
                    <div class="mb-7">
                        <div class="flex items-center gap-2">
                            <AtlasLogo :show-text="false" mark-class="h-7 w-7" />
                            <p class="text-sm font-medium text-teal-700 dark:text-teal-300">Atlas</p>
                        </div>
                        <h2 class="mt-2 text-2xl font-semibold text-zinc-950 dark:text-zinc-50">{{ title }}</h2>
                        <p class="mt-2 text-sm leading-6 text-zinc-600 dark:text-zinc-300">{{ subtitle }}</p>
                    </div>
                    <SurfaceCard v-if="frameContent" aria-label="Authentication form" body-class="sm:p-6">
                        <slot />
                    </SurfaceCard>
                    <slot v-else />
                </div>
            </div>
        </section>
    </main>
    <FullscreenTransitionLoader />
</template>
