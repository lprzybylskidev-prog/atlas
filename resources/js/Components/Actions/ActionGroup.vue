<script setup lang="ts">
import { router } from '@inertiajs/vue3';

import Tooltip from '../Tooltip.vue';
import { useModal } from '../../Composables/useModal';
import {
    actionAvailable,
    actionDisabled,
    actionDisabledReason,
    actionHref,
    actionIcon,
    actionSemantic,
    actionTone,
    actionToneClass,
} from '../../Services/actionCatalog';
import type { ActionConfirmation, AtlasAction } from '../../Types/actions';

const props = withDefaults(
    defineProps<{
        actions: AtlasAction<undefined>[];
        placement?: 'primary' | 'secondary' | 'overflow' | 'detail' | 'edit';
    }>(),
    {
        placement: 'detail',
    },
);

const { confirm } = useModal();

function visible(action: AtlasAction<undefined>): boolean {
    const placements = action.placement === undefined ? [] : Array.isArray(action.placement) ? action.placement : [action.placement];

    return actionAvailable(action, undefined) && (placements.length === 0 || placements.includes(props.placement));
}

function tooltip(action: AtlasAction<undefined>): string {
    const reason = actionDisabledReason(action, undefined);

    return actionDisabled(action, undefined) && reason ? `${action.label}: ${reason}` : action.label;
}

function confirmation(action: AtlasAction<undefined>): ActionConfirmation<undefined> | null {
    if (action.confirm === undefined) {
        return null;
    }

    if (typeof action.confirm === 'object') {
        return action.confirm;
    }

    const subject = typeof action.confirm === 'function' ? action.confirm(undefined) : action.confirm;
    const semantic = actionSemantic(action);
    const key =
        semantic !== undefined && ['acknowledge', 'archive', 'deactivate', 'delete', 'rescan', 'retry', 'revoke'].includes(semantic)
            ? `modal.action.${semantic}`
            : null;

    return {
        titleKey: key === null ? 'datatable.action.confirm.title' : `${key}.title`,
        descriptionKey: key === null ? 'datatable.action.confirm.description' : `${key}.description`,
        confirmKey: key === null ? 'datatable.action.confirm.confirm' : `${key}.confirm`,
        cancelKey: 'datatable.action.confirm.cancel',
        subject,
        tone: actionTone(action) === 'danger' ? 'danger' : 'warning',
    };
}

async function run(action: AtlasAction<undefined>): Promise<void> {
    if (actionDisabled(action, undefined)) {
        return;
    }

    const request = confirmation(action);

    if (
        request !== null &&
        !(await confirm({
            titleKey: request.titleKey,
            descriptionKey: request.descriptionKey,
            confirmKey: request.confirmKey,
            cancelKey: request.cancelKey,
            tone: request.tone ?? 'warning',
            subject: typeof request.subject === 'function' ? request.subject(undefined) : request.subject,
            irreversible: request.irreversible,
            typedConfirmation:
                typeof request.typedConfirmation === 'function' ? request.typedConfirmation(undefined) : request.typedConfirmation,
        }))
    ) {
        return;
    }

    if (action.onAction !== undefined) {
        await action.onAction(undefined);
        return;
    }

    const href = actionHref(action, undefined);

    if (href !== undefined) {
        router.visit(href, { method: action.method ?? 'get', preserveScroll: true });
    }
}
</script>

<template>
    <div class="flex flex-wrap gap-2">
        <Tooltip v-for="action in actions.filter(visible)" :key="action.key" :text="tooltip(action)" placement="top">
            <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-lg border px-3 text-sm font-medium transition disabled:cursor-not-allowed disabled:opacity-40"
                :class="actionToneClass(action)"
                :aria-label="tooltip(action)"
                :disabled="actionDisabled(action, undefined)"
                @click="run(action)"
            >
                <component :is="actionIcon(action)" aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                {{ action.label }}
            </button>
        </Tooltip>
    </div>
</template>
