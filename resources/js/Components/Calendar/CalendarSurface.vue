<script setup lang="ts">
import { IconCalendarOff, IconClock, IconMapPin, IconRepeat } from '@tabler/icons-vue';
import { computed } from 'vue';

import UiState from '../UiState.vue';
import { useTranslator } from '../../Localization/translator';
import type { CalendarOccurrence, CalendarView } from '../../Types/calendar';

const props = defineProps<{
    view: CalendarView;
    selectedDate: string;
    rangeStartsAt: string;
    events: CalendarOccurrence[];
}>();

const emit = defineEmits<{
    selectEvent: [event: CalendarOccurrence];
    createOnDate: [date: string];
}>();

const { locale, t } = useTranslator();
const rangeStartDate = computed(() => props.rangeStartsAt.slice(0, 10));
const visibleDays = computed(() => {
    const count = props.view === 'month' ? 42 : props.view === 'week' ? 7 : 1;
    const start = props.view === 'month' || props.view === 'week' ? rangeStartDate.value : props.selectedDate;

    return Array.from({ length: count }, (_, index) => addDays(start, index));
});
const agendaGroups = computed(() => {
    const groups = new Map<string, CalendarOccurrence[]>();

    props.events.forEach((event) => {
        const existing = groups.get(event.occurrenceDate) ?? [];
        existing.push(event);
        groups.set(event.occurrenceDate, existing);
    });

    return [...groups.entries()];
});

function eventsForDate(date: string): CalendarOccurrence[] {
    return props.events.filter((event) => event.occurrenceDate === date);
}

