<script setup lang="ts">
import { IconPhoto, IconRefresh, IconScissors, IconZoomIn, IconZoomOut } from '@tabler/icons-vue';
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import { CircleStencil, Cropper, RectangleStencil } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';

import DialogPanel from '../DialogPanel.vue';
import FormButton from './FormButton.vue';
import FormFileUpload from './FormFileUpload.vue';

interface CropperResult {
    canvas?: HTMLCanvasElement;
}

interface CropperInstance {
    getResult: () => CropperResult;
    refresh: () => void;
    zoom: (factor: number) => void;
}

const model = defineModel<File | null>({ required: true });

const props = withDefaults(
    defineProps<{
        label: string;
        chooseLabel: string;
        cropLabel: string;
        cropActionLabel?: string;
        resetLabel: string;
        zoomInLabel: string;
        zoomOutLabel: string;
        accept?: string;
        aspectRatio?: number;
        outputHeight?: number;
        outputMime?: 'image/jpeg' | 'image/png' | 'image/webp';
        outputQuality?: number;
        outputSuffix?: string;
        outputWidth?: number;
        stencil?: 'circle' | 'rectangle';
        error?: string;
    }>(),
    {
        accept: 'image/png,image/jpeg,image/webp',
        aspectRatio: 1,
        cropActionLabel: undefined,
        error: undefined,
        outputHeight: 512,
        outputMime: 'image/webp',
        outputQuality: 0.92,
        outputSuffix: 'cropped',
        outputWidth: 512,
        stencil: 'rectangle',
    },
);

const cropper = ref<CropperInstance | null>(null);
const selectedFile = ref<File | null>(null);
const sourceUrl = ref('');
const sourceName = ref('image.png');
const open = ref(false);
const uploadKey = ref(0);
const croppedLabel = computed(() => model.value?.name ?? props.chooseLabel);
const resolvedCropActionLabel = computed(() => props.cropActionLabel ?? props.cropLabel);
const stencilComponent = computed(() => (props.stencil === 'circle' ? CircleStencil : RectangleStencil));
const fixedStencilProps = computed(() => ({
    aspectRatio: props.aspectRatio,
}));
const outputExtension = computed(() => props.outputMime.replace('image/', '').replace('jpeg', 'jpg'));

watch(selectedFile, (file) => {
    if (file === null) {
        return;
    }

    sourceName.value = file.name;
    sourceUrl.value = '';
    model.value = null;
    readFileSource(file);
});

watch(open, (nextOpen) => {
    if (!nextOpen) {
        return;
    }

    void nextTick(() => {
        window.setTimeout(() => cropper.value?.refresh(), 0);
    });
});

onBeforeUnmount(() => {
    sourceUrl.value = '';
});

function zoom(factor: number): void {
    cropper.value?.zoom(factor);
}

function crop(): void {
    const canvas = cropper.value?.getResult().canvas;

    if (canvas === undefined) {
        return;
    }

    canvas.toBlob(
        (blob) => {
            if (blob === null) {
                return;
            }

            model.value = new File([blob], `${sourceName.value.replace(/\.[^.]+$/, '')}-${props.outputSuffix}.${outputExtension.value}`, {
                type: props.outputMime,
            });
            selectedFile.value = null;
            uploadKey.value += 1;
            open.value = false;
        },
        props.outputMime,
        props.outputQuality,
    );
}

function reset(): void {
    sourceUrl.value = '';
    selectedFile.value = null;
    model.value = null;
    open.value = false;
}

function cancelCrop(): void {
    sourceUrl.value = '';
    selectedFile.value = null;
    uploadKey.value += 1;
}

function readFileSource(file: File): void {
    const reader = new FileReader();

    reader.addEventListener('load', () => {
        sourceUrl.value = typeof reader.result === 'string' ? reader.result : '';
        open.value = sourceUrl.value !== '';
    });

    reader.readAsDataURL(file);
}
</script>

<template>
    <div class="space-y-2">
        <FormFileUpload :key="uploadKey" v-model="selectedFile" :label="label" :accept="accept" :error="error" />
        <p v-if="model" class="text-xs font-medium text-teal-700 dark:text-teal-300">{{ croppedLabel }}</p>

        <DialogPanel v-model:open="open" :title="cropLabel" :icon="IconPhoto" tone="teal" size="3xl" @close="cancelCrop">
            <div class="space-y-4">
                <div class="relative overflow-hidden rounded-lg bg-zinc-950 shadow-sm ring-1 ring-zinc-200 dark:ring-zinc-800">
                    <Cropper
                        v-if="open && sourceUrl"
                        ref="cropper"
                        class="form-image-cropper h-[420px] max-h-[62vh]"
                        :src="sourceUrl"
                        :stencil-component="stencilComponent"
                        :stencil-props="fixedStencilProps"
                        :canvas="{ width: outputWidth, height: outputHeight, imageSmoothingEnabled: true, imageSmoothingQuality: 'high' }"
                        image-restriction="stencil"
                        :auto-zoom="true"
                        :transitions="false"
                    />
                    <div class="absolute right-2 top-2 z-10 flex gap-1.5">
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-white/20 bg-zinc-950/80 text-white shadow-sm backdrop-blur transition hover:border-teal-300 hover:bg-teal-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                            :aria-label="zoomInLabel"
                            @click="zoom(1.15)"
                        >
                            <IconZoomIn aria-hidden="true" class="h-3.5 w-3.5" :stroke-width="1.8" />
                        </button>
                        <button
                            type="button"
                            class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-white/20 bg-zinc-950/80 text-white shadow-sm backdrop-blur transition hover:border-teal-300 hover:bg-teal-600 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-500"
                            :aria-label="zoomOutLabel"
                            @click="zoom(0.87)"
                        >
                            <IconZoomOut aria-hidden="true" class="h-3.5 w-3.5" :stroke-width="1.8" />
                        </button>
                    </div>
                </div>
                <div class="flex flex-wrap justify-end gap-2">
                    <FormButton type="button" tone="neutral" :icon="IconRefresh" @click="reset">
                        {{ resetLabel }}
                    </FormButton>
                    <FormButton type="button" :icon="IconScissors" @click="crop">
                        {{ resolvedCropActionLabel }}
                    </FormButton>
                </div>
            </div>
        </DialogPanel>
    </div>
</template>

<style scoped>
.form-image-cropper {
    --vac-handler-background: rgb(15 118 110);
    --vac-handler-border-color: rgb(255 255 255);
    --vac-handler-border-radius: 9999px;
    --vac-line-color: rgb(255 255 255 / 0.75);
    --vac-stencil-border: solid 2px rgb(255 255 255);
}

.form-image-cropper :deep(.vue-advanced-cropper__foreground) {
    background: rgb(0 0 0 / 0.56);
}

.form-image-cropper :deep(.vue-circle-stencil__preview),
.form-image-cropper :deep(.vue-rectangle-stencil__preview) {
    border: 2px solid rgb(255 255 255 / 0.95);
    box-shadow:
        0 0 0 1px rgb(24 24 27 / 0.25),
        0 18px 45px rgb(0 0 0 / 0.28);
}
</style>
