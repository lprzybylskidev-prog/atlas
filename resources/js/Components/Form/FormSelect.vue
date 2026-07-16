<script setup lang="ts">
import { IconCheck, IconChevronDown } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export interface FormSelectOption {
    value: string | number;
    label: string;
}

const model = defineModel<string | number>({ required: true });

const props = withDefaults(
    defineProps<{
        label?: string;
        ariaLabel?: string;
        options: FormSelectOption[];
        placeholder?: string;
        error?: string;
        buttonClass?: string;
    }>(),
    {
        label: undefined,
        ariaLabel: undefined,
        placeholder: 'Select',
        error: undefined,
        buttonClass: '',
    },
);

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const button = ref<HTMLButtonElement | null>(null);
const listboxId = `form-select-${crypto.randomUUID()}`;
const errorId = `${listboxId}-error`;

const selectedOption = computed(() => props.options.find((option) => option.value === model.value) ?? null);

function selectOption(option: FormSelectOption): void {
    if (option.value === model.value) {
        open.value = false;
        button.value?.focus();

        return;
    }

    model.value = option.value;
    open.value = false;
    button.value?.focus();
}

function closeOnOutsidePointer(event: PointerEvent): void {
    const target = event.target;

    if (target instanceof Node && !root.value?.contains(target)) {
        open.value = false;
    }
}

function handleButtonKeydown(event: KeyboardEvent): void {
    if (event.key === 'ArrowDown' || event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        open.value = true;
        return;
    }

    if (event.key === 'Escape') {
        open.value = false;
    }
}

function handleOptionKeydown(event: KeyboardEvent, index: number): void {
    if (event.key === 'Escape') {
        event.preventDefault();
        open.value = false;
        button.value?.focus();
        return;
    }

    if (event.key === 'ArrowDown') {
        event.preventDefault();
        const next = root.value?.querySelectorAll<HTMLButtonElement>('[data-select-option]')[index + 1];
        next?.focus();
        return;
    }

    if (event.key === 'ArrowUp') {
        event.preventDefault();
        const previous = root.value?.querySelectorAll<HTMLButtonElement>('[data-select-option]')[index - 1];
        previous?.focus();
    }
}

onMounted(() => {
    document.addEventListener('pointerdown', closeOnOutsidePointer);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeOnOutsidePointer);
});
</script>

<template>
    <div ref="root" class="relative">
        <span v-if="label" class="mb-1 block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ label }}</span>
        <button
            ref="button"
            type="button"
            class="inline-flex h-10 w-full items-center justify-between gap-2 rounded-lg border border-zinc-300 bg-white px-3 text-left text-sm leading-5 text-zinc-950 outline-none transition hover:border-zinc-400 hover:bg-zinc-50 focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:focus:ring-teal-950"
            :class="buttonClass"
            role="combobox"
            :aria-label="ariaLabel ?? label"
            :aria-expanded="open"
            :aria-controls="listboxId"
            :aria-invalid="error ? 'true' : 'false'"
            :aria-describedby="error ? errorId : undefined"
            @click="open = !open"
            @keydown="handleButtonKeydown"
        >
            <span class="flex min-h-5 items-center truncate" :class="{ 'text-zinc-500 dark:text-zinc-400': selectedOption === null }">
                {{ selectedOption?.label ?? placeholder }}
            </span>
            <IconChevronDown
                aria-hidden="true"
                class="h-4 w-4 shrink-0 text-zinc-400 transition"
                :class="{ 'rotate-180': open }"
                :stroke-width="1.8"
            />
        </button>
        <div
            v-if="open"
            :id="listboxId"
            role="listbox"
            class="absolute right-0 z-30 mt-1.5 max-h-64 w-full min-w-max space-y-1 overflow-auto rounded-lg border border-zinc-200 bg-white p-1 shadow-lg shadow-zinc-950/10 dark:border-zinc-800 dark:bg-zinc-950 dark:shadow-black/30"
        >
            <button
                v-for="(option, index) in options"
                :key="`${option.value}`"
                type="button"
                data-select-option
                role="option"
                :aria-selected="option.value === model"
                class="flex h-9 w-full items-center justify-between gap-3 rounded-md px-2 text-left text-sm transition outline-none hover:bg-teal-50 hover:text-teal-900 focus:bg-teal-50 focus:text-teal-900 dark:hover:bg-teal-950 dark:hover:text-teal-100 dark:focus:bg-teal-950 dark:focus:text-teal-100"
                :class="
                    option.value === model
                        ? 'bg-teal-50 text-teal-900 dark:bg-teal-950 dark:text-teal-100'
                        : 'text-zinc-700 dark:text-zinc-200'
                "
                @click="selectOption(option)"
                @keydown="handleOptionKeydown($event, index)"
            >
                <span class="truncate">{{ option.label }}</span>
                <IconCheck v-if="option.value === model" aria-hidden="true" class="h-4 w-4 shrink-0" :stroke-width="1.8" />
            </button>
        </div>
        <p v-if="error" :id="errorId" class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>
    </div>
</template>
