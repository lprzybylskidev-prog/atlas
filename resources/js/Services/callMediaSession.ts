import { Room, RoomEvent, type RemoteParticipant, type RemoteTrack, type RemoteTrackPublication, type Track } from 'livekit-client';

import type { RtcParticipantAccess } from './chatCalls';

export class CallMediaSession {
    readonly room = new Room({ adaptiveStream: true, dynacast: true });

    async connect(access: RtcParticipantAccess, media: { camera: boolean; microphone: boolean }): Promise<void> {
        await this.room.connect(access.serverUrl, access.participantToken);
        await this.room.localParticipant.setMicrophoneEnabled(media.microphone);
        await this.room.localParticipant.setCameraEnabled(media.camera);
    }

    async camera(enabled: boolean): Promise<void> {
        await this.room.localParticipant.setCameraEnabled(enabled);
    }

    async microphone(enabled: boolean): Promise<void> {
        await this.room.localParticipant.setMicrophoneEnabled(enabled);
    }

    async screenShare(enabled: boolean): Promise<void> {
        await this.room.localParticipant.setScreenShareEnabled(enabled);
    }

    async switchDevice(kind: MediaDeviceKind, deviceId: string): Promise<void> {
        await this.room.switchActiveDevice(kind, deviceId);
    }

    onTrackSubscribed(callback: (element: HTMLMediaElement, participantIdentity: string, source: Track.Source) => void): void {
        this.room.on(
            RoomEvent.TrackSubscribed,
            (track: RemoteTrack, _publication: RemoteTrackPublication, participant: RemoteParticipant) => {
                callback(track.attach(), participant.identity, track.source);
            },
        );
    }

    disconnect(): void {
        this.room.disconnect();
    }
}
