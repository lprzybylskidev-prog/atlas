<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

import type { AtlasPageProps } from '../../Types/inertia';
import CallOverlay from './CallOverlay.vue';
import ChatShell from './ChatShell.vue';
import MeetingLiveRuntime from './MeetingLiveRuntime.vue';

const page = usePage<AtlasPageProps>();
const routes = computed(() => page.props.auth.availableApplicationRoutes);
</script>

<template>
    <ChatShell v-if="page.props.auth.user !== null && routes.includes('chat.index')" />
    <CallOverlay v-if="page.props.auth.user !== null && routes.includes('chat.calls.current')" />
    <MeetingLiveRuntime v-if="page.props.auth.user !== null && routes.includes('chat.meetings.show')" />
</template>
