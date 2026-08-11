<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconUserEdit } from '@tabler/icons-vue';
import { computed, reactive } from 'vue';

import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormActions from '../../../Components/FormActions.vue';
import PageStack from '../../../Components/PageStack.vue';
import ActionGroup from '../../../Components/Actions/ActionGroup.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import UserTeamAuthorizationWorkflow from '../../../Components/Authorization/UserTeamAuthorizationWorkflow.vue';
import { useAccountSensitivityOptions } from '../../../Composables/useAccountSensitivityOptions';
import { useAdminUserAccountActions } from '../../../Composables/useAdminUserAccountActions';
import AppLayout from '../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type {
    AuthorizationAssignmentOption,
    TeamPolicyDefaults,
    UserTeamAccessAssignment,
    UserTeamAccessCopySource,
    UserTeamAccessPackage,
} from '../../../Types/user-team-access';

interface UserFormData {
    publicId: string;
    name: string;
    email: string;
    isActive: boolean;
    emailVerified: boolean;
    firstPasswordSet: boolean;
    loginLocked: boolean;
    mfaEnabled: boolean;
    accountSensitivity: string;
    canImpersonate: boolean;
    impersonationRequiresSensitiveOverride: boolean;
}

interface TeamMembership {
    teamPublicId: string;
    teamName: string;
    teamActive: boolean;
    validFrom: string | null;
    validTo: string | null;
    roleNames: string[];
    directPermissionNames: string[];
    inactivityTimeoutMinutes: number | null;
    sessionMaxLifetimeMinutes: number | null;
    breakDailyLimitMinutes: number | null;
    breakMaximumSingleMinutes: number | null;
    provenancePublicId: string | null;
    provenanceSourceType: 'manual' | 'preset' | 'copy';
    provenanceSourceLabel: string | null;
    provenanceDivergedAt: string | null;
    provenanceVersion: number;
}

const props = defineProps<{
    user: UserFormData;
    teamMemberships: TeamMembership[];
    assignableTeams: FormSelectOption[];
    packages: UserTeamAccessPackage[];
    copySources: UserTeamAccessCopySource[];
    roleOptions: AuthorizationAssignmentOption[];
    permissionOptions: AuthorizationAssignmentOption[];
    rolePermissionMap: Record<string, string[]>;
    sessionDefaults: {
        inactivityTimeoutMinutes: number;
        sessionMaxLifetimeMinutes: number;
    };
    teamPolicyDefaults: Record<string, TeamPolicyDefaults>;
}>();

const { t } = useTranslator();
const pageTitle = computed(() => t('pages.admin.users.edit.title', { object: props.user.name || props.user.email }));
const form = useForm({
    name: props.user.name,
    email: props.user.email,
    account_sensitivity: props.user.accountSensitivity,
});
const teamForm = useForm({
    team_public_id: '',
});
const teamAccessAssignments = reactive<UserTeamAccessAssignment[]>(
    props.teamMemberships.map((membership) => ({
        team_public_id: membership.teamPublicId,
        teamName: membership.teamName,
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
        provenance_diverged_at: membership.provenanceDivergedAt,
        provenance_version: membership.provenanceVersion,
    })),
);
const accountSensitivity = useAccountSensitivityOptions();
const userAccountActions = useAdminUserAccountActions();
const sensitivityOptions = accountSensitivity.options;
const recordActions = userAccountActions.recordActions(props.user);

function submit(): void {
    form.patch(`/admin/users/${props.user.publicId}`, { preserveScroll: true });
}

function addTeamAccess(): void {
    teamForm.post(`/admin/users/${props.user.publicId}/teams`, {
        preserveScroll: true,
        onSuccess: () => teamForm.reset('team_public_id'),
    });
}

function addTeamAccessFromWorkflow(teamPublicId: string): void {
    teamForm.team_public_id = teamPublicId;
    addTeamAccess();
}

function removeTeamAccess(assignment: UserTeamAccessAssignment): void {
    router.delete(`/admin/users/${props.user.publicId}/teams/${assignment.team_public_id}`, {
        data: {
            reason: assignment.removal_reason ?? '',
        },
        preserveScroll: true,
    });
}

