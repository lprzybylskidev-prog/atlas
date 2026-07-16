import { readonly, ref } from 'vue';

import type { TranslationKey } from '../Localization/catalog';

export type ToastType = 'success' | 'info' | 'warning' | 'error';

export interface ToastMessage {
    id: string;
    type: ToastType;
    key?: TranslationKey;
    message?: string;
    description?: string | null;
    timeoutMs: number | null;
    createdAt: number;
}

const messages = ref<ToastMessage[]>([]);

const defaultTimeouts: Record<ToastType, number | null> = {
    success: 30000,
    info: 30000,
    warning: 30000,
    error: null,
};

export function useToast() {
    function push(message: Omit<ToastMessage, 'id' | 'createdAt' | 'timeoutMs'> & { timeoutMs?: number | null }): string {
        const id = crypto.randomUUID();
        const timeoutMs = message.timeoutMs === undefined ? defaultTimeouts[message.type] : message.timeoutMs;

        messages.value = [
            ...messages.value,
            {
                ...message,
                id,
                timeoutMs,
                createdAt: Date.now(),
            },
        ];

        if (timeoutMs !== null) {
            window.setTimeout(() => dismiss(id), timeoutMs);
        }

        return id;
    }

    function success(key: TranslationKey, description?: string | null): string {
        return push({ type: 'success', key, description });
    }

    function info(key: TranslationKey, description?: string | null): string {
        return push({ type: 'info', key, description });
    }

    function warning(key: TranslationKey, description?: string | null, timeoutMs: number | null = defaultTimeouts.warning): string {
        return push({ type: 'warning', key, description, timeoutMs });
    }

    function error(key: TranslationKey, description?: string | null, timeoutMs: number | null = defaultTimeouts.error): string {
        return push({ type: 'error', key, description, timeoutMs });
    }

    function dismiss(id: string): void {
        messages.value = messages.value.filter((message) => message.id !== id);
    }

    function clear(): void {
        messages.value = [];
    }

    return {
        messages: readonly(messages),
        push,
        success,
        info,
        warning,
        error,
        dismiss,
        clear,
    };
}
