<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { IconDeviceFloppy, IconX } from '@tabler/icons-vue';
import type { Component } from 'vue';

import { useModal } from '../Composables/useModal';
import { useTranslator } from '../Localization/translator';
import FormButton from './Form/FormButton.vue';

const props = withDefaults(
    defineProps<{
        cancelHref?: string;
        cancelLabel?: string;
        dirty?: boolean;
        processing?: boolean;
        processingLabel?: string;
        scopeLabel?: string;
        submitIcon?: Component;
        submitLabel?: string;
        submitTone?: 'primary' | 'neutral' | 'danger';
    }>(),
    {
        cancelHref: undefined,
        cancelLabel: undefined,
        dirty: false,
        processing: false,
        processingLabel: undefined,
        scopeLabel: undefined,
        submitIcon: () => IconDeviceFloppy,
        submitLabel: undefined,
        submitTone: 'primary',
    },
);

const emit = defineEmits<{ cancel: [] }>();
const { t } = useTranslator();
const { confirm } = useModal();

async function cancel(): Promise<void> {
    if (
        props.dirty &&
        !(await confirm({
            titleKey: 'form.unsaved.title',
            descriptionKey: 'form.unsaved.description',
            confirmKey: 'form.unsaved.discard',
            cancelKey: 'form.unsaved.continue',
            tone: 'warning',
        }))
    ) {
        return;
    }

    emit('cancel');

    if (props.cancelHref !== undefined) {
        router.visit(props.cancelHref);
    }
}
</script>

<template>
    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
        <p v-if="scopeLabel" class="text-xs text-zinc-500 dark:text-zinc-400">
            {{ t('form.save_scope', { scope: scopeLabel }) }}
        </p>
        <div v-if="submitLabel" class="flex flex-wrap items-center justify-end gap-2" :class="{ 'sm:ml-auto': scopeLabel }">
            <FormButton type="button" tone="neutral" :icon="IconX" :disabled="processing" @click="cancel">
                {{ cancelLabel ?? t('actions.cancel') }}
            </FormButton>
            <FormButton type="submit" :tone="submitTone" :icon="submitIcon" :loading="processing">
                {{ processing ? (processingLabel ?? submitLabel) : submitLabel }}
            </FormButton>
        </div>
        <div v-else class="flex flex-wrap items-center gap-2">
            <slot />
        </div>
    </div>
</template>
