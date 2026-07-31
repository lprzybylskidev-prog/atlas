<script setup lang="ts">
import { IconCalendarEvent, IconChevronLeft, IconChevronRight } from '@tabler/icons-vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import { useTranslator } from '../../Localization/translator';

const model = defineModel<string>({ required: true });

const props = defineProps<{
    label?: string;
    ariaLabel?: string;
    id?: string;
    error?: string;
}>();

const open = ref(false);
const root = ref<HTMLElement | null>(null);
const monthCursor = ref(firstOfMonth(model.value || todayIso()));
const inputId = props.id ?? `form-date-${crypto.randomUUID()}`;
const errorId = `${inputId}-error`;
const { locale, t } = useTranslator();
const weekdayLabels = computed(() =>
    Array.from({ length: 7 }, (_, index) => {
        const date = new Date(Date.UTC(2026, 0, 5 + index));

        return new Intl.DateTimeFormat(locale.value, { weekday: 'short' }).format(date);
    }),
);

const monthLabel = computed(() =>
    new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric' }).format(parseIsoDate(monthCursor.value)),
);
const calendarDays = computed(() => {
    const first = parseIsoDate(monthCursor.value);
    const offset = (first.getDay() + 6) % 7;
    const start = new Date(first);
    start.setDate(first.getDate() - offset);

    return Array.from({ length: 42 }, (_, index) => {
        const date = new Date(start);
        date.setDate(start.getDate() + index);
        const iso = toIsoDate(date);

        return {
            iso,
            day: String(date.getDate()),
            inMonth: date.getMonth() === first.getMonth(),
            selected: iso === model.value,
            today: iso === todayIso(),
        };
    });
});

function closeOnOutsidePointer(event: PointerEvent): void {
    const target = event.target;

    if (target instanceof Node && !root.value?.contains(target)) {
        open.value = false;
    }
}

function closeOnEscape(event: KeyboardEvent): void {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

function showPicker(): void {
    monthCursor.value = firstOfMonth(isIsoDate(model.value) ? model.value : todayIso());
    open.value = true;
}

function previousMonth(): void {
    const date = parseIsoDate(monthCursor.value);
    date.setMonth(date.getMonth() - 1);
    monthCursor.value = toIsoDate(date).slice(0, 7) + '-01';
}

function nextMonth(): void {
    const date = parseIsoDate(monthCursor.value);
    date.setMonth(date.getMonth() + 1);
    monthCursor.value = toIsoDate(date).slice(0, 7) + '-01';
}

function selectDate(value: string): void {
    model.value = value;
    open.value = false;
}

function todayIso(): string {
    return toIsoDate(new Date());
}

function firstOfMonth(value: string): string {
    return `${value.slice(0, 7)}-01`;
}

function isIsoDate(value: string): boolean {
    return /^\d{4}-\d{2}-\d{2}$/.test(value);
}

function parseIsoDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function toIsoDate(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

onMounted(() => {
    document.addEventListener('pointerdown', closeOnOutsidePointer);
    document.addEventListener('keydown', closeOnEscape);
});

onBeforeUnmount(() => {
    document.removeEventListener('pointerdown', closeOnOutsidePointer);
    document.removeEventListener('keydown', closeOnEscape);
});
</script>

<template>
    <label ref="root" class="relative flex flex-col gap-1" :for="inputId">
        <span v-if="label" class="block text-sm font-medium text-zinc-700 dark:text-zinc-200">{{ label }}</span>
        <span class="relative block">
            <IconCalendarEvent
                aria-hidden="true"
                class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-zinc-400"
                :stroke-width="1.8"
            />
            <input
                :id="inputId"
                v-model="model"
                type="text"
                placeholder="YYYY-MM-DD"
                inputmode="numeric"
                autocomplete="off"
                :aria-label="ariaLabel"
                :aria-invalid="error ? 'true' : 'false'"
                :aria-describedby="error ? errorId : undefined"
                class="h-10 w-full rounded-lg border border-zinc-300 bg-white px-3 pl-9 text-sm text-zinc-950 outline-none transition placeholder:text-zinc-400 focus:border-teal-600 focus:ring-2 focus:ring-teal-100 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-50 dark:placeholder:text-zinc-500 dark:focus:ring-teal-950"
                @focus="showPicker"
                @click="showPicker"
            />
        </span>
        <p v-if="error" :id="errorId" class="mt-1 text-xs text-rose-600 dark:text-rose-300">{{ error }}</p>

        <section
            v-if="open"
            class="absolute top-full left-0 z-50 mt-1.5 w-72 rounded-lg border border-zinc-200 bg-white p-3 shadow-lg shadow-zinc-950/10 dark:border-zinc-800 dark:bg-zinc-950 dark:shadow-black/30"
        >
            <div class="mb-3 flex items-center justify-between gap-2">
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-900"
                    :aria-label="t('form.date.previous_month')"
                    @click="previousMonth"
                >
                    <IconChevronLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                </button>
                <p class="text-sm font-semibold text-zinc-950 dark:text-zinc-50">{{ monthLabel }}</p>
                <button
                    type="button"
                    class="inline-flex h-8 w-8 items-center justify-center rounded-md text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-900"
                    :aria-label="t('form.date.next_month')"
                    @click="nextMonth"
                >
                    <IconChevronRight aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                </button>
            </div>
            <div class="grid grid-cols-7 gap-1 text-center text-xs font-semibold text-zinc-500 dark:text-zinc-400">
                <span v-for="weekday in weekdayLabels" :key="weekday" class="py-1">{{ weekday }}</span>
            </div>
            <div class="mt-1 grid grid-cols-7 gap-1">
                <button
                    v-for="day in calendarDays"
                    :key="day.iso"
                    type="button"
                    class="h-8 rounded-md text-sm transition focus:outline-none focus:ring-2 focus:ring-teal-500"
                    :class="[
                        day.selected
                            ? 'bg-teal-700 text-white hover:bg-teal-800 dark:bg-teal-600 dark:hover:bg-teal-500'
                            : 'text-zinc-800 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-900',
                        !day.inMonth && !day.selected ? 'opacity-40' : '',
                        day.today && !day.selected ? 'ring-1 ring-teal-300 dark:ring-teal-700' : '',
                    ]"
                    @click="selectDate(day.iso)"
                >
                    {{ day.day }}
                </button>
            </div>
        </section>
    </label>
</template>
