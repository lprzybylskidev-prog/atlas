<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconUsersGroup } from '@tabler/icons-vue';
import { computed } from 'vue';

import TeamForm from '../../../Components/Teams/TeamForm.vue';
import TeamMemberAccessWorkflow, { type TeamMemberAccessAssignment } from '../../../Components/Teams/TeamMemberAccessWorkflow.vue';
import PageStack from '../../../Components/PageStack.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import type { AuthorizationAssignmentOption } from '../../../Types/user-team-access';

const props = defineProps<{
    userOptions: FormSelectOption[];
    roleOptions: AuthorizationAssignmentOption[];
    permissionOptions: AuthorizationAssignmentOption[];
    rolePermissionMap: Record<string, string[]>;
    sessionDefaults: {
        inactivityTimeoutMinutes: number;
        sessionMaxLifetimeMinutes: number;
    };
    breakDefaults: {
        dailyLimitMinutes: number;
        maximumSingleBreakMinutes: number;
    };
}>();

const { t } = useTranslator();
const form = useForm({
    name: '',
    display_name: '',
    inactivity_timeout_minutes: '',
    session_max_lifetime_minutes: '',
    break_daily_limit_minutes: '',
    break_maximum_single_minutes: '',
    user_assignments: [] as TeamMemberAccessAssignment[],
});
const policyDefaults = computed(() => ({
    inactivityTimeoutMinutes:
        form.inactivity_timeout_minutes === '' ? props.sessionDefaults.inactivityTimeoutMinutes : Number(form.inactivity_timeout_minutes),
    sessionMaxLifetimeMinutes:
        form.session_max_lifetime_minutes === ''
            ? props.sessionDefaults.sessionMaxLifetimeMinutes
            : Number(form.session_max_lifetime_minutes),
    breakDailyLimitMinutes:
        form.break_daily_limit_minutes === '' ? props.breakDefaults.dailyLimitMinutes : Number(form.break_daily_limit_minutes),
    breakMaximumSingleMinutes:
        form.break_maximum_single_minutes === ''
            ? props.breakDefaults.maximumSingleBreakMinutes
            : Number(form.break_maximum_single_minutes),
}));

function addUser(userPublicId: string): void {
    if (form.user_assignments.some((assignment) => assignment.user_public_id === userPublicId)) {
        return;
    }

    form.user_assignments.push({
        user_public_id: userPublicId,
        role_names: [],
        direct_permission_names: [],
        inactivity_timeout_minutes: '',
        session_max_lifetime_minutes: '',
        break_daily_limit_minutes: '',
        break_maximum_single_minutes: '',
        reason: '',
        removal_reason: '',
    });
}

function removeAssignment(index: number): void {
    form.user_assignments.splice(index, 1);
}

function submit(): void {
    form.post('/admin/teams', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.teams.create.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.teams.create.title')" :title-icon="IconUsersGroup">
        <PageStack>
            <TeamForm
                v-model:name="form.name"
                v-model:display-name="form.display_name"
                v-model:inactivity-timeout-minutes="form.inactivity_timeout_minutes"
                v-model:session-max-lifetime-minutes="form.session_max_lifetime_minutes"
                v-model:break-daily-limit-minutes="form.break_daily_limit_minutes"
                v-model:break-maximum-single-minutes="form.break_maximum_single_minutes"
                :errors="form.errors"
                :session-defaults="sessionDefaults"
                :break-defaults="breakDefaults"
                :processing="form.processing"
                :submit-label="t('pages.admin.teams.actions.create')"
                :processing-label="t('pages.admin.teams.actions.creating')"
                back-href="/admin/teams"
                @submit="submit"
            >
                <TeamMemberAccessWorkflow
                    mode="create"
                    :assignments="form.user_assignments"
                    :user-options="props.userOptions"
                    :role-options="props.roleOptions"
                    :permission-options="props.permissionOptions"
                    :role-permission-map="props.rolePermissionMap"
                    :session-defaults="sessionDefaults"
                    :policy-defaults="policyDefaults"
                    :processing="form.processing"
                    :root-error="form.errors.user_assignments"
                    :errors="form.errors"
                    @add-user="addUser"
                    @remove="removeAssignment($event.index)"
                />
            </TeamForm>
        </PageStack>
    </AppLayout>
</template>
