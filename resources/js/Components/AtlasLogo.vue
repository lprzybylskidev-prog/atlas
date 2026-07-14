<script setup lang="ts">
import { useTranslator } from '../Localization/translator';

const props = withDefaults(
    defineProps<{
        showText?: boolean;
        animateText?: boolean;
        markClass?: string;
        uiLocale?: string;
    }>(),
    {
        showText: true,
        animateText: false,
        markClass: 'h-9 w-9',
        uiLocale: undefined,
    },
);

const logoPath = '/brand/atlas-logo.svg';
const { t } = useTranslator(props.uiLocale);
</script>

<template>
    <div class="flex min-w-0 items-center gap-3">
        <div
            class="flex shrink-0 items-center justify-center rounded-lg bg-teal-700 text-white shadow-sm shadow-teal-950/15 dark:bg-teal-700"
            :class="markClass"
        >
            <img :src="logoPath" alt="" class="h-6 w-6" />
        </div>
        <div
            class="min-w-0 overflow-hidden"
            :class="
                animateText
                    ? showText
                        ? 'max-w-44 opacity-100 transition-[max-width,opacity,transform] duration-300 ease-in-out'
                        : 'max-w-0 -translate-x-1 opacity-0 transition-[max-width,opacity,transform] duration-300 ease-in-out'
                    : showText
                      ? ''
                      : 'hidden'
            "
            :aria-hidden="!showText"
        >
            <p class="truncate whitespace-nowrap text-sm font-semibold leading-5 text-zinc-950 dark:text-zinc-50">Atlas</p>
            <p class="truncate whitespace-nowrap text-xs leading-4 text-zinc-500 dark:text-zinc-400">{{ t('brand.subtitle') }}</p>
        </div>
    </div>
</template>
