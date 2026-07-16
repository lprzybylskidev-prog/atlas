import { computed, isRef, onBeforeUnmount, onMounted, ref, type Ref } from 'vue';

export interface FieldErrorBag {
    [field: string]: string | undefined;
}

export function useFieldError(errors: Ref<FieldErrorBag> | FieldErrorBag) {
    function fieldError(field: string): string | undefined {
        return isRef(errors) ? errors.value[field] : errors[field];
    }

    return { fieldError };
}

export function useDoubleSubmitGuard(processing: Ref<boolean>) {
    const submitted = ref(false);
    const blocked = computed(() => processing.value || submitted.value);

    async function run(action: () => void | Promise<void>): Promise<void> {
        if (blocked.value) {
            return;
        }

        submitted.value = true;

        try {
            await action();
        } finally {
            if (!processing.value) {
                submitted.value = false;
            }
        }
    }

    function resetSubmitGuard(): void {
        submitted.value = false;
    }

    return { blocked, resetSubmitGuard, run };
}

export function useUnsavedChanges(isDirty: Ref<boolean>, message: string) {
    function beforeUnload(event: BeforeUnloadEvent): void {
        if (!isDirty.value) {
            return;
        }

        event.preventDefault();
        event.returnValue = message;
    }

    onMounted(() => {
        window.addEventListener('beforeunload', beforeUnload);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('beforeunload', beforeUnload);
    });

    return {
        hasUnsavedChanges: computed(() => isDirty.value),
    };
}
