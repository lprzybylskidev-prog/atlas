<script setup lang="ts">
import { IconAlertTriangle, IconCircleCheck, IconInfoCircle, IconX, IconCircleX } from '@tabler/icons-vue';
import { computed, onMounted, watch } from 'vue';

import { useToast, type ToastMessage } from '../Composables/useToast';
import { useTranslator } from '../Localization/translator';
import type { AtlasPageProps } from '../Types/inertia';
import { usePage } from '@inertiajs/vue3';

const props = defineProps<{
    uiLocale?: string;
}>();

const page = usePage<AtlasPageProps>();
const { messages, dismiss, push } = useToast();
const { t } = useTranslator(props.uiLocale);

const iconByType = {
    success: IconCircleCheck,
    info: IconInfoCircle,
    warning: IconAlertTriangle,
    error: IconCircleX,
};

const classByType = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100',
    info: 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900 dark:bg-sky-950 dark:text-sky-100',
    warning: 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-100',
    error: 'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-900 dark:bg-rose-950 dark:text-rose-100',
};

const progressByType = {
    success: 'bg-emerald-500',
    info: 'bg-sky-500',
    warning: 'bg-amber-500',
    error: 'bg-rose-500',
};

const flashSignature = computed(() => JSON.stringify(page.props.flash));

function messageText(message: ToastMessage): string {
    if (message.key) {
        return t(message.key);
    }

    return message.message ?? '';
}

function descriptionText(message: ToastMessage): string | null {
    if (message.descriptionKey) {
        return t(message.descriptionKey);
    }

    return message.description ?? null;
}

function pushFlashMessages(): void {
    const flash = page.props.flash;

    flash.messages?.forEach((message) => {
        push({
            type: message.type,
            key: message.key as ToastMessage['key'],
            message: message.message,
            description: message.description,
            descriptionKey: message.descriptionKey as ToastMessage['descriptionKey'],
            timeoutMs: message.timeoutMs,
            critical: message.critical,
        });
    });

    if (flash.success) {
        push({ type: 'success', message: flash.success });
    }

    if (flash.error) {
        push({ type: 'error', message: flash.error, timeoutMs: null });
    }
}

onMounted(pushFlashMessages);
watch(flashSignature, pushFlashMessages);
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed right-4 bottom-4 z-[90] flex w-[min(24rem,calc(100vw-2rem))] flex-col gap-3"
            role="status"
            aria-live="polite"
            aria-relevant="additions removals"
        >
            <article
                v-for="message in messages"
                :key="message.id"
                class="relative overflow-hidden rounded-lg border p-4 shadow-lg"
                :class="classByType[message.type]"
            >
                <div class="flex items-start gap-3">
                    <component :is="iconByType[message.type]" aria-hidden="true" class="mt-0.5 h-5 w-5 shrink-0" :stroke-width="1.8" />
                    <div class="min-w-0 flex-1">
                        <p class="text-sm font-semibold">{{ messageText(message) }}</p>
                        <p v-if="descriptionText(message)" class="mt-1 text-sm opacity-80">{{ descriptionText(message) }}</p>
                    </div>
                    <button
                        type="button"
                        class="rounded-md p-1 opacity-70 transition hover:bg-white/40 hover:opacity-100"
                        :aria-label="t('toast.close')"
                        @click="dismiss(message.id)"
                    >
                        <IconX aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    </button>
                </div>
                <span
                    v-if="message.timeoutMs !== null"
                    class="absolute bottom-0 left-0 h-1 animate-[toast-progress_linear_forwards]"
                    :class="progressByType[message.type]"
                    :style="{ animationDuration: `${message.timeoutMs}ms` }"
                />
            </article>
        </div>
    </Teleport>
</template>
