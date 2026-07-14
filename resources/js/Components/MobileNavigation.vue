<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconGauge, IconX } from '@tabler/icons-vue';

import AtlasLogo from './AtlasLogo.vue';
import { useTranslator } from '../Localization/translator';

const props = defineProps<{
    open: boolean;
    uiLocale?: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useTranslator(props.uiLocale);

const items = [
    { label: t('navigation.dashboard'), href: '/', icon: IconGauge },
];
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <button type="button" class="absolute inset-0 bg-zinc-950/45" :aria-label="t('actions.close_navigation')" @click="emit('close')" />
        <div class="absolute inset-y-0 left-0 flex w-[min(22rem,calc(100vw-2rem))] flex-col bg-white shadow-2xl dark:bg-zinc-950">
            <div class="flex h-16 items-center justify-between border-b border-zinc-200 px-4 dark:border-zinc-800">
                <AtlasLogo />
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-900 dark:hover:text-zinc-50"
                    :aria-label="t('actions.close_navigation')"
                    @click="emit('close')"
                >
                    <IconX aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                </button>
            </div>
            <nav class="space-y-1 p-4" :aria-label="t('navigation.aria.mobile')">
                <Link
                    v-for="item in items"
                    :key="item.label"
                    :href="item.href"
                    class="flex h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-900"
                    @click="emit('close')"
                >
                    <component :is="item.icon" aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                    {{ item.label }}
                </Link>
            </nav>
        </div>
    </div>
</template>
