<script setup lang="ts">
import { IconLoader2 } from '@tabler/icons-vue';
import { computed } from 'vue';

import { fullscreenTransitionLoading } from '../Services/fullscreenTransitionLoading';

const isDarkOverlay = computed(() => fullscreenTransitionLoading.theme === 'dark');
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div
                v-if="fullscreenTransitionLoading.visible"
                data-testid="fullscreen-transition-loader"
                class="fixed inset-0 z-[9999] flex items-center justify-center"
                :class="isDarkOverlay ? 'bg-zinc-950 text-zinc-50' : 'bg-zinc-50 text-zinc-950'"
                role="status"
                aria-live="polite"
                aria-busy="true"
            >
                <div
                    class="flex h-16 w-16 items-center justify-center rounded-lg border"
                    :class="
                        isDarkOverlay
                            ? 'border-zinc-800 bg-zinc-900 text-teal-300 shadow-2xl shadow-black/30'
                            : 'border-zinc-200 bg-white text-teal-700 shadow-2xl shadow-zinc-950/10'
                    "
                >
                    <IconLoader2 aria-hidden="true" class="h-7 w-7 animate-spin" :stroke-width="1.8" />
                    <span class="sr-only">Loading</span>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
