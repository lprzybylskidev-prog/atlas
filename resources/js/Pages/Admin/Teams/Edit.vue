<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconUsersGroup } from '@tabler/icons-vue';
import { computed, ref } from 'vue';

import RecordActions, { type RecordAction } from '../../../Components/RecordActions.vue';
import TeamForm from '../../../Components/Teams/TeamForm.vue';
import TeamMemberAccessWorkflow, { type TeamMemberAccessAssignment } from '../../../Components/Teams/TeamMemberAccessWorkflow.vue';
import PageStack from '../../../Components/PageStack.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import type { AuthorizationAssignmentOption } from '../../../Types/user-team-access';

const props = defineProps<{
    team: {
        publicId: string;
        name: string;
        displayName: string;
        isActive: boolean;
        inactivityTimeoutMinutes: number | null;
        sessionMaxLifetimeMinutes: number | null;
        breakDailyLimitMinutes: number | null;
        breakMaximumSingleMinutes: number | null;
    };
    memberships: Array<{
        userPublicId: string;
        userName: string;
        userEmail: string;
        validFrom: string | null;
        validTo: string | null;
        roleNames: string[];
        directPermissionNames: string[];
        inactivityTimeoutMinutes: number | null;
        sessionMaxLifetimeMinutes: number | null;
        breakDailyLimitMinutes: number | null;
        breakMaximumSingleMinutes: number | null;
    }>;
    assignableUsers: FormSelectOption[];
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
    name: props.team.name,
    display_name: props.team.displayName,
    inactivity_timeout_minutes: props.team.inactivityTimeoutMinutes === null ? '' : String(props.team.inactivityTimeoutMinutes),
    session_max_lifetime_minutes: props.team.sessionMaxLifetimeMinutes === null ? '' : String(props.team.sessionMaxLifetimeMinutes),
    break_daily_limit_minutes: props.team.breakDailyLimitMinutes === null ? '' : String(props.team.breakDailyLimitMinutes),
    break_maximum_single_minutes: props.team.breakMaximumSingleMinutes === null ? '' : String(props.team.breakMaximumSingleMinutes),
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
const memberAssignments = ref<TeamMemberAccessAssignment[]>(
    props.memberships.map((membership) => ({
        user_public_id: membership.userPublicId,
        userName: membership.userName,
        userEmail: membership.userEmail,
        role_names: [...membership.roleNames],
        direct_permission_names: [...membership.directPermissionNames],
        inactivity_timeout_minutes: membership.inactivityTimeoutMinutes === null ? '' : String(membership.inactivityTimeoutMinutes),
        session_max_lifetime_minutes: membership.sessionMaxLifetimeMinutes === null ? '' : String(membership.sessionMaxLifetimeMinutes),
        break_daily_limit_minutes: membership.breakDailyLimitMinutes === null ? '' : String(membership.breakDailyLimitMinutes),
        break_maximum_single_minutes: membership.breakMaximumSingleMinutes === null ? '' : String(membership.breakMaximumSingleMinutes),
        reason: '',
        removal_reason: '',
    })),
);
const memberProcessing = ref(false);

const recordActions = computed<RecordAction[]>(() => [
    {
        key: 'activate',
        label: t('pages.admin.teams.actions.activate'),
        href: `/admin/teams/${encodeURIComponent(props.team.publicId)}/activate`,
        method: 'post',
        tone: 'success',
        disabled: props.team.isActive,
        disabledReason: t('pages.admin.teams.actions.activate_disabled'),
    },
    {
        key: 'deactivate',
        label: t('pages.admin.teams.actions.deactivate'),
        href: `/admin/teams/${encodeURIComponent(props.team.publicId)}/deactivate`,
        method: 'post',
        tone: 'danger',
        disabled: !props.team.isActive,
        disabledReason: t('pages.admin.teams.actions.deactivate_disabled'),
    },
]);

function submit(): void {
    form.patch(`/admin/teams/${encodeURIComponent(props.team.publicId)}`, { preserveScroll: true });
}

function addUser(userPublicId: string): void {
    memberProcessing.value = true;
    router.post(
        `/admin/teams/${encodeURIComponent(props.team.publicId)}/users`,
        { user_public_id: userPublicId },
        {
            preserveScroll: true,
            onFinish: () => {
                memberProcessing.value = false;
            },
        },
    );
}

function saveAuthorization(assignment: TeamMemberAccessAssignment): void {
    memberProcessing.value = true;
    router.patch(
        `/admin/teams/${encodeURIComponent(props.team.publicId)}/users/${encodeURIComponent(assignment.user_public_id)}/authorization`,
        {
            role_names: assignment.role_names,
            direct_permission_names: assignment.direct_permission_names,
            inactivity_timeout_minutes: assignment.inactivity_timeout_minutes,
            session_max_lifetime_minutes: assignment.session_max_lifetime_minutes,
            break_daily_limit_minutes: assignment.break_daily_limit_minutes,
            break_maximum_single_minutes: assignment.break_maximum_single_minutes,
            reason: assignment.reason ?? '',
        },
        {
            preserveScroll: true,
            onFinish: () => {
                memberProcessing.value = false;
            },
        },
    );
}

function removeUser(assignment: TeamMemberAccessAssignment): void {
    if ((assignment.removal_reason ?? '').trim() === '') {
        return;
    }

    memberProcessing.value = true;
    router.delete(`/admin/teams/${encodeURIComponent(props.team.publicId)}/users/${encodeURIComponent(assignment.user_public_id)}`, {
        data: {
            reason: assignment.removal_reason ?? '',
        },
        preserveScroll: true,
        onFinish: () => {
            memberProcessing.value = false;
        },
    });
}
</script>

<template>
    <Head :title="t('pages.admin.teams.edit.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.teams.edit.title')" :title-icon="IconUsersGroup">
        <PageStack>
            <div class="flex justify-end">
                <RecordActions :actions="recordActions" />
            </div>

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
                :submit-label="t('pages.admin.teams.actions.save')"
                :processing-label="t('pages.admin.teams.actions.saving')"
                back-href="/admin/teams"
                @submit="submit"
            >
                <TeamMemberAccessWorkflow
                    mode="edit"
                    :assignments="memberAssignments"
                    :user-options="assignableUsers"
                    :role-options="roleOptions"
                    :permission-options="permissionOptions"
                    :role-permission-map="rolePermissionMap"
                    :session-defaults="sessionDefaults"
                    :policy-defaults="policyDefaults"
                    :processing="memberProcessing"
                    @add-user="addUser"
                    @save="saveAuthorization($event.assignment)"
                    @remove="removeUser($event.assignment)"
                />
            </TeamForm>
        </PageStack>
    </AppLayout>
</template>
