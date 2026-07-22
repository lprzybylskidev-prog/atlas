<script setup lang="ts">
import { IconFilter, IconRefresh } from '@tabler/icons-vue';

import SurfaceCard from './SurfaceCard.vue';
import AtlasForm from './Form/AtlasForm.vue';
import FormButton from './Form/FormButton.vue';

withDefaults(
    defineProps<{
        title?: string;
        summary?: string;
        applyLabel?: string;
        clearLabel?: string;
    }>(),
    {
        title: 'Filters',
        summary: undefined,
        applyLabel: 'Apply',
        clearLabel: 'Clear',
    },
);

const emit = defineEmits<{
    apply: [];
    clear: [];
}>();
</script>

<template>
    <AtlasForm @submit="emit('apply')">
        <SurfaceCard :title="title" :icon="IconFilter" tone="zinc">
            <template #actions>
                <div class="flex flex-wrap justify-end gap-2">
                    <FormButton type="button" tone="neutral" :icon="IconRefresh" @click="emit('clear')">
                        {{ clearLabel }}
                    </FormButton>
                    <FormButton type="submit" :icon="IconFilter">
                        {{ applyLabel }}
                    </FormButton>
                </div>
            </template>

            <div class="space-y-4">
                <slot />

                <p v-if="summary" class="text-sm text-zinc-500 dark:text-zinc-400">{{ summary }}</p>
            </div>
        </SurfaceCard>
    </AtlasForm>
</template>
