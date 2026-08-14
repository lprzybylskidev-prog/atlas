import { describe, expect, it } from 'vitest';

import { mergeChatMessages, type ChatRealtimeMessage } from './chatRealtime';

function message(publicId: string, createdAt: string, body = publicId): ChatRealtimeMessage {
    return { publicId, authorPublicId: 'user', body, renderedHtml: body, createdAt };
}

describe('Chat realtime reconciliation', () => {
    it('deduplicates websocket and reconnect messages by public identifier', () => {
        const existing = message('01J00000000000000000000001', '2026-08-14T10:00:00+00:00');
        const replacement = message('01J00000000000000000000001', '2026-08-14T10:00:00+00:00', 'authoritative');
        const missed = message('01J00000000000000000000002', '2026-08-14T10:01:00+00:00');

        expect(mergeChatMessages([existing], [replacement, missed])).toEqual([replacement, missed]);
    });

    it('keeps authoritative chronological ordering after reconnect', () => {
        const later = message('later', '2026-08-14T10:02:00+00:00');
        const earlier = message('earlier', '2026-08-14T10:01:00+00:00');

        expect(mergeChatMessages([later], [earlier]).map(({ publicId }) => publicId)).toEqual(['earlier', 'later']);
    });
});
