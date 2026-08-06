<script setup lang="ts">
import { IconArrowLeft, IconDeviceFloppy, IconUsersGroup } from '@tabler/icons-vue';

import ActionLink from '../ActionLink.vue';
import AtlasForm from '../Form/AtlasForm.vue';
import FormButton from '../Form/FormButton.vue';
import FormInput from '../Form/FormInput.vue';
import FormActions from '../FormActions.vue';
import SurfaceCard from '../SurfaceCard.vue';
import { useTranslator } from '../../Localization/translator';

const name = defineModel<string>('name', { required: true });
const displayName = defineModel<string>('displayName', { required: true });
const inactivityTimeoutMinutes = defineModel<string>('inactivityTimeoutMinutes', { required: true });
const sessionMaxLifetimeMinutes = defineModel<string>('sessionMaxLifetimeMinutes', { required: true });
const breakDailyLimitMinutes = defineModel<string>('breakDailyLimitMinutes', { required: true });
const breakMaximumSingleMinutes = defineModel<string>('breakMaximumSingleMinutes', { required: true });

withDefaults(
    defineProps<{
        errors?: Partial<
            Record<
                | 'name'
                | 'display_name'
                | 'inactivity_timeout_minutes'
                | 'session_max_lifetime_minutes'
                | 'break_daily_limit_minutes'
                | 'break_maximum_single_minutes',
                string
            >
        >;
        sessionDefaults: {
            inactivityTimeoutMinutes: number;
            sessionMaxLifetimeMinutes: number;
        };
        breakDefaults: {
            dailyLimitMinutes: number;
            maximumSingleBreakMinutes: number;
        };
        processing?: boolean;
        submitLabel: string;
        processingLabel: string;
        backHref: string;
    }>(),
    {
        errors: () => ({}),
        processing: false,
    },
);

const emit = defineEmits<{
    submit: [];
}>();

const { t } = useTranslator();
</script>

<template>
    <AtlasForm class="space-y-5" :processing="processing" @submit="emit('submit')">
        <SurfaceCard :title="t('pages.admin.teams.form.identity_title')" :icon="IconUsersGroup" tone="teal">
            <div class="grid gap-4 lg:grid-cols-2">
                <FormInput
                    v-model="name"
                    :label="t('pages.admin.teams.form.technical_name')"
                    :placeholder="t('pages.admin.teams.form.technical_name_placeholder')"
                    :error="errors.name"
                />
                <FormInput
                    v-model="displayName"
                    :label="t('pages.admin.teams.form.display_name')"
                    :placeholder="t('pages.admin.teams.form.display_name_placeholder')"
                    :error="errors.display_name"
                />
            </div>
        </SurfaceCard>

        <SurfaceCard :title="t('pages.admin.teams.form.policy_limits_title')" :icon="IconUsersGroup" tone="sky">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <FormInput
                    v-model="inactivityTimeoutMinutes"
                    type="number"
                    inputmode="numeric"
                    step="1"
                    min="1"
                    suffix="min"
                    :label="t('pages.admin.users.fields.inactivity_timeout_minutes')"
                    :placeholder="
                        t('pages.admin.users.session_limits.default_minutes', { minutes: sessionDefaults.inactivityTimeoutMinutes })
                    "
                    :error="errors.inactivity_timeout_minutes"
                />
                <FormInput
                    v-model="sessionMaxLifetimeMinutes"
                    type="number"
                    inputmode="numeric"
                    step="1"
                    min="1"
                    suffix="min"
                    :label="t('pages.admin.users.fields.session_max_lifetime_minutes')"
                    :placeholder="
                        t('pages.admin.users.session_limits.default_minutes', { minutes: sessionDefaults.sessionMaxLifetimeMinutes })
                    "
                    :error="errors.session_max_lifetime_minutes"
                />
                <FormInput
                    v-model="breakDailyLimitMinutes"
                    type="number"
                    inputmode="numeric"
                    step="1"
                    min="0"
                    suffix="min"
                    :label="t('pages.admin.users.assignment.break_daily_limit_minutes')"
                    :placeholder="t('pages.admin.users.session_limits.default_minutes', { minutes: breakDefaults.dailyLimitMinutes })"
                    :error="errors.break_daily_limit_minutes"
                />
                <FormInput
                    v-model="breakMaximumSingleMinutes"
                    type="number"
                    inputmode="numeric"
                    step="1"
                    min="1"
                    suffix="min"
                    :label="t('pages.admin.users.assignment.break_maximum_single_minutes')"
                    :placeholder="
                        t('pages.admin.users.session_limits.default_minutes', { minutes: breakDefaults.maximumSingleBreakMinutes })
                    "
                    :error="errors.break_maximum_single_minutes"
                />
            </div>
        </SurfaceCard>

        <slot />

        <FormActions>
            <FormButton type="submit" :icon="IconDeviceFloppy" :loading="processing">
                {{ processing ? processingLabel : submitLabel }}
            </FormButton>
            <ActionLink :href="backHref" :icon="IconArrowLeft">
                {{ t('pages.admin.teams.actions.back_to_teams') }}
            </ActionLink>
        </FormActions>
    </AtlasForm>
</template>
