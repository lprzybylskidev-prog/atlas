import { Room, RoomEvent, Track, type RemoteParticipant, type RemoteTrack, type RemoteTrackPublication } from 'livekit-client';

import '../css/recording-template.css';

const rootElement = document.querySelector<HTMLElement>('#atlas-recording');
const screenElement = document.querySelector<HTMLElement>('#screen');
const participantElement = document.querySelector<HTMLElement>('#participants');
const query = new URLSearchParams(window.location.search);
const serverUrl = query.get('url');
const token = query.get('token');

if (!rootElement || !screenElement || !participantElement || !serverUrl || !token) {
    throw new Error('Atlas recording template requires LiveKit room credentials.');
}

const root = rootElement;
const screen = screenElement;
const participants = participantElement;

const room = new Room({ adaptiveStream: false, dynacast: false });
const participantTiles = new Map<string, HTMLElement>();

function tile(participant: RemoteParticipant): HTMLElement {
    const existing = participantTiles.get(participant.identity);
    if (existing) return existing;
    const element = document.createElement('article');
    element.className = 'participant';
    element.dataset.identity = participant.identity;
    const label = document.createElement('span');
    label.textContent = participant.name || participant.identity;
    element.appendChild(label);
    participantTiles.set(participant.identity, element);
    participants.appendChild(element);
    return element;
}

function attach(track: RemoteTrack, publication: RemoteTrackPublication, participant: RemoteParticipant): void {
    const elements = track.attach();
    const media = Array.isArray(elements) ? elements : [elements];
    if (publication.source === Track.Source.ScreenShare) {
        for (const element of media) screen.appendChild(element);
        root?.classList.add('has-screen-share');
        return;
    }
    const target = tile(participant);
    for (const element of media) {
        if (element instanceof HTMLAudioElement) {
            element.className = 'participant-audio';
        }
        target.appendChild(element);
    }
}

room.on(RoomEvent.TrackSubscribed, attach);
room.on(RoomEvent.TrackUnsubscribed, (track) => {
    track.detach().forEach((element) => element.remove());
    if (screen.childElementCount === 0) root.classList.remove('has-screen-share');
});
room.on(RoomEvent.ParticipantDisconnected, (participant) => {
    participantTiles.get(participant.identity)?.remove();
    participantTiles.delete(participant.identity);
});
room.on(RoomEvent.ActiveSpeakersChanged, (speakers) => {
    const active = new Set(speakers.map((speaker) => speaker.identity));
    for (const [identity, element] of participantTiles) element.classList.toggle('active-speaker', active.has(identity));
});

void room.connect(serverUrl, token, { autoSubscribe: true });
