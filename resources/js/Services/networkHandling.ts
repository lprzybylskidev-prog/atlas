import { useToast } from '../Composables/useToast';

const SAFE_RETRY_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);
let registered = false;

export type NetworkStatus = 401 | 403 | 419 | 422 | 429 | 500;

export function canRetryRequest(method: string): boolean {
    return SAFE_RETRY_METHODS.has(method.toUpperCase());
}

export function handledNetworkStatus(status: number): status is NetworkStatus {
    return status === 401 || status === 403 || status === 419 || status === 422 || status === 429 || status >= 500;
}

export function networkMessage(status: NetworkStatus): string {
    if (status === 401) {
        return 'Your sign-in session ended. Please sign in again.';
    }

    if (status === 403) {
        return 'You are not authorized to perform this action.';
    }

    if (status === 419) {
        return 'The page security token expired. Refresh the page before submitting again.';
    }

    if (status === 422) {
        return 'Some submitted fields need attention.';
    }

    if (status === 429) {
        return 'Too many requests. Please wait before trying again.';
    }

    return 'The server could not complete the request.';
}

export function registerNetworkHandling(): void {
    if (registered) {
        return;
    }

    registered = true;
    const toast = useToast();

    window.addEventListener('offline', () => {
        toast.push({
            type: 'warning',
            message: 'You are offline.',
            description: 'Atlas will not retry unsafe changes automatically.',
            timeoutMs: null,
            critical: true,
        });
    });

    window.addEventListener('online', () => {
        toast.push({
            type: 'info',
            message: 'Back online.',
            description: 'Refresh the current view if any data looks stale.',
        });
    });

    document.addEventListener('inertia:invalid', (event) => {
        const response = responseFromEvent(event);

        if (response !== null && handledNetworkStatus(response.status)) {
            toast.push({
                type: response.status >= 500 ? 'error' : 'warning',
                message: networkMessage(response.status),
                timeoutMs: response.status >= 500 ? null : 30000,
                critical: response.status >= 500,
            });
        }
    });

    document.addEventListener('inertia:exception', (event) => {
        const message = exceptionMessageFromEvent(event);

        toast.push({
            type: 'error',
            message: 'A browser request failed.',
            description: message,
            timeoutMs: null,
            critical: true,
        });
    });
}

function responseFromEvent(event: Event): Response | null {
    if (!('detail' in event) || typeof event.detail !== 'object' || event.detail === null) {
        return null;
    }

    const response = (event.detail as { response?: unknown }).response;

    return response instanceof Response ? response : null;
}

function exceptionMessageFromEvent(event: Event): string {
    if (!('detail' in event) || typeof event.detail !== 'object' || event.detail === null) {
        return 'Unexpected browser request failure.';
    }

    const exception = (event.detail as { exception?: unknown }).exception;

    if (exception instanceof Error) {
        return exception.message;
    }

    return 'Unexpected browser request failure.';
}
