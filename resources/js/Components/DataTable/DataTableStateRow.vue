<script setup lang="ts">
import UiState from '../UiState.vue';

defineProps<{
    colspan: number;
    variant: 'loading-initial' | 'loading-refresh' | 'empty' | 'no-results' | 'error-recoverable';
    title: string;
}>();
</script>

<template>
    <tr>
        <td :colspan="colspan" class="p-3">
            <UiState :variant="variant" :title="title" size="compact">
                <slot />
            </UiState>
        </td>
    </tr>
</template>
