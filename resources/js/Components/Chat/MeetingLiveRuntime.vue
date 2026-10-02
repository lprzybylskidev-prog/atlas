<script setup lang="ts">
import MeetingMinimizedControls from './MeetingMinimizedControls.vue';
import {
    endMeetingLiveSession,
    leaveMeetingLiveSession,
    meetingLiveActive,
    meetingLiveCameraEnabled,
    meetingLiveMicrophoneEnabled,
    meetingLiveMinimized,
    meetingLiveOrganizer,
    meetingLiveRecordingStatus,
    meetingLiveScreenSharing,
    meetingLiveTitle,
    restoreMeetingLiveSession,
    toggleMeetingLiveCamera,
    toggleMeetingLiveMicrophone,
} from '../../Services/meetingLiveSession';
</script>

<template>
    <MeetingMinimizedControls
        v-if="meetingLiveActive && meetingLiveMinimized"
        :title="meetingLiveTitle"
        :microphone-enabled="meetingLiveMicrophoneEnabled"
        :camera-enabled="meetingLiveCameraEnabled"
        :screen-sharing="meetingLiveScreenSharing"
        :recording-status="meetingLiveRecordingStatus"
        :organizer="meetingLiveOrganizer"
        @microphone="toggleMeetingLiveMicrophone"
        @camera="toggleMeetingLiveCamera"
        @restore="restoreMeetingLiveSession"
        @leave="leaveMeetingLiveSession"
        @end="endMeetingLiveSession"
    />
</template>
