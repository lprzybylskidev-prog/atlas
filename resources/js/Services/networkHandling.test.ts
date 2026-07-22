import { describe, expect, it } from 'vitest';

import { canRetryRequest, handledNetworkStatus, networkMessage } from './networkHandling';

describe('networkHandling', () => {
    it('allows automatic retry only for safe idempotent methods', () => {
        expect(canRetryRequest('GET')).toBe(true);
        expect(canRetryRequest('head')).toBe(true);
        expect(canRetryRequest('POST')).toBe(false);
        expect(canRetryRequest('PATCH')).toBe(false);
        expect(canRetryRequest('DELETE')).toBe(false);
    });

    it('centralizes status codes handled by the session and network layer', () => {
        expect(handledNetworkStatus(401)).toBe(true);
        expect(handledNetworkStatus(403)).toBe(true);
        expect(handledNetworkStatus(419)).toBe(true);
        expect(handledNetworkStatus(422)).toBe(true);
        expect(handledNetworkStatus(429)).toBe(true);
        expect(handledNetworkStatus(500)).toBe(true);
        expect(handledNetworkStatus(404)).toBe(false);
    });

    it('uses explicit translation keys for session expiry and rate limiting', () => {
        expect(networkMessage(419)).toBe('network.status.419');
        expect(networkMessage(429)).toBe('network.status.429');
    });
});
