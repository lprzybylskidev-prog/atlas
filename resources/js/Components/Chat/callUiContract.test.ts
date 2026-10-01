import { describe, expect, it } from 'vitest';

const components = import.meta.glob('./Call*.vue', {
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
        expect(overlay).toContain('data-testid="call-preflight"');
        expect(overlay).toContain('data-testid="team-call-available"');
        expect(overlay).toContain('calls.actions.screen_share_on');
        expect(overlay).not.toMatch(/record(ing)?/i);
    });

    it('requests browser media only inside an explicit Call preparation flow', () => {
        const overlay = components['./CallOverlay.vue'] ?? '';
        const launcher = components['./CallLauncher.vue'] ?? '';

        expect(launcher).toContain('atlas:call-prepare');
        expect(overlay).toContain('navigator.mediaDevices.getUserMedia');
        expect(overlay.indexOf('getUserMedia')).toBeGreaterThan(overlay.indexOf('prepareDevices'));
    });
});
