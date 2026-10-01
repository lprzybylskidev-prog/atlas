import { afterEach, describe, expect, it, vi } from 'vitest';

import { availableMediaDevices, callPreferences, saveCallPreferences, startCall } from './chatCalls';

afterEach(() => {
    vi.unstubAllGlobals();
    vi.restoreAllMocks();
});

describe('Chat Call browser boundary', () => {
    it('classifies selectable input and output devices without assuming labels are available', async () => {
        const devices = [
            { kind: 'videoinput', deviceId: 'camera' },
            { kind: 'audioinput', deviceId: 'microphone' },
            { kind: 'audiooutput', deviceId: 'speaker' },
        ] as MediaDeviceInfo[];
        vi.stubGlobal('navigator', { mediaDevices: { enumerateDevices: vi.fn().mockResolvedValue(devices) } });

        await expect(availableMediaDevices()).resolves.toEqual({
            cameras: [devices[0]],
            microphones: [devices[1]],
            speakers: [devices[2]],
        });
    });

    it('uses the dedicated preferences endpoint and persists nullable device choices and camera default', async () => {
        const preferences = {
            cameraDeviceId: null,
            microphoneDeviceId: 'microphone',
            speakerDeviceId: null,
            outgoingCameraEnabled: true,
        };
        const fetch = vi
            .fn()
            .mockResolvedValueOnce(new Response(JSON.stringify({ preferences }), { status: 200 }))
            .mockResolvedValueOnce(new Response(JSON.stringify({ preferences }), { status: 200 }));
        vi.stubGlobal('fetch', fetch);
        vi.stubGlobal('document', { cookie: '', querySelector: vi.fn().mockReturnValue(null) });

        await expect(callPreferences()).resolves.toEqual(preferences);
        await expect(saveCallPreferences(preferences)).resolves.toEqual(preferences);
        expect(fetch.mock.calls[0]?.[0]).toBe('/chat/call-preferences');
        expect(fetch.mock.calls[1]?.[0]).toBe('/chat/call-preferences');
        expect(fetch.mock.calls[1]?.[1]).toMatchObject({
            method: 'PATCH',
            body: JSON.stringify({
                camera_device_id: null,
                microphone_device_id: 'microphone',
                speaker_device_id: null,
                outgoing_camera_enabled: true,
            }),
        });
    });

    it('sends an idempotency key and explicit initial camera state when starting a Call', async () => {
        const response = { call: { publicId: 'call-1' }, rtc: null };
        const fetch = vi.fn().mockResolvedValue(new Response(JSON.stringify(response), { status: 201 }));
        vi.stubGlobal('fetch', fetch);
        vi.stubGlobal('document', { cookie: '', querySelector: vi.fn().mockReturnValue(null) });

        await expect(startCall('conversation-1', true, 'request-1')).resolves.toEqual(response);
        expect(fetch).toHaveBeenCalledWith(
            '/chat/conversations/conversation-1/calls',
            expect.objectContaining({
                method: 'POST',
                body: JSON.stringify({ camera_enabled: true, client_request_key: 'request-1' }),
            }),
        );
    });
});
