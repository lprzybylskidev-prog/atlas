import { readonly, ref } from 'vue';

import type { TranslationKey } from '../Localization/catalog';

export interface ModalRequest {
    id: string;
    variant: 'confirm' | 'busy';
    titleKey: TranslationKey;
    descriptionKey: TranslationKey;
    confirmKey?: TranslationKey;
    cancelKey?: TranslationKey;
    tone: 'neutral' | 'warning' | 'danger';
}

const activeModal = ref<ModalRequest | null>(null);
let activeResolver: ((confirmed: boolean) => void) | null = null;

export function useModal() {
    function confirm(request: Omit<ModalRequest, 'id' | 'variant'>): Promise<boolean> {
        activeModal.value = {
            ...request,
            id: crypto.randomUUID(),
            variant: 'confirm',
        };

        return new Promise((resolve) => {
            activeResolver = resolve;
        });
    }

    function busy(request: Omit<ModalRequest, 'id' | 'variant' | 'confirmKey' | 'cancelKey' | 'tone'>): () => void {
        activeResolver = null;
        activeModal.value = {
            ...request,
            id: crypto.randomUUID(),
            variant: 'busy',
            tone: 'neutral',
        };

        return () => {
            activeModal.value = null;
        };
    }

    function resolve(confirmed: boolean): void {
        activeResolver?.(confirmed);
        activeResolver = null;
        activeModal.value = null;
    }

    return {
        activeModal: readonly(activeModal),
        busy,
        confirm,
        resolve,
    };
}
