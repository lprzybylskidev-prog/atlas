import { describe, expect, it } from 'vitest';
import source from './Show.vue?raw';

describe('Meeting transcription UI contract', () => {
    it('gates the complete transcript surface behind provider and retained-recording eligibility', () => {
        expect(source).toContain('v-if="props.transcription?.eligible && recording.ready"');
        expect(source).toContain("meeting.mode !== 'in_person' && recording.publicId");
        expect(source).toContain('meetings.transcription.actions.create');
        expect(source).not.toContain('Transcription is not configured');
    });

    it('supports queued status, editing, version history, sharing, and revocation', () => {
        expect(source).toContain('/transcription`');
        expect(source).toContain('expected_version: transcription.value.version');
        expect(source).toContain('transcription.history');
        expect(source).toContain('/shares/${share}`');
    });
});
