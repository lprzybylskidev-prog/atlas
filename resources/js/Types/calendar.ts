export type CalendarView = 'month' | 'week' | 'day' | 'agenda';

export interface CalendarOccurrence {
    eventPublicId: string;
    occurrenceDate: string;
    title: string;
    description: string | null;
    startsAt: string;
    endsAt: string;
    allDay: boolean;
    location: string | null;
    availability: 'busy' | 'free';
    recurring: boolean;
    recurrenceFrequency: 'daily' | 'weekly' | 'monthly' | null;
    recurrenceInterval: number;
    recurrenceWeekdays: number[];
    recurrenceEndsOn: string | null;
    recurrenceCount: number | null;
    reminderMinutes: number[];
    version: number;
    source: string;
    kind: string | null;
    mode: 'online' | 'in_person' | 'hybrid' | null;
    deepLinkUrl: string | null;
    cancelled: boolean;
    editable: boolean;
}

export interface CalendarPreference {
    defaultReminderMinutes: number;
    emailEnabled: boolean;
}
