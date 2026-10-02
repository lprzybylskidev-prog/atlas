import { describe, expect, it } from 'vitest';

const pages = import.meta.glob('./Show.vue', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;

const components = import.meta.glob('../../Components/Chat/MediaDeviceSetup.vue', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;

describe('Meeting pre-call contract', () => {
    it('keeps RTC controls to the server-authorized online and hybrid Meeting modes', () => {
        const page = pages['./Show.vue'] ?? '';

        expect(page).toContain("mode: 'online' | 'in_person' | 'hybrid';");
        expect(page).toContain("props.meeting.canJoinOnline && props.meeting.mode !== 'in_person'");
        expect(page).toContain('if (!canUseRtcSession.value) return');
        expect(page).toContain('@click="openPreCall"');
        expect(page).toContain('v-if="canUseRtcSession"');
        expect(page).toContain('test-id="meeting-preflight"');
        expect(page).not.toContain("disabled>{{ t('meetings.actions.join_online')");
    });

    it('shares preview, device selection, preferences, and cleanup with Calls', () => {
        const page = pages['./Show.vue'] ?? '';
        const setup = components['../../Components/Chat/MediaDeviceSetup.vue'] ?? '';

        expect(page).toContain('<MediaDeviceSetup');
        expect(setup).toContain('calls.devices.camera');
        expect(setup).toContain('calls.devices.microphone');
        expect(setup).toContain('calls.devices.speaker');
        expect(setup).toContain('saveCallPreferences');
        expect(setup).toContain('previewStream.value?.getTracks().forEach((track) => track.stop())');
    });

    it('shows organizer recording controls and a participant-visible recording state only inside RTC Meetings', () => {
        const page = pages['./Show.vue'] ?? '';

        expect(page).toContain('v-if="meeting.canManageRecording && recording.status === \'not_recording\'"');
        expect(page).toContain('@click="controlRecording(\'start\')"');
        expect(page).toContain('@click="controlRecording(\'pause\')"');
        expect(page).toContain('@click="controlRecording(\'resume\')"');
        expect(page).toContain('@click="controlRecording(\'stop\')"');
        expect(page).toContain('data-testid="meeting-recording-state"');
        expect(page).toContain('aria-live="polite"');
        expect(page).toContain("meeting.mode !== 'in_person' && recording.publicId");
        expect(page).toContain('v-if="canUseRtcSession"');
    });
});
