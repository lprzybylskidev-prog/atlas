import { router } from '@inertiajs/vue3';

import { useToast } from '../Composables/useToast';

interface RealtimeEvent {
    publicId: string;
    topic: string;
    eventType: string;
    teamPublicId: string | null;
    payload: Record<string, unknown>;
    createdAt: string;
}

interface RealtimeResponse {
    events?: RealtimeEvent[];
}

const STORAGE_KEY = 'atlas.realtime.lastEventPublicId';
const INITIAL_POLL_DELAY_MS = 1000;
const POLL_INTERVAL_MS = 5000;
let registered = false;
let timer: number | undefined;

export function registerRealtimeEvents(): void {
    if (registered || typeof window === 'undefined') {
        return;
    }

    registered = true;
    timer = window.setTimeout(pollRealtimeEvents, INITIAL_POLL_DELAY_MS);

    window.addEventListener('beforeunload', () => {
        if (timer !== undefined) {
            window.clearTimeout(timer);
        }
    });
}

async function pollRealtimeEvents(): Promise<void> {
    try {
        const params = new URLSearchParams();
        const lastEventPublicId = window.sessionStorage.getItem(STORAGE_KEY);

        if (lastEventPublicId !== null && lastEventPublicId !== '') {
            params.set('after', lastEventPublicId);
        }

        const response = await fetch(`/realtime/events?${params.toString()}`, {
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
            },
        });

        if (response.ok) {
            const payload = (await response.json()) as RealtimeResponse;
            handleRealtimeEvents(payload.events ?? []);
        }
    } catch {
        // Network handling owns user-facing connectivity messages.
    } finally {
        timer = window.setTimeout(pollRealtimeEvents, POLL_INTERVAL_MS);
    }
}

function handleRealtimeEvents(events: RealtimeEvent[]): void {
    if (events.length === 0) {
        return;
    }

    const toast = useToast();
    let refreshNotifications = false;
    let reloadPage = false;

    for (const event of events) {
        window.sessionStorage.setItem(STORAGE_KEY, event.publicId);

        if (event.eventType === 'notification.created') {
            refreshNotifications = true;
        }

        if (event.eventType === 'system.alert') {
            toast.push({
                type: severityToastType(event.payload.severity),
                message: stringPayload(event.payload.title, 'System alert'),
                description: stringPayload(event.payload.body),
                timeoutMs: event.payload.severity === 'error' ? null : 30000,
                critical: event.payload.severity === 'error',
            });
        }

        if (event.eventType === 'session.invalidated') {
            reloadPage = true;
        }
    }

    if (refreshNotifications) {
        router.reload({ only: notificationReloadProps() });
    }

    if (reloadPage) {
        window.location.reload();
    }
}

function notificationReloadProps(): string[] {
    if (window.location.pathname.startsWith('/notifications')) {
        return ['notifications', 'notificationRows', 'table'];
    }

    return ['notifications'];
}

function severityToastType(value: unknown): 'success' | 'info' | 'warning' | 'error' {
    if (value === 'success') {
        return 'success';
    }

    if (value === 'warning') {
        return 'warning';
    }

    if (value === 'error' || value === 'danger') {
        return 'error';
    }

    return 'info';
}

function stringPayload(value: unknown, fallback?: string): string | undefined {
    return typeof value === 'string' && value.trim() !== '' ? value : fallback;
}