function updateTeamAuthorization(assignment: UserTeamAccessAssignment): void {
    router.patch(
        `/admin/users/${props.user.publicId}/teams/${assignment.team_public_id}/authorization`,
        {
            role_names: assignment.role_names,
            direct_permission_names: assignment.direct_permission_names,
            inactivity_timeout_minutes: assignment.inactivity_timeout_minutes,
            session_max_lifetime_minutes: assignment.session_max_lifetime_minutes,
            break_daily_limit_minutes: assignment.break_daily_limit_minutes,
            break_maximum_single_minutes: assignment.break_maximum_single_minutes,
            reason: assignment.reason ?? '',
            expected_version: assignment.provenance_version ?? 0,
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="pageTitle" />
    <AppLayout mode="admin" :title="pageTitle" :title-icon="IconUserEdit">
        <PageStack>
            <SurfaceCard :title="t('pages.admin.users.status.title')" :icon="IconUserEdit" tone="emerald">
                <dl class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-5">
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.active') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.isActive ? 'active' : 'inactive'"
                                :label="t(user.isActive ? 'datatable.status.active' : 'datatable.status.inactive')"
                            />
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.email_verified') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.emailVerified ? 'verified' : 'unverified'"
                                :label="
                                    t(
                                        user.emailVerified
                                            ? 'pages.admin.users.status.email_verified'
                                            : 'pages.admin.users.status.email_unverified',
                                    )
                                "
                            />
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.first_password_set') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.firstPasswordSet ? 'enabled' : 'pending'"
                                :label="
                                    t(
                                        user.firstPasswordSet
                                            ? 'pages.admin.users.status.first_password_set'
                                            : 'pages.admin.users.status.first_password_pending',
                                    )
                                "
                            />
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.login_locked') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.loginLocked ? 'blocked' : 'active'"
                                :label="
                                    t(
                                        user.loginLocked
                                            ? 'pages.admin.users.status.login_locked'
                                            : 'pages.admin.users.status.login_unlocked',
                                    )
                                "
                            />
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.mfa_enabled') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.mfaEnabled ? 'enabled' : 'disabled'"
                                :label="
                                    t(user.mfaEnabled ? 'pages.admin.users.status.mfa_enabled' : 'pages.admin.users.status.mfa_disabled')
                                "
                            />
                        </dd>
                    </div>
                </dl>
            </SurfaceCard>

            <SurfaceCard :title="t('pages.admin.users.actions.title')" :icon="IconUserEdit" tone="amber">
                <ActionGroup :actions="recordActions" placement="edit" />
            </SurfaceCard>

            <div class="space-y-5">
                <SurfaceCard :title="t('pages.admin.users.identity.title')" :icon="IconUserEdit" tone="teal">
                    <AtlasForm :processing="form.processing" @submit="submit">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <FormInput
                                v-model="form.name"
                                :label="t('pages.admin.users.fields.name')"
                                autocomplete="name"
                                :error="form.errors.name"
                            />
                            <FormInput
                                v-model="form.email"
                                :label="t('pages.admin.users.fields.email')"
                                type="email"
                                autocomplete="email"
                                :error="form.errors.email"
                            />
                            <FormSelect
                                v-model="form.account_sensitivity"
                                :label="t('pages.admin.users.fields.account_sensitivity')"
                                :options="sensitivityOptions"
                                :error="form.errors.account_sensitivity"
                            />
                        </div>

                        <FormActions
                            class="mt-5"
                            :submit-label="t('pages.admin.users.actions.save')"
                            :processing-label="t('pages.admin.users.actions.saving')"
                            :processing="form.processing"
                            :dirty="form.isDirty"
                            cancel-href="/admin/users"
                            :scope-label="t('pages.admin.users.identity.title')"
                        />
                    </AtlasForm>
                </SurfaceCard>

                <UserTeamAuthorizationWorkflow
                    mode="edit"
                    :assignments="teamAccessAssignments"
                    :team-options="assignableTeams"
                    :packages="packages"
                    :copy-sources="copySources"
                    :role-options="roleOptions"
                    :permission-options="permissionOptions"
                    :role-permission-map="rolePermissionMap"
                    :session-defaults="sessionDefaults"
                    :team-policy-defaults="teamPolicyDefaults"
                    :processing="teamForm.processing"
                    :root-error="teamForm.errors.team_public_id"
                    @add-team="addTeamAccessFromWorkflow"
                    @save="updateTeamAuthorization($event.assignment)"
                    @remove="removeTeamAccess($event.assignment)"
                />
            </div>
        </PageStack>
    </AppLayout>
</template>
