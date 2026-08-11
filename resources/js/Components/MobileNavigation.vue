<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { IconChevronDown, IconX } from '@tabler/icons-vue';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

import AtlasLogo from './AtlasLogo.vue';
import ShellSubnavigation from './ShellSubnavigation.vue';
import { useTranslator } from '../Localization/translator';
import type { NavigationGroup, ShellSubnavigationItem } from '../Types/navigation';

const props = defineProps<{
    open: boolean;
    groups: NavigationGroup[];
    subnavigation: ShellSubnavigationItem[];
    subnavigationLabel: string;
    uiLocale?: string;
}>();

const emit = defineEmits<{ close: [] }>();
const { t } = useTranslator(props.uiLocale);
const drawer = ref<HTMLElement | null>(null);
const closeButton = ref<HTMLButtonElement | null>(null);
let previouslyFocused: HTMLElement | null = null;
const focusableSelector =
    'a[href],button:not([disabled]),summary,input:not([disabled]),select:not([disabled]),[tabindex]:not([tabindex="-1"])';

function close(): void {
    emit('close');
}

function handleKeydown(event: KeyboardEvent): void {
    if (!props.open) return;
    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }
    if (event.key !== 'Tab' || !drawer.value) return;

    const focusable = Array.from(drawer.value.querySelectorAll<HTMLElement>(focusableSelector)).filter(
        (element) => element.offsetParent !== null,
    );
    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (!first || !last) {
        event.preventDefault();
    } else if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(
    () => props.open,
    async (open) => {
        if (open) {
            previouslyFocused = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            document.body.style.overflow = 'hidden';
            document.addEventListener('keydown', handleKeydown);
            await nextTick();
            closeButton.value?.focus();
            return;
        }

        document.body.style.removeProperty('overflow');
        document.removeEventListener('keydown', handleKeydown);
        previouslyFocused?.focus();
        previouslyFocused = null;
    },
);

onBeforeUnmount(() => {
    document.body.style.removeProperty('overflow');
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <div v-if="open" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-labelledby="mobile-navigation-title">
        <button type="button" class="absolute inset-0 bg-zinc-950/45" :aria-label="t('actions.close_navigation')" @click="close" />
        <div
            ref="drawer"
            data-testid="mobile-navigation-drawer"
            class="absolute inset-y-0 left-0 flex w-[min(22rem,calc(100vw-2rem))] flex-col overflow-hidden bg-white shadow-2xl dark:bg-zinc-950"
        >
            <div class="flex h-16 shrink-0 items-center justify-between border-b border-zinc-200 px-4 dark:border-zinc-800">
                <h2 id="mobile-navigation-title" class="sr-only">{{ t('navigation.aria.mobile') }}</h2>
                <AtlasLogo :ui-locale="uiLocale" />
                <button
                    ref="closeButton"
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg text-zinc-500 hover:bg-zinc-100 hover:text-zinc-900 focus-visible:outline focus-visible:outline-amber-500 dark:text-zinc-400 dark:hover:bg-zinc-900 dark:hover:text-zinc-50"
                    :aria-label="t('actions.close_navigation')"
                    @click="close"
                >
                    <IconX aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto overscroll-contain px-4 py-4">
                <ShellSubnavigation
                    v-if="subnavigation.length > 0"
                    data-testid="mobile-subnavigation"
                    class="mb-5 border-b border-zinc-200 pb-4 dark:border-zinc-800"
                    :items="subnavigation"
                    :label="subnavigationLabel"
                    variant="stacked"
                    @navigate="close"
                />

                <nav class="space-y-3" :aria-label="t('navigation.aria.mobile')">
                    <details v-for="group in groups" :key="group.key" :open="group.items.some((entry) => entry.active)">
                        <summary
                            class="flex min-h-10 cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-3 text-xs font-semibold uppercase text-zinc-500 hover:bg-zinc-100 focus-visible:outline focus-visible:outline-amber-500 dark:text-zinc-300 dark:hover:bg-zinc-900"
                        >
                            <span class="flex min-w-0 items-center gap-2">
                                <component :is="group.icon" aria-hidden="true" class="h-5 w-5 shrink-0" :stroke-width="1.8" />
                                <span class="truncate">{{ group.label }}</span>
                            </span>
                            <IconChevronDown
                                aria-hidden="true"
                                class="h-4 w-4 shrink-0 transition group-open:rotate-180"
                                :stroke-width="1.8"
                            />
                        </summary>
                        <div class="mt-1 space-y-1">
                            <component
                                :is="entry.external ? 'a' : Link"
                                v-for="entry in group.items"
                                :key="entry.key"
                                :href="entry.href"
                                :target="entry.external ? '_blank' : undefined"
                                :rel="entry.external ? 'noopener noreferrer' : undefined"
                                class="flex min-h-11 items-center gap-3 rounded-lg px-3 text-sm font-medium transition focus-visible:outline focus-visible:outline-amber-500"
                                :class="
                                    entry.active
                                        ? 'bg-teal-50 text-teal-900 ring-1 ring-teal-100 dark:bg-teal-950 dark:text-teal-100 dark:ring-teal-900'
                                        : 'text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-900'
                                "
                                :aria-current="entry.active ? 'page' : undefined"
                                @click="close"
                            >
                                <component :is="entry.icon" aria-hidden="true" class="h-5 w-5 shrink-0" :stroke-width="1.8" />
                                {{ entry.label }}
                            </component>
                        </div>
                    </details>
                </nav>
            </div>
        </div>
    </div>
</template>
