<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { IconBellCog, IconCalendarEvent, IconChevronLeft, IconChevronRight, IconPlus } from '@tabler/icons-vue';
import { computed, ref, watch } from 'vue';

import CalendarEventDialog from '../../Components/Calendar/CalendarEventDialog.vue';
import CalendarPreferenceDialog from '../../Components/Calendar/CalendarPreferenceDialog.vue';
import CalendarSurface from '../../Components/Calendar/CalendarSurface.vue';
import FormButton from '../../Components/Form/FormButton.vue';
import FormDateInput from '../../Components/Form/FormDateInput.vue';
import PageStack from '../../Components/PageStack.vue';
import SurfaceCard from '../../Components/SurfaceCard.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import { useTranslator } from '../../Localization/translator';
import type { AtlasPageProps } from '../../Types/inertia';
import type { CalendarOccurrence, CalendarPreference, CalendarView } from '../../Types/calendar';

const props = defineProps<{
    view: CalendarView;
    selectedDate: string;
    range: { startsAt: string; endsAt: string };
    events: CalendarOccurrence[];
    preference: CalendarPreference;
}>();

const page = usePage<AtlasPageProps>();
const { locale, t } = useTranslator();
const eventDialogOpen = ref(false);
const preferenceDialogOpen = ref(false);
const selectedEvent = ref<CalendarOccurrence | null>(null);
const draftDate = ref(props.selectedDate);
const dateControl = ref(props.selectedDate);
const availableRoutes = computed(() => page.props.auth.availableApplicationRoutes);
const canCreate = computed(() => availableRoutes.value.includes('calendar.events.store'));
const canUpdate = computed(() => availableRoutes.value.includes('calendar.events.update'));
const canDelete = computed(() => availableRoutes.value.includes('calendar.events.destroy'));
const canUpdatePreference = computed(() => availableRoutes.value.includes('calendar.preferences.update'));
const periodTitle = computed(() => {
    const date = parseDate(props.selectedDate);

    if (props.view === 'month') {
        return new Intl.DateTimeFormat(locale.value, { month: 'long', year: 'numeric' }).format(date);
    }
    if (props.view === 'day') {
        return new Intl.DateTimeFormat(locale.value, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(date);
    }

    return props.view === 'agenda'
        ? t('pages.calendar.view.agenda_range', {
              date: new Intl.DateTimeFormat(locale.value, { day: 'numeric', month: 'long' }).format(date),
          })
        : t('pages.calendar.view.week_range', {
              date: new Intl.DateTimeFormat(locale.value, { day: 'numeric', month: 'long', year: 'numeric' }).format(date),
          });
});
const views = computed<Array<{ value: CalendarView; label: string }>>(() => [
    { value: 'month', label: t('pages.calendar.view.month') },
    { value: 'week', label: t('pages.calendar.view.week') },
    { value: 'day', label: t('pages.calendar.view.day') },
    { value: 'agenda', label: t('pages.calendar.view.agenda') },
]);

watch(
    () => props.selectedDate,
    (value) => {
        dateControl.value = value;
    },
);

function navigate(view: CalendarView, date: string): void {
    router.get('/calendar', { view, date }, { preserveState: true, preserveScroll: true, replace: true });
}

function shift(direction: -1 | 1): void {
    const date = parseDate(props.selectedDate);

    if (props.view === 'month') {
        date.setDate(1);
        date.setMonth(date.getMonth() + direction);
    } else if (props.view === 'week') {
        date.setDate(date.getDate() + direction * 7);
    } else if (props.view === 'agenda') {
        date.setDate(date.getDate() + direction * 30);
    } else {
        date.setDate(date.getDate() + direction);
    }

    navigate(props.view, isoDate(date));
}

function selectDate(value: string): void {
    dateControl.value = value;
    navigate(props.view, value);
}

function openCreate(date = props.selectedDate): void {
    selectedEvent.value = null;
    draftDate.value = date;
    eventDialogOpen.value = true;
}

function openEvent(event: CalendarOccurrence): void {
    selectedEvent.value = event;
    draftDate.value = event.occurrenceDate;
    eventDialogOpen.value = true;
}

function parseDate(value: string): Date {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function isoDate(date: Date): string {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;
}

function today(): string {
    return isoDate(new Date());
}
</script>

<template>
    <Head :title="t('pages.calendar.title')" />
    <AppLayout :title="t('pages.calendar.title')" :title-icon="IconCalendarEvent" mode="app">
        <PageStack>
            <SurfaceCard :title="periodTitle" :subtitle="t('pages.calendar.subtitle')" :icon="IconCalendarEvent" :padded="false">
                <template #actions>
                    <FormButton v-if="canUpdatePreference" tone="neutral" :icon="IconBellCog" @click="preferenceDialogOpen = true">
                        {{ t('pages.calendar.actions.preferences') }}
                    </FormButton>
                    <FormButton v-if="canCreate" :icon="IconPlus" @click="openCreate()">
                        {{ t('pages.calendar.actions.create') }}
                    </FormButton>
                </template>

                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-zinc-200 p-4 dark:border-zinc-800">
                    <div class="flex flex-wrap items-center gap-2">
                        <FormButton
                            tone="neutral"
                            :icon="IconChevronLeft"
                            :aria-label="t('pages.calendar.actions.previous')"
                            @click="shift(-1)"
                        >
                            {{ t('pages.calendar.actions.previous') }}
                        </FormButton>
                        <FormButton tone="neutral" @click="navigate(view, today())">{{ t('pages.calendar.actions.today') }}</FormButton>
                        <FormButton tone="neutral" :icon="IconChevronRight" @click="shift(1)">
                            {{ t('pages.calendar.actions.next') }}
                        </FormButton>
                    </div>
                    <FormDateInput
                        :model-value="dateControl"
                        :aria-label="t('pages.calendar.actions.choose_date')"
                        @update:model-value="selectDate"
                    />
                    <div
                        class="inline-flex flex-wrap rounded-lg border border-zinc-200 p-1 dark:border-zinc-800"
                        role="group"
                        :aria-label="t('pages.calendar.view.label')"
                    >
                        <button
                            v-for="option in views"
                            :key="option.value"
                            type="button"
                            class="rounded-md px-3 py-2 text-sm font-medium transition"
                            :class="
                                option.value === view
                                    ? 'bg-teal-700 text-white dark:bg-teal-600'
                                    : 'text-zinc-600 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-900'
                            "
                            :aria-pressed="option.value === view"
                            @click="navigate(option.value, selectedDate)"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                </div>

                <div class="p-4">
                    <CalendarSurface
                        :view="view"
                        :selected-date="selectedDate"
                        :range-starts-at="range.startsAt"
                        :events="events"
                        @select-event="openEvent"
                        @create-on-date="openCreate"
                    />
                </div>
            </SurfaceCard>
        </PageStack>

        <CalendarEventDialog
            :open="eventDialogOpen"
            :event="selectedEvent"
            :initial-date="draftDate"
            :default-reminder-minutes="preference.defaultReminderMinutes"
            :can-create="canCreate"
            :can-update="canUpdate"
            :can-delete="canDelete"
            @close="eventDialogOpen = false"
        />
        <CalendarPreferenceDialog
            :open="preferenceDialogOpen"
            :preference="preference"
            :can-update="canUpdatePreference"
            @close="preferenceDialogOpen = false"
        />
    </AppLayout>
</template>
