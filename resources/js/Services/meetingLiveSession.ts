import { router } from '@inertiajs/vue3';
import { markRaw, ref } from 'vue';

import type { CallMediaSession } from './callMediaSession';

export interface MeetingLiveActions {
    toggleMicrophone(): Promise<void>;
    toggleCamera(): Promise<void>;
    leave(): Promise<void>;
    end(): Promise<void>;
}

export const meetingLiveSession = ref<CallMediaSession | null>(null);
export const meetingLiveActive = ref(false);
export const meetingLiveMinimized = ref(false);
export const meetingLiveCameraEnabled = ref(false);
export const meetingLiveMicrophoneEnabled = ref(true);
export const meetingLiveScreenSharing = ref(false);
export const meetingLiveRecordingStatus = ref('not_recording');
export const meetingLiveTitle = ref('');
export const meetingLivePublicId = ref<string | null>(null);
export const meetingLiveOrganizer = ref(false);
export const meetingLiveRemoteElements = ref<HTMLMediaElement[]>([]);

let actions: MeetingLiveActions | null = null;

export function configureMeetingLiveSession(publicId: string, title: string, organizer: boolean, handlers: MeetingLiveActions): void {
    meetingLivePublicId.value = publicId;
    meetingLiveTitle.value = title;
    meetingLiveOrganizer.value = organizer;
    actions = handlers;
}

export function addMeetingRemoteElement(element: HTMLMediaElement): void {
    meetingLiveRemoteElements.value.push(markRaw(element));
}

export function clearMeetingLiveSession(): void {
    meetingLiveSession.value = null;
    meetingLiveActive.value = false;
    meetingLiveMinimized.value = false;
    meetingLiveScreenSharing.value = false;
    meetingLiveRemoteElements.value = [];
    meetingLivePublicId.value = null;
    meetingLiveTitle.value = '';
    meetingLiveOrganizer.value = false;
    meetingLiveRecordingStatus.value = 'not_recording';
    actions = null;
}

export function restoreMeetingLiveSession(): void {
    const publicId = meetingLivePublicId.value;
    if (publicId === null) return;
    meetingLiveMinimized.value = false;
    const open = (): void => {
        window.dispatchEvent(new CustomEvent('atlas:meeting-live-restore'));
    };
    if (window.location.pathname === `/meetings/${publicId}`) {
        open();
        return;
    }
    router.visit(`/meetings/${publicId}`, { onSuccess: open });
}

export async function toggleMeetingLiveMicrophone(): Promise<void> {
    await actions?.toggleMicrophone();
}

export async function toggleMeetingLiveCamera(): Promise<void> {
    await actions?.toggleCamera();
}

export async function leaveMeetingLiveSession(): Promise<void> {
    await actions?.leave();
}

export async function endMeetingLiveSession(): Promise<void> {
    await actions?.end();
}
