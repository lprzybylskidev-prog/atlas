import { router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch, type Ref } from 'vue';

export interface TimeTrackingActivityConfig {
    enabled: boolean;
    endpoint: string;
    thresholdSeconds: number;
    warningSeconds: number;
}

export interface TimeTrackingActivityState {
    enabled: boolean;
    warningOpen: boolean;
    offline: boolean;
    reconnectOpen: boolean;
    reconnectStatus: 'active' | 'ended' | 'failed' | null;
    countdownSeconds: number;
    workEnded: boolean;
}

const activityEvents = ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart', 'focus'] as const;
const storageKey = 'atlas.time_tracking.activity';
const channelName = 'atlas-time-tracking-activity';

export function useTimeTrackingActivityTracker(config: Ref<TimeTrackingActivityConfig>): TimeTrackingActivityState {
    const state = reactive<TimeTrackingActivityState>({
        enabled: false,
        warningOpen: false,
        offline: false,
        reconnectOpen: false,
        reconnectStatus: null,
        countdownSeconds: config.value.warningSeconds,
        workEnded: false,
    });
    const lastActivityMs = ref(performance.now());
    const lastBroadcastAt = ref(0);
    const offlineStartedMs = ref<number | null>(null);
    const warningRequested = ref(false);
    const requestPending = ref(false);
    const reconnectPending = ref(false);
    const channel = typeof BroadcastChannel === 'undefined' ? null : new BroadcastChannel(channelName);
    let interval: number | undefined;

    const thresholdMs = computed(() => Math.max(1, config.value.thresholdSeconds) * 1000);

    function recordActivity({ broadcast = true }: { broadcast?: boolean } = {}): void {
        if (!state.enabled) {
            return;
        }

        lastActivityMs.value = performance.now();
        warningRequested.value = false;
        requestPending.value = false;
        state.warningOpen = false;
        state.reconnectOpen = false;
        state.reconnectStatus = null;
        state.workEnded = false;
        state.countdownSeconds = config.value.warningSeconds;

        if (!broadcast) {
            return;
        }

        const now = Date.now();

        if (now - lastBroadcastAt.value < 1000) {
            return;
        }

        lastBroadcastAt.value = now;
        channel?.postMessage({ type: 'activity' });
        window.localStorage.setItem(storageKey, String(now));
    }

    function handleBrowserActivity(): void {
        recordActivity();
    }

    function handleCrossTabActivity(): void {
        recordActivity({ broadcast: false });
    }

    async function requestWarning(idleMs: number): Promise<void> {
        if (requestPending.value || warningRequested.value || !state.enabled || state.offline) {
            return;
        }

        requestPending.value = true;
        warningRequested.value = true;

        try {
            const response = await fetch(config.value.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ inactive_ms: Math.floor(idleMs) }),
            });

            if (!response.ok) {
                return;
            }

            const payload = (await response.json()) as { status?: string; workEnded?: boolean; warningSeconds?: number };

            if (payload.status === 'warning' || payload.status === 'ended' || payload.workEnded === true) {
                state.warningOpen = true;
                state.workEnded = payload.workEnded === true;
                state.countdownSeconds = Math.max(1, Number(payload.warningSeconds ?? config.value.warningSeconds));
            }
        } finally {
            requestPending.value = false;
        }
    }

    async function reconcileAfterReconnect(): Promise<void> {
        if (reconnectPending.value || !state.enabled || offlineStartedMs.value === null) {
            return;
        }

        reconnectPending.value = true;
        const offlineElapsedMs = performance.now() - offlineStartedMs.value;
        const idleMs = Math.max(offlineElapsedMs, performance.now() - lastActivityMs.value);

        try {
            const response = await fetch(config.value.endpoint, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ inactive_ms: Math.floor(idleMs) }),
            });

            if (!response.ok) {
                state.reconnectStatus = 'failed';
                state.reconnectOpen = true;

                return;
            }

            const payload = (await response.json()) as { status?: string; workEnded?: boolean; warningSeconds?: number };

            state.reconnectStatus = payload.workEnded === true || payload.status === 'ended' ? 'ended' : 'active';
            state.reconnectOpen = true;

            state.workEnded = state.reconnectStatus === 'ended';
        } catch {
            state.reconnectStatus = 'failed';
            state.reconnectOpen = true;
        } finally {
            offlineStartedMs.value = null;
            reconnectPending.value = false;
        }
    }

    function handleOffline(): void {
        if (!state.enabled) {
            return;
        }

        state.offline = true;
        state.warningOpen = false;
        state.reconnectOpen = false;
        state.reconnectStatus = null;
        offlineStartedMs.value = performance.now();
    }

    function handleOnline(): void {
        if (!state.enabled || !state.offline) {
            return;
        }

        state.offline = false;
        void reconcileAfterReconnect();
    }

    function tick(): void {
        if (!state.enabled || state.offline) {
            return;
        }

        if (state.warningOpen) {
            state.countdownSeconds = Math.max(0, state.countdownSeconds - 1);

            if (state.countdownSeconds === 0) {
                router.post('/logout');
            }

            return;
        }

        const idleMs = performance.now() - lastActivityMs.value;

        if (idleMs >= thresholdMs.value) {
            void requestWarning(idleMs);
        }
    }

    function start(): void {
        state.enabled = config.value.enabled;
        state.countdownSeconds = config.value.warningSeconds;
        lastActivityMs.value = performance.now();
        warningRequested.value = false;
        requestPending.value = false;

        activityEvents.forEach((eventName) => window.addEventListener(eventName, handleBrowserActivity, { passive: true }));
        channel?.addEventListener('message', handleCrossTabActivity);
        window.addEventListener('storage', handleStorage);
        window.addEventListener('offline', handleOffline);
        window.addEventListener('online', handleOnline);
        interval = window.setInterval(tick, 1000);

        if (!navigator.onLine) {
            handleOffline();
        }
    }

    function stop(): void {
        activityEvents.forEach((eventName) => window.removeEventListener(eventName, handleBrowserActivity));
        channel?.removeEventListener('message', handleCrossTabActivity);
        window.removeEventListener('storage', handleStorage);
        window.removeEventListener('offline', handleOffline);
        window.removeEventListener('online', handleOnline);

        if (interval !== undefined) {
            window.clearInterval(interval);
            interval = undefined;
        }

        state.enabled = false;
        state.warningOpen = false;
        state.offline = false;
        state.reconnectOpen = false;
        state.reconnectStatus = null;
        offlineStartedMs.value = null;
        warningRequested.value = false;
        requestPending.value = false;
        reconnectPending.value = false;
    }

    function handleStorage(event: StorageEvent): void {
        if (event.key === storageKey) {
            handleCrossTabActivity();
        }
    }

    watch(
        () => config.value,
        (next) => {
            stop();

            if (next.enabled) {
                start();
            }
        },
        { deep: true },
    );

    onMounted(() => {
        if (config.value.enabled) {
            start();
        }
    });

    onBeforeUnmount(() => {
        stop();
        channel?.close();
    });

    return state;
}
