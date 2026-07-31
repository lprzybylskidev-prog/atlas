<script setup lang="ts">
import { IconX } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { Component } from 'vue';

import CardHeader from './CardHeader.vue';
import IconButton from './IconButton.vue';

const props = withDefaults(
    defineProps<{
        open: boolean;
        title: string;
        icon: Component;
        tone?: 'teal' | 'sky' | 'emerald' | 'amber' | 'rose' | 'zinc';
        size?: 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl';
        closeLabel?: string;
        labelledBy?: string;
    }>(),
    {
        tone: 'teal',
        size: 'xl',
        closeLabel: 'Close dialog',
        labelledBy: undefined,
    },
);

const emit = defineEmits<{
    'update:open': [open: boolean];
    close: [];
}>();

const dialog = ref<HTMLElement | null>(null);
const previousFocus = ref<HTMLElement | null>(null);
const titleId = `dialog-panel-${Math.random().toString(36).slice(2)}`;
const sizeClass = computed(() => {
    const classes = {
        md: 'max-w-md',
        lg: 'max-w-lg',
        xl: 'max-w-xl',
        '2xl': 'max-w-2xl',
        '3xl': 'max-w-3xl',
        '4xl': 'max-w-4xl',
    };

    return classes[props.size];
});

function close(): void {
    emit('update:open', false);
    emit('close');
}

function focusDialog(): void {
    window.setTimeout(() => dialog.value?.focus(), 0);
}

function focusPreviousElement(): void {
    previousFocus.value?.focus();
    previousFocus.value = null;
}

function handleKeydown(event: KeyboardEvent): void {
    if (!props.open) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }

    if (event.key !== 'Tab') {
        return;
    }

    const focusable = dialog.value?.querySelectorAll<HTMLElement>(
        'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
    );

    if (focusable === undefined || focusable.length === 0) {
        event.preventDefault();
        dialog.value?.focus();
        return;
    }

    const first = focusable[0];
    const last = focusable[focusable.length - 1];

    if (event.shiftKey && document.activeElement === first) {
        event.preventDefault();
        last.focus();
        return;
    }

    if (!event.shiftKey && document.activeElement === last) {
        event.preventDefault();
        first.focus();
    }
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            previousFocus.value = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            document.addEventListener('keydown', handleKeydown);
            focusDialog();
            return;
        }

        document.removeEventListener('keydown', handleKeydown);
        focusPreviousElement();
    },
    { flush: 'post' },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', handleKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="open" class="fixed inset-0 z-[80] flex items-center justify-center p-4" role="presentation">
            <button type="button" class="absolute inset-0 cursor-default bg-zinc-950/60" :aria-label="closeLabel" @click="close" />
            <section
                ref="dialog"
                role="dialog"
                aria-modal="true"
                :aria-labelledby="labelledBy ?? titleId"
                tabindex="-1"
                :class="[
                    'relative w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-xl outline-none dark:border-zinc-800 dark:bg-zinc-950',
                    sizeClass,
                ]"
            >
                <div
                    class="flex min-w-0 items-center justify-between gap-3 border-b border-zinc-200 bg-zinc-50 px-4 py-3 dark:border-zinc-800 dark:bg-zinc-900/60"
                >
                    <CardHeader :title="title" :title-id="labelledBy ?? titleId" :icon="icon" :tone="tone" icon-variant="secondary" />
                    <IconButton :label="closeLabel" :icon="IconX" class="h-8 w-8 shrink-0" @click="close" />
                </div>
                <div class="p-4 text-sm text-zinc-600 dark:text-zinc-300">
                    <slot />
                </div>
                <div v-if="$slots.actions" class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">
                    <div class="flex flex-wrap justify-end gap-2">
                        <slot name="actions" />
                    </div>
                </div>
            </section>
        </div>
    </Teleport>
</template>
