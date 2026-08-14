<script setup lang="ts">
import ChatAttachmentComposer from '../Components/Chat/ChatAttachmentComposer.vue';

defineProps<{ conversationPublicId: string }>();
</script>

<template>
    <ChatAttachmentComposer :conversation-public-id="conversationPublicId" allow-attachments allow-voice />
</template>
