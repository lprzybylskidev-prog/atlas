import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

import { chatJson } from './chatAttachments';

export type CallStatus = 'ringing' | 'active' | 'ended' | 'declined' | 'busy' | 'missed' | 'failed';
export type CallParticipantState = 'ringing' | 'notified' | 'joined' | 'declined' | 'busy' | 'missed' | 'left' | 'failed';

export interface CallParticipant {
    publicId: string;
    name: string;
    state: CallParticipantState;
    cameraEnabled: boolean;
    microphoneEnabled: boolean;
    screenSharing: boolean;
}

export interface CallSnapshot {
    publicId: string;
    conversationPublicId: string;
    conversationType: 'direct' | 'group' | 'team';
    conversationLabel: string;
    startedByUserPublicId: string;
    startedByName: string;
    initialCameraEnabled: boolean;
    status: CallStatus;
    currentUserState: CallParticipantState;
    incoming: boolean;
    teamJoinStyle: boolean;
    canRejoin: boolean;
    startedAt: string;
    answeredAt: string | null;
    endedAt: string | null;
    participants: CallParticipant[];
}

export interface RtcParticipantAccess {
    serverUrl: string;
    participantToken: string;
    expiresAt: string;
    roomName: string;
}

export interface CallPreferences {
    cameraDeviceId: string | null;
    microphoneDeviceId: string | null;
    speakerDeviceId: string | null;
    outgoingCameraEnabled: boolean;
}

export interface CallJoinResponse {
    call: CallSnapshot;
    rtc: RtcParticipantAccess | null;
}

export interface MediaDeviceOptions {
    cameras: MediaDeviceInfo[];
    microphones: MediaDeviceInfo[];
    speakers: MediaDeviceInfo[];
}

export async function currentCall(): Promise<CallSnapshot | null> {
    return (await chatJson<{ call: CallSnapshot | null }>('/chat/calls/current')).call;
}

export async function callPreferences(): Promise<CallPreferences> {
    return (await chatJson<{ preferences: CallPreferences }>('/chat/call-preferences')).preferences;
}

export async function saveCallPreferences(preferences: CallPreferences): Promise<CallPreferences> {
    return (
        await chatJson<{ preferences: CallPreferences }>('/chat/call-preferences', 'PATCH', {
            camera_device_id: preferences.cameraDeviceId,
            microphone_device_id: preferences.microphoneDeviceId,
            speaker_device_id: preferences.speakerDeviceId,
            outgoing_camera_enabled: preferences.outgoingCameraEnabled,
        })
    ).preferences;
}

export function startCall(conversationPublicId: string, cameraEnabled: boolean, requestKey: string): Promise<CallJoinResponse> {
    return chatJson(`/chat/conversations/${conversationPublicId}/calls`, 'POST', {
        camera_enabled: cameraEnabled,
        client_request_key: requestKey,
    });
}

export function joinCall(callPublicId: string, cameraEnabled: boolean, microphoneEnabled: boolean): Promise<CallJoinResponse> {
    return chatJson(`/chat/calls/${callPublicId}/join`, 'POST', {
        camera_enabled: cameraEnabled,
        microphone_enabled: microphoneEnabled,
    });
}

export async function declineCall(callPublicId: string): Promise<CallSnapshot> {
    return (await chatJson<{ call: CallSnapshot }>(`/chat/calls/${callPublicId}/decline`, 'POST')).call;
}

export async function leaveCall(callPublicId: string): Promise<CallSnapshot> {
    return (await chatJson<{ call: CallSnapshot }>(`/chat/calls/${callPublicId}/leave`, 'POST')).call;
}

export function updateCallMedia(callPublicId: string, cameraEnabled: boolean, microphoneEnabled: boolean): Promise<unknown> {
    return chatJson(`/chat/calls/${callPublicId}/media`, 'PATCH', {
        camera_enabled: cameraEnabled,
        microphone_enabled: microphoneEnabled,
    });
}

export function updateScreenShare(callPublicId: string, active: boolean): Promise<unknown> {
    return chatJson(`/chat/calls/${callPublicId}/screen-share`, active ? 'POST' : 'DELETE');
}

export async function availableMediaDevices(): Promise<MediaDeviceOptions> {
    const devices = await navigator.mediaDevices.enumerateDevices();

    return {
        cameras: devices.filter((device) => device.kind === 'videoinput'),
        microphones: devices.filter((device) => device.kind === 'audioinput'),
        speakers: devices.filter((device) => device.kind === 'audiooutput'),
    };
}

interface CallRealtimeEvent {
    callPublicId: string;
}

function realtimeMeta(name: string, fallback: string | undefined): string | undefined {
    return document.querySelector<HTMLMetaElement>(`meta[name="atlas-reverb-${name}"]`)?.content || fallback;
}

export class CallRealtimeClient {
    private readonly echo: Echo<'reverb'>;

    constructor(
        private readonly userPublicId: string,
        private readonly changed: (event: CallRealtimeEvent) => void,
    ) {
        window.Pusher = Pusher;
        const scheme = realtimeMeta('scheme', import.meta.env.VITE_REVERB_SCHEME) ?? 'https';
        this.echo = new Echo({
            broadcaster: 'reverb',
            key: realtimeMeta('key', import.meta.env.VITE_REVERB_APP_KEY) ?? '',
            wsHost: realtimeMeta('host', import.meta.env.VITE_REVERB_HOST) ?? window.location.hostname,
            wsPort: Number(realtimeMeta('port', import.meta.env.VITE_REVERB_PORT) ?? 80),
            wssPort: Number(realtimeMeta('port', import.meta.env.VITE_REVERB_PORT) ?? 443),
            forceTLS: scheme === 'https',
            enabledTransports: ['ws', 'wss'],
        });
    }

    start(): void {
        this.echo
            .private(`chat.user.${this.userPublicId}`)
            .listen('.chat.call.incoming', this.changed)
            .listen('.chat.call.available', this.changed)
            .listen('.chat.call.updated', this.changed);
    }

    stop(): void {
        this.echo.leave(`chat.user.${this.userPublicId}`);
        this.echo.disconnect();
    }
}
