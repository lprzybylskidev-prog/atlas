<script setup lang="ts">
import { useTranslator } from '../Localization/translator';

const props = withDefaults(
    defineProps<{
        showText?: boolean;
        markClass?: string;
        uiLocale?: string;
    }>(),
    {
        showText: true,
        markClass: 'h-9 w-9',
        uiLocale: undefined,
    },
);

const logoPath = '/brand/atlas-logo.svg';
const { t } = useTranslator(props.uiLocale);
</script>

<template>
    <div class="flex min-w-0 items-center" :class="showText ? 'gap-3' : 'gap-0'">
        <div
            class="flex shrink-0 items-center justify-center rounded-lg bg-teal-700 text-white shadow-sm shadow-teal-950/15 dark:bg-teal-700"
            :class="markClass"
        >
            <img :src="logoPath" alt="" class="h-6 w-6" />
        </div>
        <div v-if="showText" class="min-w-0 overflow-hidden">
            <p class="truncate whitespace-nowrap text-sm font-semibold leading-5 text-zinc-950 dark:text-zinc-50">Atlas</p>
            <p class="truncate whitespace-nowrap text-xs leading-4 text-zinc-500 dark:text-zinc-400">{{ t('brand.subtitle') }}</p>
        </div>
    </div>
</template>
