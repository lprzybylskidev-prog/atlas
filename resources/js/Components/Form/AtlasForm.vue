<script setup lang="ts">
const props = withDefaults(
    defineProps<{
        processing?: boolean;
    }>(),
    {
        processing: false,
    },
);

const emit = defineEmits<{
    submit: [];
}>();

function submit(): void {
    if (props.processing) {
        return;
    }

    emit('submit');
}
</script>

<template>
    <form novalidate :aria-busy="processing" @submit.prevent="submit">
        <slot />
    </form>
</template>
