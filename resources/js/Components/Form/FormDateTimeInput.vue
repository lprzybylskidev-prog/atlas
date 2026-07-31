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
        model.value = `${value} ${current.time}`;
    },
});
const timeValue = computed({
    get: () => normalize(model.value).time,
    set: (value: string) => {
        const current = normalize(model.value);
        model.value = `${current.date} ${value}`;
    },
});

function normalize(value: string): { date: string; time: string } {
    const normalized = value.replace('T', ' ').trim();
    const dateMatch = normalized.match(/\b\d{4}-\d{2}-\d{2}\b/);
    const timeMatch = normalized.match(/\b\d{2}:\d{2}\b/);

    return {
        date: dateMatch?.[0] ?? '',
        time: timeMatch?.[0] ?? '09:00',
    };
}
</script>

<template>
    <div class="grid gap-3 sm:grid-cols-[minmax(0,1fr)_8rem]">
        <FormDateInput :id="id" v-model="dateValue" :label="label" :aria-label="ariaLabel" :error="error" />
        <FormInput
            v-model="timeValue"
            :label="t('form.datetime.time')"
            placeholder="HH:mm"
            inputmode="numeric"
            :leading-icon="IconCalendarTime"
        />
    </div>
</template>
