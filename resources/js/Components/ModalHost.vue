<script setup lang="ts">
import { IconAlertTriangle, IconLoader2, IconX } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { useModal } from '../Composables/useModal';
import { useTranslator } from '../Localization/translator';

const props = defineProps<{
    uiLocale?: string;
}>();

const { activeModal, resolve } = useModal();
const { t } = useTranslator(props.uiLocale);
const confirmButton = ref<HTMLButtonElement | null>(null);
const dialog = ref<HTMLElement | null>(null);
const previousFocus = ref<HTMLElement | null>(null);

const toneClass = computed(() => {
    if (activeModal.value?.tone === 'danger') {
        return 'bg-rose-700 text-white hover:bg-rose-800 dark:bg-rose-600 dark:hover:bg-rose-500';
    }

    if (activeModal.value?.tone === 'warning') {
        return 'bg-amber-600 text-white hover:bg-amber-700 dark:bg-amber-500 dark:hover:bg-amber-400';
    }

    return 'bg-teal-700 text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-500';
});

function close(): void {
    if (activeModal.value?.variant === 'busy') {
        return;
    }

    resolve(false);
}

function confirm(): void {
    if (activeModal.value?.variant === 'busy') {
        return;
    }

    resolve(true);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && activeModal.value !== null) {
        event.preventDefault();
        close();
    }
}

watch(
    activeModal,
    (modal) => {
        if (modal !== null) {
            previousFocus.value = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            window.setTimeout(() => {
                if (modal.variant === 'confirm') {
                    confirmButton.value?.focus();
                    return;
                }

                dialog.value?.focus();
            }, 0);
            return;
        }

        previousFocus.value?.focus();
        previousFocus.value = null;
    },
    { flush: 'post' },
);

onMounted(() => {
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <Teleport to="body">
        <div v-if="activeModal" class="fixed inset-0 z-[80] flex items-center justify-center p-4" role="presentation">
            <button
                v-if="activeModal.variant === 'confirm'"
                class="absolute inset-0 cursor-default bg-zinc-950/60"
                type="button"
                :aria-label="t('modal.cancel')"
                @click="close"
            />
            <div v-else class="absolute inset-0 bg-zinc-950/60" />
            <section
                ref="dialog"
                role="dialog"
                aria-modal="true"
                tabindex="-1"
                :aria-labelledby="`${activeModal.id}-title`"
                :aria-describedby="`${activeModal.id}-description`"
                class="relative w-full max-w-lg rounded-lg border border-zinc-200 bg-white p-5 shadow-xl dark:border-zinc-800 dark:bg-zinc-950"
            >
                <div class="flex items-start gap-3">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                        :class="
                            activeModal.variant === 'busy'
                                ? 'bg-teal-50 text-teal-700 dark:bg-teal-950 dark:text-teal-300'
                                : 'bg-amber-50 text-amber-700 dark:bg-amber-950 dark:text-amber-300'
                        "
                    >
                        <IconLoader2
                            v-if="activeModal.variant === 'busy'"
                            aria-hidden="true"
                            class="h-5 w-5 animate-spin"
                            :stroke-width="1.8"
                        />
                        <IconAlertTriangle v-else aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h2 :id="`${activeModal.id}-title`" class="text-base font-semibold text-zinc-950 dark:text-zinc-50">
                            {{ t(activeModal.titleKey) }}
                        </h2>
                        <p :id="`${activeModal.id}-description`" class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">
                            {{ t(activeModal.descriptionKey) }}
                        </p>
                    </div>
                    <button
                        v-if="activeModal.variant === 'confirm'"
                        type="button"
                        class="rounded-md p-1 text-zinc-400 transition hover:bg-zinc-100 hover:text-zinc-700 dark:hover:bg-zinc-900 dark:hover:text-zinc-200"
                        :aria-label="t('modal.cancel')"
                        @click="close"
                    >
                        <IconX aria-hidden="true" class="h-5 w-5" :stroke-width="1.8" />
                    </button>
                </div>
                <div v-if="activeModal.variant === 'confirm'" class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        class="h-10 rounded-lg border border-zinc-300 px-4 text-sm font-medium text-zinc-700 transition hover:bg-zinc-100 dark:border-zinc-700 dark:text-zinc-200 dark:hover:bg-zinc-900"
                        @click="close"
                    >
                        {{ t(activeModal.cancelKey ?? 'modal.cancel') }}
                    </button>
                    <button
                        ref="confirmButton"
                        type="button"
                        class="h-10 rounded-lg px-4 text-sm font-medium transition"
                        :class="toneClass"
                        @click="confirm"
                    >
                        {{ t(activeModal.confirmKey ?? 'modal.cancel') }}
                    </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
