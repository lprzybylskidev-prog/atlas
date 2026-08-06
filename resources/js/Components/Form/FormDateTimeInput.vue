<script setup lang="ts">
import { IconCalendarTime } from '@tabler/icons-vue';
import { computed } from 'vue';

import FormDateInput from './FormDateInput.vue';
import FormInput from './FormInput.vue';
import { useTranslator } from '../../Localization/translator';

const model = defineModel<string>({ required: true });

defineProps<{
    label?: string;
    ariaLabel?: string;
    id?: string;
    error?: string;
}>();

const { t } = useTranslator();
const dateValue = computed({
    get: () => normalize(model.value).date,
    set: (value: string) => {
        const current = normalize(model.value);
        model.value = serialize(value, current.hour, current.minute, current.second);
    },
});
const hourValue = computed({
    get: () => normalize(model.value).hour,
    set: (value: string) => {
        const current = normalize(model.value);
        model.value = serialize(current.date, value, current.minute, current.second);
    },
});
const minuteValue = computed({
    get: () => normalize(model.value).minute,
    set: (value: string) => {
        const current = normalize(model.value);
        model.value = serialize(current.date, current.hour, value, current.second);
    },
});
const secondValue = computed({
    get: () => normalize(model.value).second,
    set: (value: string) => {
        const current = normalize(model.value);
        model.value = serialize(current.date, current.hour, current.minute, value);
    },
});

function normalize(value: string): { date: string; hour: string; minute: string; second: string } {
    const normalized = value.replace('T', ' ').trim();
    const dateMatch = normalized.match(/\b\d{4}-\d{2}-\d{2}\b/);
    const timeMatch = normalized.match(/\b(\d{1,2}):(\d{1,2})(?::(\d{1,2}))?\b/);

    return {
        date: dateMatch?.[0] ?? '',
        hour: clampPart(timeMatch?.[1] ?? '09', 23),
        minute: clampPart(timeMatch?.[2] ?? '00', 59),
        second: clampPart(timeMatch?.[3] ?? '00', 59),
    };
}

function serialize(date: string, hour: string, minute: string, second: string): string {
    if (date === '') {
        return '';
    }

    const normalizedHour = clampPart(hour, 23);
    const normalizedMinute = clampPart(minute, 59);
    const normalizedSecond = clampPart(second, 59);
    const offset = timezoneOffset(date, normalizedHour, normalizedMinute, normalizedSecond);

    return `${date}T${normalizedHour}:${normalizedMinute}:${normalizedSecond}${offset}`;
}

function clampPart(value: string, max: number): string {
    const numeric = Number.parseInt(value, 10);

    if (!Number.isFinite(numeric)) {
        return '00';
    }

    return String(Math.min(Math.max(numeric, 0), max)).padStart(2, '0');
}

function timezoneOffset(date: string, hour: string, minute: string, second: string): string {
    const [year, month, day] = date.split('-').map(Number);
    const local = new Date(year, month - 1, day, Number(hour), Number(minute), Number(second));
    const offsetMinutes = -local.getTimezoneOffset();
    const sign = offsetMinutes >= 0 ? '+' : '-';
    const absolute = Math.abs(offsetMinutes);
    const offsetHour = String(Math.floor(absolute / 60)).padStart(2, '0');
    const offsetMinute = String(absolute % 60).padStart(2, '0');

    return `${sign}${offsetHour}:${offsetMinute}`;
}
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_6rem_6rem_6rem]">
        <FormDateInput :id="id" v-model="dateValue" :label="label" :aria-label="ariaLabel" :error="error" />
        <FormInput
            v-model="hourValue"
            :label="t('form.datetime.hour')"
            placeholder="HH"
            inputmode="numeric"
            :leading-icon="IconCalendarTime"
        />
        <FormInput v-model="minuteValue" :label="t('form.datetime.minute')" placeholder="MM" inputmode="numeric" />
        <FormInput v-model="secondValue" :label="t('form.datetime.second')" placeholder="SS" inputmode="numeric" />
    </div>
</template>
