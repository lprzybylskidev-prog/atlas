<script setup lang="ts">
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { IconArrowLeft, IconHome, IconLock, IconRefresh, IconServerOff } from '@tabler/icons-vue';

import ActionLink from '../Components/ActionLink.vue';
import AtlasLogo from '../Components/AtlasLogo.vue';
import FormButton from '../Components/Form/FormButton.vue';
import FullscreenTransitionLoader from '../Components/FullscreenTransitionLoader.vue';
import IconTile from '../Components/IconTile.vue';
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
const { locale, t } = useTranslator();

const fallbackTranslations: Record<string, Record<'pl' | 'en', string>> = {
    'errors.403.description': {
        pl: 'Nie masz skutecznego uprawnienia w aktywnym zespole albo aktywny zespół nie został wybrany. Poproś administratora o sprawdzenie dostępu.',
        en: 'You do not have an effective permission in the active team, or no active team is selected. Ask an administrator to review your access.',
    },
    'errors.403.title': {
        pl: 'Brak dostępu',
        en: 'Access denied',
    },
    'errors.404.description': {
        pl: 'Nie znaleźliśmy strony lub zasobu, którego szukasz.',
        en: 'We could not find the page or resource you are looking for.',
    },
    'errors.404.title': {
        pl: 'Nie znaleziono strony',
        en: 'Page not found',
    },
    'errors.419.description': {
        pl: 'Sesja wygasła. Odśwież stronę i spróbuj ponownie.',
        en: 'Your session has expired. Refresh the page and try again.',
    },
    'errors.419.title': {
        pl: 'Sesja wygasła',
        en: 'Session expired',
    },
    'errors.actions.back': {
        pl: 'Wróć',
        en: 'Go back',
    },
    'errors.actions.home': {
        pl: 'Przejdź do pulpitu',
        en: 'Go to dashboard',
    },
    'errors.default.description': {
        pl: 'Wystąpił problem po stronie systemu. Spróbuj ponownie za chwilę.',
        en: 'The system ran into a problem. Try again in a moment.',
    },
    'errors.default.title': {
        pl: 'Coś poszło nie tak',
        en: 'Something went wrong',
    },
};

function errorText(key: string): string {
    const translated = t(key);

    if (translated !== key) {
        return translated;
    }

    return fallbackTranslations[key]?.[locale.value] ?? key;
}

function goBack(): void {
    window.history.back();
}

const content = computed(() => {
    if (props.status === 403) {
        return {
            title: errorText('errors.403.title'),
            description: errorText('errors.403.description'),
            icon: IconLock,
        };
    }

    if (props.status === 404) {
        return {
            title: errorText('errors.404.title'),
            description: errorText('errors.404.description'),
            icon: IconHome,
        };
    }

    if (props.status === 419) {
        return {
            title: errorText('errors.419.title'),
            description: errorText('errors.419.description'),
            icon: IconRefresh,
        };
    }

    return {
        title: errorText('errors.default.title'),
        description: errorText('errors.default.description'),
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
                <IconTile class="mb-6" :icon="content.icon" tone="teal" />

                <p class="text-sm font-semibold tracking-normal text-teal-700 dark:text-teal-300">{{ status }}</p>
                <h1 class="mt-3 text-3xl font-semibold tracking-normal text-zinc-950 dark:text-zinc-50 sm:text-4xl">
                    {{ content.title }}
                </h1>
                <p class="mt-4 max-w-lg text-sm leading-6 text-zinc-600 dark:text-zinc-300">
                    {{ content.description }}
                </p>

                <div class="mt-8 flex flex-wrap gap-3">
                    <FormButton type="button" tone="neutral" :icon="IconArrowLeft" @click="goBack">
                        {{ errorText('errors.actions.back') }}
                    </FormButton>
                    <ActionLink href="/" tone="primary" :icon="IconHome">
                        {{ errorText('errors.actions.home') }}
                    </ActionLink>
                </div>
            </div>
        </section>
    </main>
    <FullscreenTransitionLoader />
</template>
