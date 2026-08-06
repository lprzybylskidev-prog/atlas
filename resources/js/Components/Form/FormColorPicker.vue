<script setup lang="ts">
import iro from '@jaames/iro';
import { IconPalette } from '@tabler/icons-vue';
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import DialogPanel from '../DialogPanel.vue';

const model = defineModel<string>({ required: true });

const props = withDefaults(
    defineProps<{
        label: string;
        redLabel: string;
        greenLabel: string;
        blueLabel: string;
        defaultColor?: string;
        error?: string;
    }>(),
    {
        defaultColor: '#0f766e',
        error: undefined,
    },
);

const pickerHost = ref<HTMLElement | null>(null);
const open = ref(false);
let picker: iro.ColorPicker | null = null;
let syncingFromPicker = false;

const normalizedColor = computed(() => normalizeHex(model.value));
const rgb = computed(() => hexToRgb(normalizedColor.value));
const rgbCode = computed(() => `rgb(${rgb.value.r},${rgb.value.g},${rgb.value.b})`);
const red = computed({
    get: () => String(rgb.value.r),
    set: (value: string) => updateChannel('r', value),
});
const green = computed({
    get: () => String(rgb.value.g),
    set: (value: string) => updateChannel('g', value),
});
const blue = computed({
    get: () => String(rgb.value.b),
    set: (value: string) => updateChannel('b', value),
});

onMounted(() => {
    watch(
        open,
        (nextOpen) => {
            if (nextOpen) {
                void nextTick(mountPicker);
            }
        },
        { immediate: true },
    );
});

onBeforeUnmount(() => {
    destroyPicker();
});

watch(normalizedColor, (color) => {
    if (model.value !== color) {
        model.value = color;
    }

    if (picker !== null && picker.color.hexString.toLowerCase() !== color.toLowerCase() && !syncingFromPicker) {
        picker.color.hexString = color;
    }
});

function mountPicker(): void {
    if (pickerHost.value === null) {
        return;
    }

    destroyPicker();

    picker = iro.ColorPicker(pickerHost.value, {
        width: 124,
        color: normalizedColor.value,
        borderWidth: 0,
        handleRadius: 6,
        padding: 2,
        layout: [
            {
                component: iro.ui.Wheel,
                options: {
                    wheelLightness: false,
                },
            },
            {
                component: iro.ui.Slider,
                options: {
                    sliderType: 'value',
                },
            },
        ],
    });

    picker.on('color:change', (color: iro.Color) => {
        syncingFromPicker = true;
        model.value = color.hexString;
        void nextTick(() => {
            syncingFromPicker = false;
        });
    });
}

function destroyPicker(): void {
    picker = null;
    pickerHost.value?.replaceChildren();
}

function updateChannel(channel: 'b' | 'g' | 'r', value: string): void {
    model.value = rgbToHex({
        ...rgb.value,
        [channel]: clampRgb(value),
    });
}

function normalizeHex(hex: string): string {
    if (/^#[0-9A-Fa-f]{6}$/.test(hex)) {
        return hex;
    }

    return /^#[0-9A-Fa-f]{6}$/.test(props.defaultColor) ? props.defaultColor : '#0f766e';
}

function hexToRgb(hex: string): { r: number; g: number; b: number } {
    const normalized = normalizeHex(hex).slice(1);

    return {
        r: Number.parseInt(normalized.slice(0, 2), 16),
        g: Number.parseInt(normalized.slice(2, 4), 16),
        b: Number.parseInt(normalized.slice(4, 6), 16),
    };
}

function rgbToHex(value: { r: number; g: number; b: number }): string {
    return `#${[value.r, value.g, value.b].map((channel) => channel.toString(16).padStart(2, '0')).join('')}`;
}

function clampRgb(value: string): number {
    const parsed = Number.parseInt(value, 10);

    if (!Number.isFinite(parsed)) {
        return 0;
    }

    return Math.max(0, Math.min(255, parsed));
}
</script>

