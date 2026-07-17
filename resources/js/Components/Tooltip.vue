<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import type { CSSProperties } from 'vue';

type ResolvedPlacement = 'bottom' | 'left' | 'right' | 'top';

const props = withDefaults(
    defineProps<{
        text: string;
        disabled?: boolean;
        fullWidth?: boolean;
        align?: 'center' | 'end' | 'start';
        placement?: 'right' | 'top';
        wide?: boolean;
    }>(),
    {
        disabled: false,
        fullWidth: false,
        align: 'center',
        placement: 'right',
        wide: false,
    },
);

const trigger = ref<HTMLElement | null>(null);
const tooltip = ref<HTMLElement | null>(null);
const visible = ref(false);
const positioned = ref(false);
const tooltipStyle = ref<CSSProperties>({});
const tooltipId = `tooltip-${Math.random().toString(36).slice(2)}`;

const tooltipMaxWidth = computed(() => (props.wide ? 'min(48rem, calc(100vw - 1rem))' : 'min(20rem, calc(100vw - 1rem))'));
const baseTooltipStyle = computed<CSSProperties>(() => ({
    maxHeight: 'calc(100vh - 1rem)',
    maxWidth: tooltipMaxWidth.value,
    overflowY: 'auto',
    visibility: positioned.value ? 'visible' : 'hidden',
    zIndex: 9999,
}));

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), Math.max(min, max));
}

function alignedHorizontalLeft(triggerRect: DOMRect, tooltipRect: DOMRect): number {
    if (props.align === 'start') {
        return triggerRect.left;
    }

    if (props.align === 'end') {
        return triggerRect.right - tooltipRect.width;
    }

    return triggerRect.left + triggerRect.width / 2 - tooltipRect.width / 2;
}

function candidatePosition(placement: ResolvedPlacement, triggerRect: DOMRect, tooltipRect: DOMRect): { left: number; top: number } {
    const gap = 8;

    if (placement === 'top') {
        return {
            left: alignedHorizontalLeft(triggerRect, tooltipRect),
            top: triggerRect.top - tooltipRect.height - gap,
        };
    }

    if (placement === 'bottom') {
        return {
            left: alignedHorizontalLeft(triggerRect, tooltipRect),
            top: triggerRect.bottom + gap,
        };
    }

    if (placement === 'left') {
        return {
            left: triggerRect.left - tooltipRect.width - gap,
            top: triggerRect.top + triggerRect.height / 2 - tooltipRect.height / 2,
        };
    }

    return {
        left: triggerRect.right + gap,
        top: triggerRect.top + triggerRect.height / 2 - tooltipRect.height / 2,
    };
}

function fitsViewport(position: { left: number; top: number }, tooltipRect: DOMRect): boolean {
    const margin = 8;

    return (
        position.left >= margin &&
        position.top >= margin &&
        position.left + tooltipRect.width <= window.innerWidth - margin &&
        position.top + tooltipRect.height <= window.innerHeight - margin
    );
}

function placementCandidates(): ResolvedPlacement[] {
    return props.placement === 'top' ? ['top', 'bottom', 'right', 'left'] : ['right', 'left', 'top', 'bottom'];
}

function updatePosition(): void {
    const triggerElement = trigger.value;
    const tooltipElement = tooltip.value;

    if (triggerElement === null || tooltipElement === null || props.disabled) {
        return;
    }

    const triggerRect = triggerElement.getBoundingClientRect();
    const tooltipRect = tooltipElement.getBoundingClientRect();
    const margin = 8;
    const candidates = placementCandidates().map((placement) => candidatePosition(placement, triggerRect, tooltipRect));
    const selected = candidates.find((position) => fitsViewport(position, tooltipRect)) ?? candidates[0];

    tooltipStyle.value = {
        left: `${clamp(selected.left, margin, window.innerWidth - tooltipRect.width - margin)}px`,
        top: `${clamp(selected.top, margin, window.innerHeight - tooltipRect.height - margin)}px`,
    };
    positioned.value = true;
}

function showTooltip(): void {
    if (props.disabled || props.text === '') {
        return;
    }

    positioned.value = false;
    visible.value = true;
    void nextTick(updatePosition);
}

function hideTooltip(): void {
    visible.value = false;
}

onMounted(() => {
    window.addEventListener('resize', updatePosition);
    window.addEventListener('scroll', updatePosition, true);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', updatePosition);
    window.removeEventListener('scroll', updatePosition, true);
});

watch(
    () => [props.text, props.disabled, props.placement, props.align, props.wide],
    () => {
        if (visible.value) {
            void nextTick(updatePosition);
        }
    },
);
</script>

<template>
    <span
        ref="trigger"
        class="relative inline-flex max-w-full"
        :class="{ 'w-full min-w-0': fullWidth }"
        :aria-describedby="visible && !disabled ? tooltipId : undefined"
        @mouseenter="showTooltip"
        @mouseleave="hideTooltip"
        @focusin="showTooltip"
        @focusout="hideTooltip"
    >
        <slot />
        <Teleport to="body">
            <span
                v-if="visible && !disabled"
                :id="tooltipId"
                ref="tooltip"
                role="tooltip"
                class="pointer-events-none fixed select-none rounded-md bg-zinc-950 px-2 py-1 text-xs font-medium whitespace-normal text-white shadow-lg break-words dark:bg-zinc-100 dark:text-zinc-950"
                :style="[baseTooltipStyle, tooltipStyle]"
            >
                {{ text }}
            </span>
        </Teleport>
    </span>
</template>
