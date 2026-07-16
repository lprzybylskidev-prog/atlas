<script setup lang="ts">
import { IconCheck, IconMinus } from '@tabler/icons-vue';
import { computed } from 'vue';

const model = defineModel<boolean | string[]>({ required: true });

const props = withDefaults(
    defineProps<{
        label?: string;
        value?: string;
        ariaLabel?: string;
        disabled?: boolean;
        indeterminate?: boolean;
        align?: 'center' | 'start';
    }>(),
    {
        label: undefined,
        value: undefined,
        ariaLabel: undefined,
        disabled: false,
        indeterminate: false,
        align: 'center',
    },
);

const checked = computed(() => {
    if (Array.isArray(model.value)) {
        return props.value !== undefined && model.value.includes(props.value);
    }

    return model.value;
});

function toggle(): void {
    if (props.disabled) {
        return;
    }

    if (Array.isArray(model.value)) {
        if (props.value === undefined) {
            return;
        }

        model.value = checked.value ? model.value.filter((value) => value !== props.value) : [...model.value, props.value];
        return;
    }

    model.value = !checked.value;
}
</script>

<template>
    <button
        type="button"
        role="checkbox"
        :aria-checked="indeterminate ? 'mixed' : checked"
        :aria-label="ariaLabel ?? label"
        :disabled="disabled"
        class="group inline-flex gap-2 text-sm text-zinc-700 outline-none disabled:cursor-not-allowed disabled:opacity-50 dark:text-zinc-200"
        :class="align === 'start' ? 'items-start' : 'items-center'"
        @click="toggle"
    >
        <span
            class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded border transition group-focus-visible:outline-2 group-focus-visible:outline-offset-2 group-focus-visible:outline-teal-700"
            :class="
                checked || indeterminate
                    ? 'border-teal-600 bg-teal-600 text-white dark:border-teal-400 dark:bg-teal-400 dark:text-zinc-950'
                    : 'border-zinc-300 bg-white text-transparent group-hover:border-teal-500 dark:border-zinc-700 dark:bg-zinc-950'
            "
        >
            <IconMinus v-if="indeterminate && !checked" aria-hidden="true" class="h-3 w-3" :stroke-width="2.2" />
            <IconCheck v-else aria-hidden="true" class="h-3 w-3" :stroke-width="2.2" />
        </span>
        <span v-if="label || $slots.default" class="text-left">
            <slot>{{ label }}</slot>
        </span>
    </button>
</template>
