<script setup lang="ts">
import { IconAlertTriangle, IconLoader2, IconX } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import { useModal } from '../Composables/useModal';
import { useTranslator } from '../Localization/translator';
import FormInput from './Form/FormInput.vue';

const props = defineProps<{
    uiLocale?: string;
}>();

const { activeModal, resolve } = useModal();
const { t } = useTranslator(props.uiLocale);
const confirmButton = ref<HTMLButtonElement | null>(null);
const dialog = ref<HTMLElement | null>(null);
const previousFocus = ref<HTMLElement | null>(null);
const typedValue = ref('');

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

    if (activeModal.value?.typedConfirmation && typedValue.value !== activeModal.value.typedConfirmation) {
        return;
    }

    resolve(true);
}

function onKeydown(event: KeyboardEvent): void {
    if (activeModal.value === null) {
        return;
    }

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
        return;
    }

    if (event.key === 'Tab') {
        const focusable = dialog.value?.querySelectorAll<HTMLElement>(
            'button:not([disabled]), [href], input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [tabindex]:not([tabindex="-1"])',
        );

        if (focusable === undefined || focusable.length === 0) {
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
}

watch(
    activeModal,
    (modal) => {
        if (modal !== null) {
            previousFocus.value = document.activeElement instanceof HTMLElement ? document.activeElement : null;
            typedValue.value = '';
            window.setTimeout(() => {
                if (modal.variant === 'confirm' && !modal.typedConfirmation) {
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
                        <dl
                            v-if="activeModal.subject || activeModal.affectedCount !== undefined || activeModal.irreversible"
                            class="mt-4 space-y-2 rounded-lg border border-zinc-200 bg-zinc-50 p-3 text-sm dark:border-zinc-800 dark:bg-zinc-900"
                        >
                            <div v-if="activeModal.subject" class="flex gap-2">
                                <dt class="shrink-0 font-medium text-zinc-500 dark:text-zinc-400">{{ t('modal.subject') }}</dt>
                                <dd class="min-w-0 break-words text-zinc-800 dark:text-zinc-100">{{ activeModal.subject }}</dd>
                            </div>
                            <div v-if="activeModal.affectedCount !== undefined" class="flex gap-2">
                                <dt class="shrink-0 font-medium text-zinc-500 dark:text-zinc-400">{{ t('modal.affected_count') }}</dt>
                                <dd class="text-zinc-800 dark:text-zinc-100">{{ activeModal.affectedCount }}</dd>
                            </div>
                            <div v-if="activeModal.irreversible" class="text-rose-700 dark:text-rose-300">
                                {{ t('modal.irreversible') }}
                            </div>
                        </dl>
                        <FormInput
                            v-if="activeModal.typedConfirmation"
                            v-model="typedValue"
                            class="mt-4"
                            :label="t('modal.typed_confirmation_label', { value: activeModal.typedConfirmation })"
                            :aria-label="t('modal.typed_confirmation_label', { value: activeModal.typedConfirmation })"
                            autocomplete="off"
                            monospace
                        />
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
                        class="h-10 rounded-lg px-4 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-50"
                        :class="toneClass"
                        :disabled="Boolean(activeModal.typedConfirmation && typedValue !== activeModal.typedConfirmation)"
                        @click="confirm"
                    >
                        {{ t(activeModal.confirmKey ?? 'modal.cancel') }}
                    </button>
                </div>
            </section>
        </div>
    </Teleport>
</template>
