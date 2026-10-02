import { describe, expect, it } from 'vitest';

const components = import.meta.glob('./{Call,Media,Global,Meeting}*.vue', {
    eager: true,
    import: 'default',
    query: '?raw',
}) as Record<string, string>;

describe('Call UI contract', () => {
    it('keeps explicit incoming choices, pre-call controls, rejoin, and quiet Team behavior visible', () => {
        const overlay = components['./CallOverlay.vue'] ?? '';

        expect(overlay).toContain('calls.actions.answer_audio');
        expect(overlay).toContain('calls.actions.answer_video');
        expect(overlay).toContain('calls.actions.decline');
        expect(overlay).toContain('test-id="call-preflight"');
        expect(overlay).toContain('data-testid="team-call-available"');
        expect(overlay).toContain('calls.actions.screen_share_on');
        expect(overlay).not.toMatch(/record(ing)?/i);
    });

    it('requests browser media only inside an explicit Call preparation flow', () => {
        const overlay = components['./CallOverlay.vue'] ?? '';
        const setup = components['./MediaDeviceSetup.vue'] ?? '';
        const launcher = components['./CallLauncher.vue'] ?? '';

        expect(launcher).toContain('atlas:call-prepare');
        expect(overlay).toContain('<MediaDeviceSetup');
        expect(setup).toContain('navigator.mediaDevices.getUserMedia');
        expect(setup.indexOf('getUserMedia')).toBeGreaterThan(setup.indexOf('async function prepare'));
        expect(setup).not.toMatch(/onMounted\([^)]*prepare/);
    });

    it('keeps global Call and minimized Meeting controls outside replaceable page layouts', () => {
        const runtime = components['./GlobalChatRuntime.vue'] ?? '';
        const meetingRuntime = components['./MeetingLiveRuntime.vue'] ?? '';
        const minimized = components['./MeetingMinimizedControls.vue'] ?? '';

        expect(runtime).toContain('<CallOverlay');
        expect(runtime).toContain('<MeetingLiveRuntime');
        expect(meetingRuntime).toContain('meetingLiveActive && meetingLiveMinimized');
        expect(minimized).toContain('data-testid="minimized-meeting-session"');
        expect(minimized).toContain('meetings.recording.indicator.paused');
    });
});
