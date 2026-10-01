<script setup lang="ts">
import { IconPhone } from '@tabler/icons-vue';

import { useTranslator } from '../../Localization/translator';
import FormButton from '../Form/FormButton.vue';

const props = defineProps<{ conversationPublicId: string }>();
const { t } = useTranslator();

function prepare(): void {
    window.dispatchEvent(
        new CustomEvent('atlas:call-prepare', {
            detail: { conversationPublicId: props.conversationPublicId, cameraEnabled: null },
        }),
    );
}
</script>

<template>
    <div class="flex flex-wrap gap-2" :aria-label="t('calls.launcher.label')">
        <FormButton tone="primary" :icon="IconPhone" @click="prepare">
            {{ t('calls.actions.start') }}
        </FormButton>
    </div>
</template>
