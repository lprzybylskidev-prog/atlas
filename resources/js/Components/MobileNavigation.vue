<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconGauge, IconKey, IconPackages, IconShieldCheck, IconUserPlus, IconUsersGroup, IconX } from '@tabler/icons-vue';
import { computed } from 'vue';

import AtlasLogo from './AtlasLogo.vue';
import { useTranslator } from '../Localization/translator';

const props = defineProps<{
    open: boolean;
    mode?: 'app' | 'admin';
    uiLocale?: string;
}>();

const emit = defineEmits<{
    close: [];
}>();

const { t } = useTranslator(props.uiLocale);

const groups = computed(() => {
    const workspace = {
        label: t('navigation.group.workspace'),
        items: [{ label: t('navigation.dashboard'), href: '/', icon: IconGauge }],
    };

    if (props.mode !== 'admin') {
        return [workspace];
    }

    return [
        workspace,
        {
            label: t('navigation.group.identity_access'),
            items: [
                { label: t('navigation.users'), href: '/admin/users', icon: IconUserPlus },
                { label: t('navigation.roles'), href: '/admin/authorization/roles', icon: IconShieldCheck },
                { label: t('navigation.packages'), href: '/admin/authorization/packages', icon: IconPackages },
                { label: t('navigation.permissions'), href: '/admin/authorization/permissions', icon: IconKey },
            ],
        },
        {
            label: t('navigation.group.organization'),
            items: [{ label: t('navigation.teams'), href: '/admin/teams', icon: IconUsersGroup }],
        },
    ];
});
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true">
        <button type="button" class="absolute inset-0 bg-zinc-950/45" :aria-label="t('actions.close_navigation')" @click="emit('close')" />
        <div class="absolute inset-y-0 left-0 flex w-[min(22rem,calc(100vw-2rem))] flex-col bg-white shadow-2xl dark:bg-zinc-950">
            <div class="flex h-16 items-center justify-between border-b border-zinc-200 px-4 dark:border-zinc-800">
                <AtlasLogo :ui-locale="uiLocale" />
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-900 dark:hover:text-zinc-50"
                    :aria-label="t('actions.close_navigation')"
                    @click="emit('close')"
                >
                    <IconX aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                </button>
            </div>
            <nav v-if="mode !== 'admin'" class="space-y-1 p-4" :aria-label="t('navigation.aria.mobile')">
                <Link
                    v-for="item in groups[0]?.items ?? []"
                    :key="item.label"
                    :href="item.href"
                    class="flex h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-900"
                    @click="emit('close')"
                >
                    <component :is="item.icon" aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                    {{ item.label }}
                </Link>
            </nav>

            <nav v-else class="space-y-4 p-4" :aria-label="t('navigation.aria.mobile')">
                <details v-for="group in groups" :key="group.label" open>
                    <summary class="list-none px-3 text-xs font-semibold uppercase text-zinc-400">{{ group.label }}</summary>
                    <div class="mt-2 space-y-1">
                        <Link
                            v-for="item in group.items"
                            :key="item.label"
                            :href="item.href"
                            class="flex h-12 items-center gap-3 rounded-lg px-3 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-900"
                            @click="emit('close')"
                        >
                            <component :is="item.icon" aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                            {{ item.label }}
                        </Link>
                    </div>
                </details>
            </nav>
        </div>
    </div>
</template>
