<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconDeviceFloppy, IconUserEdit } from '@tabler/icons-vue';
import { reactive } from 'vue';

import ActionLink from '../../../Components/ActionLink.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormActions from '../../../Components/FormActions.vue';
import PageStack from '../../../Components/PageStack.vue';
import RecordActions from '../../../Components/RecordActions.vue';
import StatusBadge from '../../../Components/StatusBadge.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import UserTeamAccessWorkflow from '../../../Components/Users/UserTeamAccessWorkflow.vue';
import { useAccountSensitivityOptions } from '../../../Composables/useAccountSensitivityOptions';
import { useAdminUserAccountActions } from '../../../Composables/useAdminUserAccountActions';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type {
    AuthorizationAssignmentOption,
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
}>();

const { t } = useTranslator();
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
        source: 'manual',
        onboarding_package: '',
        copy_authorization_from_user: '',
        role_names: [...membership.roleNames],
        direct_permission_names: [...membership.directPermissionNames],
        reason: '',
        removal_reason: '',
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
            reason: assignment.reason ?? '',
        },
        { preserveScroll: true },
    );
}
</script>

<template>
    <Head :title="t('pages.admin.users.edit.head_title')" />
    <AdminLayout :title="t('pages.admin.users.edit.title')" :title-icon="IconUserEdit">
        <PageStack>
            <SurfaceCard :title="t('pages.admin.users.status.title')" :icon="IconUserEdit" tone="emerald">
                <dl class="grid gap-3 text-sm sm:grid-cols-2 xl:grid-cols-5">
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.active') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.isActive"
                                :true-label="t('datatable.boolean.yes')"
                                :false-label="t('datatable.boolean.no')"
                            />
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.email_verified') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.emailVerified"
                                :true-label="t('datatable.boolean.yes')"
                                :false-label="t('datatable.boolean.no')"
                            />
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.first_password_set') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.firstPasswordSet"
                                :true-label="t('datatable.boolean.yes')"
                                :false-label="t('datatable.boolean.no')"
                            />
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.login_locked') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.loginLocked"
                                :true-label="t('datatable.boolean.yes')"
                                :false-label="t('datatable.boolean.no')"
                            />
                        </dd>
                    </div>
                    <div
                        class="flex items-center justify-between gap-3 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 dark:border-zinc-800 dark:bg-zinc-900/50"
                    >
                        <dt class="text-zinc-500 dark:text-zinc-400">{{ t('pages.admin.users.status.mfa_enabled') }}</dt>
                        <dd>
                            <StatusBadge
                                :value="user.mfaEnabled"
                                :true-label="t('datatable.boolean.yes')"
                                :false-label="t('datatable.boolean.no')"
                            />
                        </dd>
                    </div>
                </dl>
            </SurfaceCard>

            <SurfaceCard :title="t('pages.admin.users.actions.title')" :icon="IconUserEdit" tone="amber">
                <RecordActions :actions="recordActions" />
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

                        <FormActions class="mt-5">
                            <FormButton type="submit" :icon="IconDeviceFloppy" :loading="form.processing">
                                {{ form.processing ? t('pages.admin.users.actions.saving') : t('pages.admin.users.actions.save') }}
                            </FormButton>
                            <ActionLink href="/admin/users" :icon="IconArrowLeft">
                                {{ t('pages.admin.users.actions.back_to_users') }}
                            </ActionLink>
                        </FormActions>
                    </AtlasForm>
                </SurfaceCard>

                <UserTeamAccessWorkflow
                    mode="edit"
                    :assignments="teamAccessAssignments"
                    :team-options="assignableTeams"
                    :packages="packages"
                    :copy-sources="copySources"
                    :role-options="roleOptions"
                    :permission-options="permissionOptions"
                    :role-permission-map="rolePermissionMap"
                    :processing="teamForm.processing"
                    :root-error="teamForm.errors.team_public_id"
                    @add-team="addTeamAccessFromWorkflow"
                    @save="updateTeamAuthorization($event.assignment)"
                    @remove="removeTeamAccess($event.assignment)"
                />
            </div>
        </PageStack>
    </AdminLayout>
</template>