function addDays(value: string, amount: number): string {
    const [year, month, day] = value.split('-').map(Number);
    const date = new Date(year, month - 1, day);
    date.setDate(date.getDate() + amount);

    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function dateLabel(value: string, style: 'short' | 'long' = 'short'): string {
    const [year, month, day] = value.split('-').map(Number);

    return new Intl.DateTimeFormat(locale.value, {
        weekday: style === 'long' ? 'long' : 'short',
        day: 'numeric',
        month: style === 'long' ? 'long' : 'short',
        year: style === 'long' ? 'numeric' : undefined,
    }).format(new Date(year, month - 1, day));
}

function timeLabel(event: CalendarOccurrence): string {
    if (event.allDay) {
        return t('pages.calendar.event.all_day');
    }

    return `${event.startsAt.slice(11, 16)}–${event.endsAt.slice(11, 16)}`;
}
</script>

<template>
    <div v-if="view === 'month'" class="overflow-x-auto" data-testid="calendar-month-view">
        <div class="min-w-175">
            <div class="grid grid-cols-7 border-b border-zinc-200 dark:border-zinc-800">
                <div
                    v-for="day in visibleDays.slice(0, 7)"
                    :key="`weekday-${day}`"
                    class="px-2 py-2 text-center text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400"
                >
                    {{ dateLabel(day).split(',')[0] }}
                </div>
            </div>
            <div class="grid grid-cols-7">
                <section
                    v-for="day in visibleDays"
                    :key="day"
                    class="min-h-30 border-b border-r border-zinc-200 p-2 last:border-r-0 dark:border-zinc-800"
                    :class="day.slice(5, 7) === selectedDate.slice(5, 7) ? '' : 'bg-zinc-50/70 dark:bg-zinc-900/40'"
                >
                    <button
                        type="button"
                        class="mb-2 inline-flex h-7 min-w-7 items-center justify-center rounded-md text-xs font-semibold text-zinc-600 hover:bg-teal-50 hover:text-teal-800 dark:text-zinc-300 dark:hover:bg-teal-950 dark:hover:text-teal-200"
                        :aria-label="t('pages.calendar.actions.create_on_date', { date: day })"
                        @click="emit('createOnDate', day)"
                    >
                        {{ Number(day.slice(8, 10)) }}
                    </button>
                    <div class="space-y-1">
                        <button
                            v-for="event in eventsForDate(day)"
                            :key="`${event.eventPublicId}-${event.occurrenceDate}`"
                            type="button"
                            class="block w-full rounded-md border border-teal-200 bg-teal-50 px-2 py-1 text-left text-xs text-teal-950 transition hover:border-teal-400 dark:border-teal-900 dark:bg-teal-950/60 dark:text-teal-100"
                            @click="emit('selectEvent', event)"
                        >
                            <span class="block truncate font-semibold">{{ event.title }}</span>
                            <span class="block text-teal-700 dark:text-teal-300">{{ timeLabel(event) }}</span>
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <div v-else-if="view === 'week'" class="grid gap-3 md:grid-cols-2 xl:grid-cols-7" data-testid="calendar-week-view">
        <section
            v-for="day in visibleDays"
            :key="day"
            class="min-h-44 rounded-lg border border-zinc-200 bg-white p-3 dark:border-zinc-800 dark:bg-zinc-950"
        >
            <button
                type="button"
                class="mb-3 text-left text-sm font-semibold text-zinc-800 hover:text-teal-700 dark:text-zinc-100"
                @click="emit('createOnDate', day)"
            >
                {{ dateLabel(day) }}
            </button>
            <div class="space-y-2">
                <button
                    v-for="event in eventsForDate(day)"
                    :key="`${event.eventPublicId}-${event.occurrenceDate}`"
                    type="button"
                    class="w-full rounded-lg border border-zinc-200 p-2 text-left text-sm hover:border-teal-400 dark:border-zinc-800"
                    @click="emit('selectEvent', event)"
                >
                    <span class="block font-semibold text-zinc-900 dark:text-zinc-50">{{ event.title }}</span>
                    <span class="mt-1 flex items-center gap-1 text-xs text-zinc-500 dark:text-zinc-400">
                        <IconClock class="h-3.5 w-3.5" />
                        {{ timeLabel(event) }}
                    </span>
                </button>
            </div>
        </section>
    </div>

    <div v-else-if="view === 'day'" data-testid="calendar-day-view">
        <div v-if="events.length" class="space-y-3">
            <button
                v-for="event in events"
                :key="`${event.eventPublicId}-${event.occurrenceDate}`"
                type="button"
                class="w-full rounded-lg border border-zinc-200 bg-white p-4 text-left transition hover:border-teal-400 dark:border-zinc-800 dark:bg-zinc-950"
                @click="emit('selectEvent', event)"
            >
                <span class="flex flex-wrap items-center justify-between gap-2">
                    <strong class="text-zinc-950 dark:text-zinc-50">{{ event.title }}</strong>
                    <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ timeLabel(event) }}</span>
                </span>
                <span v-if="event.location" class="mt-2 flex items-center gap-1 text-sm text-zinc-600 dark:text-zinc-300">
                    <IconMapPin class="h-4 w-4" />
                    {{ event.location }}
                </span>
                <span v-if="event.recurring" class="mt-2 flex items-center gap-1 text-xs text-teal-700 dark:text-teal-300">
                    <IconRepeat class="h-4 w-4" />
                    {{ t('pages.calendar.event.recurring') }}
                </span>
            </button>
        </div>
        <UiState
            v-else
            variant="empty"
            :icon="IconCalendarOff"
            :title="t('pages.calendar.empty.day_title')"
            :description="t('pages.calendar.empty.day_description')"
        />
    </div>

    <div v-else data-testid="calendar-agenda-view">
        <div v-if="agendaGroups.length" class="space-y-5">
            <section v-for="[date, dateEvents] in agendaGroups" :key="date">
                <h2 class="mb-2 text-sm font-semibold text-zinc-700 dark:text-zinc-200">{{ dateLabel(date, 'long') }}</h2>
                <div class="space-y-2">
                    <button
                        v-for="event in dateEvents"
                        :key="`${event.eventPublicId}-${event.occurrenceDate}`"
                        type="button"
                        class="flex w-full flex-wrap items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-white px-4 py-3 text-left hover:border-teal-400 dark:border-zinc-800 dark:bg-zinc-950"
                        @click="emit('selectEvent', event)"
                    >
                        <span>
                            <strong class="block text-zinc-950 dark:text-zinc-50">{{ event.title }}</strong>
                            <span v-if="event.location" class="mt-1 block text-sm text-zinc-500 dark:text-zinc-400">{{
                                event.location
                            }}</span>
                        </span>
                        <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ timeLabel(event) }}</span>
                    </button>
                </div>
            </section>
        </div>
        <UiState
            v-else
            variant="empty"
            :icon="IconCalendarOff"
            :title="t('pages.calendar.empty.agenda_title')"
            :description="t('pages.calendar.empty.agenda_description')"
        />
    </div>
</template>
