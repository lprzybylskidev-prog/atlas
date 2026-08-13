<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconUsersGroup } from '@tabler/icons-vue';
import { computed } from 'vue';

import ActionGroup from '../../../Components/Actions/ActionGroup.vue';
import TeamForm from '../../../Components/Teams/TeamForm.vue';
import UserTeamAuthorizationWorkflow from '../../../Components/Authorization/UserTeamAuthorizationWorkflow.vue';
import PageStack from '../../../Components/PageStack.vue';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import type { AuthorizationAssignmentOption, UserTeamAccessAssignment } from '../../../Types/user-team-access';
import type { AtlasAction } from '../../../Types/actions';

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
        roleNames: string[];
        directPermissionNames: string[];
        inactivityTimeoutMinutes: number | null;
        sessionMaxLifetimeMinutes: number | null;
        breakDailyLimitMinutes: number | null;
        breakMaximumSingleMinutes: number | null;
        provenancePublicId: string | null;
        provenanceSourceType: 'manual' | 'preset' | 'copy';
        provenanceSourceLabel: string | null;
        provenanceVersion: number;
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
const pageTitle = computed(() => t('pages.admin.teams.edit.title', { object: props.team.displayName || props.team.name }));
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
const memberAssignments: UserTeamAccessAssignment[] = props.memberships.map((membership) => ({
    team_public_id: props.team.publicId,
    user_public_id: membership.userPublicId,
    userName: membership.userName,
    userEmail: membership.userEmail,
    source: membership.provenanceSourceType === 'preset' ? 'package' : membership.provenanceSourceType,
    onboarding_package: '',
    copy_authorization_from_user: '',
    role_names: [...membership.roleNames],
    direct_permission_names: [...membership.directPermissionNames],
    inactivity_timeout_minutes: membership.inactivityTimeoutMinutes === null ? '' : String(membership.inactivityTimeoutMinutes),
    session_max_lifetime_minutes: membership.sessionMaxLifetimeMinutes === null ? '' : String(membership.sessionMaxLifetimeMinutes),
    break_daily_limit_minutes: membership.breakDailyLimitMinutes === null ? '' : String(membership.breakDailyLimitMinutes),
    break_maximum_single_minutes: membership.breakMaximumSingleMinutes === null ? '' : String(membership.breakMaximumSingleMinutes),
    reason: '',
    removal_reason: '',
    provenance_public_id: membership.provenancePublicId,
    provenance_source_type: membership.provenanceSourceType,
    provenance_source_label: membership.provenanceSourceLabel,
    provenance_version: membership.provenanceVersion,
}));

const recordActions = computed<AtlasAction<undefined>[]>(() => [
    {
        key: 'view',
        label: t('pages.admin.teams.structure.title'),
        href: `/admin/teams/${encodeURIComponent(props.team.publicId)}/structure`,
        method: 'get',
    },
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
        semantic: 'deactivate',
        confirm: {
            titleKey: 'modal.action.deactivate.title',
            descriptionKey: 'modal.action.deactivate.description',
            confirmKey: 'modal.action.deactivate.confirm',
            subject: props.team.displayName || props.team.name,
            tone: 'danger',
        },
        disabled: !props.team.isActive,
        disabledReason: t('pages.admin.teams.actions.deactivate_disabled'),
    },
]);

function submit(): void {
    form.patch(`/admin/teams/${encodeURIComponent(props.team.publicId)}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout mode="admin" :title="pageTitle" :title-icon="IconUsersGroup">
        <PageStack>
            <div class="flex justify-end">
                <ActionGroup :actions="recordActions" placement="edit" />
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
                :dirty="form.isDirty"
                independent-workflow
                :submit-label="t('pages.admin.teams.actions.save')"
                :processing-label="t('pages.admin.teams.actions.saving')"
                back-href="/admin/teams"
                @submit="submit"
            >
                <UserTeamAuthorizationWorkflow
                    mode="edit"
                    context-axis="team"
                    :membership-mutation="false"
                    :authorization-mutation="false"
                    :assignments="memberAssignments"
                    :user-options="assignableUsers"
                    :team-options="[]"
                    :packages="[]"
                    :copy-sources="[]"
                    :role-options="roleOptions"
                    :permission-options="permissionOptions"
                    :role-permission-map="rolePermissionMap"
                    :session-defaults="sessionDefaults"
                    :team-policy-defaults="{ [team.publicId]: policyDefaults }"
                />
            </TeamForm>
        </PageStack>
    </AppLayout>
</template>
