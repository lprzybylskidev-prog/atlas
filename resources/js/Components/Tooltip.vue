<script setup lang="ts">
withDefaults(
    defineProps<{
        text: string;
        disabled?: boolean;
        fullWidth?: boolean;
        align?: 'center' | 'end' | 'start';
        placement?: 'right' | 'top';
    }>(),
    {
        disabled: false,
        fullWidth: false,
        align: 'center',
        placement: 'right',
    },
);
</script>

<template>
    <span class="group/tooltip relative inline-flex max-w-full" :class="{ 'w-full min-w-0': fullWidth }">
        <slot />
        <span
            v-if="!disabled"
            role="tooltip"
            class="pointer-events-none absolute z-50 max-w-80 select-none rounded-md bg-zinc-950 px-2 py-1 text-xs font-medium whitespace-normal text-white opacity-0 shadow-lg transition-[opacity,transform] duration-300 ease-in-out break-words group-hover/tooltip:opacity-100 group-focus-within/tooltip:opacity-100 dark:bg-zinc-100 dark:text-zinc-950"
            :class="[
                placement === 'right'
                    ? 'top-1/2 left-full ml-2 -translate-y-1/2 translate-x-1 group-hover/tooltip:translate-x-0 group-focus-within/tooltip:translate-x-0'
                    : align === 'end'
                      ? 'right-0 bottom-full mb-2 translate-y-1 group-hover/tooltip:translate-y-0 group-focus-within/tooltip:translate-y-0'
                      : align === 'start'
                        ? 'bottom-full left-0 mb-2 translate-y-1 group-hover/tooltip:translate-y-0 group-focus-within/tooltip:translate-y-0'
                        : 'bottom-full left-1/2 mb-2 -translate-x-1/2 translate-y-1 group-hover/tooltip:translate-y-0 group-focus-within/tooltip:translate-y-0',
            ]"
        >
            {{ text }}
        </span>
    </span>
</template>
