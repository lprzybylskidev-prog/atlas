import { useToast } from '../Composables/useToast';
import type { TranslationKey } from '../Localization/catalog';

const SAFE_RETRY_METHODS = new Set(['GET', 'HEAD', 'OPTIONS']);
let registered = false;

export type NetworkStatus = 401 | 403 | 419 | 422 | 429 | 500;

export function canRetryRequest(method: string): boolean {
    return SAFE_RETRY_METHODS.has(method.toUpperCase());
}

export function handledNetworkStatus(status: number): status is NetworkStatus {
    return status === 401 || status === 403 || status === 419 || status === 422 || status === 429 || status >= 500;
}

export function networkMessage(status: NetworkStatus): TranslationKey {
    if (status === 401) {
        return 'network.status.401';
    }

    if (status === 403) {
        return 'network.status.403';
    }

    if (status === 419) {
        return 'network.status.419';
    }

    if (status === 422) {
        return 'network.status.422';
    }

    if (status === 429) {
        return 'network.status.429';
    }

    return 'network.status.server_error';
}

export class NetworkRequestError extends Error {
    public constructor(
        public readonly status: number,
        message: string,
    ) {
        super(message);
        this.name = 'NetworkRequestError';
    }
}

export async function requestJson<T>(url: string, init: RequestInit = {}): Promise<T> {
    const method = (init.method ?? 'GET').toUpperCase();
    const attempts = canRetryRequest(method) ? 2 : 1;
    let lastError: unknown;

    for (let attempt = 0; attempt < attempts; attempt += 1) {
        try {
            const response = await fetch(url, {
                ...init,
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    ...init.headers,
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                throw new NetworkRequestError(response.status, `Request failed with HTTP ${response.status}.`);
            }

            if (!response.headers.get('content-type')?.includes('application/json')) {
                throw new NetworkRequestError(response.status, 'Expected a JSON response.');
            }

            return (await response.json()) as T;
        } catch (error) {
            lastError = error;
        }
    }

    throw lastError instanceof Error ? lastError : new Error('Unexpected browser request failure.');
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
            key: 'network.offline.title',
            descriptionKey: 'network.offline.description',
            timeoutMs: null,
            critical: true,
        });
    });

    window.addEventListener('online', () => {
        toast.push({
            type: 'info',
            key: 'network.back_online.title',
            descriptionKey: 'network.back_online.description',
        });
    });

    document.addEventListener('inertia:invalid', (event) => {
        const response = responseFromEvent(event);

        if (response !== null && handledNetworkStatus(response.status)) {
            toast.push({
                type: response.status >= 500 ? 'error' : 'warning',
                key: networkMessage(response.status),
                timeoutMs: response.status >= 500 ? null : 30000,
                critical: response.status >= 500,
            });
        }
    });

    document.addEventListener('inertia:exception', (event) => {
        const message = exceptionMessageFromEvent(event);

        toast.push({
            type: 'error',
            key: 'network.exception.title',
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