<template>
    <div class="space-y-2">
        <button
            type="button"
            class="flex w-full items-center justify-between gap-3 rounded-lg border border-zinc-300 bg-white px-3 py-2 text-left text-sm transition hover:border-teal-400 hover:bg-teal-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500 dark:border-zinc-700 dark:bg-zinc-900 dark:hover:border-teal-700 dark:hover:bg-teal-950/40"
            :aria-label="label"
            @click="open = true"
        >
            <span class="flex min-w-0 items-center gap-3">
                <span
                    class="h-8 w-8 shrink-0 rounded-full shadow-sm ring-1 ring-zinc-200 ring-inset dark:ring-zinc-700"
                    :style="{ backgroundColor: normalizedColor }"
                />
                <span class="min-w-0">
                    <span class="block font-medium text-zinc-800 dark:text-zinc-100">{{ label }}</span>
                    <span class="block font-mono text-xs text-zinc-500 dark:text-zinc-400">{{ normalizedColor.toUpperCase() }}</span>
                </span>
            </span>
            <IconPalette aria-hidden="true" class="h-5 w-5 shrink-0 text-zinc-400" :stroke-width="1.8" />
        </button>

        <p v-if="error" class="text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>

        <DialogPanel v-model:open="open" :title="label" :icon="IconPalette" tone="teal" size="lg">
            <div class="grid items-center justify-center gap-5 sm:grid-cols-[148px_284px]">
                <div class="flex justify-center">
                    <div ref="pickerHost" class="form-color-picker h-[152px] w-[124px]" />
                </div>
                <div class="w-full min-w-0 space-y-3">
                    <div class="flex items-center gap-3">
                        <span
                            class="h-12 w-12 shrink-0 rounded-md shadow-sm ring-1 ring-zinc-200 ring-inset dark:ring-zinc-700"
                            :style="{ backgroundColor: normalizedColor }"
                        />
                        <p class="whitespace-nowrap font-mono text-sm font-medium text-zinc-800 dark:text-zinc-100">{{ rgbCode }}</p>
                    </div>
                    <div class="grid grid-cols-3 gap-2">
                        <label class="relative block">
                            <span class="sr-only">{{ redLabel }}</span>
                            <span
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-zinc-500 dark:text-zinc-400"
                            >
                                {{ redLabel }}
                            </span>
                            <input
                                v-model="red"
                                type="text"
                                inputmode="numeric"
                                class="h-9 w-full rounded-md border border-zinc-300 bg-white px-2 pl-7 text-center text-sm text-zinc-950 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:focus:ring-teal-950"
                            />
                        </label>
                        <label class="relative block">
                            <span class="sr-only">{{ greenLabel }}</span>
                            <span
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-zinc-500 dark:text-zinc-400"
                            >
                                {{ greenLabel }}
                            </span>
                            <input
                                v-model="green"
                                type="text"
                                inputmode="numeric"
                                class="h-9 w-full rounded-md border border-zinc-300 bg-white px-2 pl-7 text-center text-sm text-zinc-950 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:focus:ring-teal-950"
                            />
                        </label>
                        <label class="relative block">
                            <span class="sr-only">{{ blueLabel }}</span>
                            <span
                                class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-xs font-semibold text-zinc-500 dark:text-zinc-400"
                            >
                                {{ blueLabel }}
                            </span>
                            <input
                                v-model="blue"
                                type="text"
                                inputmode="numeric"
                                class="h-9 w-full rounded-md border border-zinc-300 bg-white px-2 pl-7 text-center text-sm text-zinc-950 outline-none transition focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:focus:ring-teal-950"
                            />
                        </label>
                    </div>
                </div>
            </div>
        </DialogPanel>
    </div>
</template>

<style scoped>
.form-color-picker :deep(.IroColorPicker) {
    display: block;
}

.form-color-picker :deep(.IroWheel) {
    border-radius: 9999px;
    box-shadow:
        0 0 0 1px rgb(39 39 42),
        inset 0 0 0 1px rgb(255 255 255 / 0.08);
    overflow: hidden;
    transform: translateZ(0);
}

.form-color-picker :deep(.IroWheelHue),
.form-color-picker :deep(.IroWheelSaturation),
.form-color-picker :deep(.IroWheelLightness),
.form-color-picker :deep(.IroWheelBorder) {
    backface-visibility: hidden;
    transform-style: preserve-3d;
}

.form-color-picker :deep(.IroSlider),
.form-color-picker :deep(.IroSliderGradient) {
    border-radius: 9999px !important;
    overflow: hidden;
}

.form-color-picker :deep(.IroSlider) {
    background: transparent !important;
    box-shadow:
        0 0 0 1px rgb(39 39 42),
        inset 0 0 0 1px rgb(255 255 255 / 0.08);
    isolation: isolate;
}

.form-color-picker :deep(.IroSliderGradient) {
    backface-visibility: hidden;
    clip-path: inset(0 0 0 0 round 9999px);
    image-rendering: auto;
    transform: translateZ(0) scaleX(1.015);
    transform-origin: center;
}

.form-color-picker :deep(svg) {
    display: block;
}

.form-color-picker :deep(.IroHandle circle:first-child) {
    stroke: rgb(255 255 255);
    stroke-width: 5px;
}

.form-color-picker :deep(.IroHandle circle:last-child) {
    stroke: rgb(24 24 27 / 0.5);
    stroke-width: 1.5px;
}
</style>
