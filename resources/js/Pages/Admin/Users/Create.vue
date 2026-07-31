<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconUserPlus } from '@tabler/icons-vue';

import ActionLink from '../../../Components/ActionLink.vue';
import AtlasForm from '../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../Components/Form/FormButton.vue';
import FormInput from '../../../Components/Form/FormInput.vue';
import FormSelect, { type FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import FormActions from '../../../Components/FormActions.vue';
import PageStack from '../../../Components/PageStack.vue';
import SurfaceCard from '../../../Components/SurfaceCard.vue';
import UserTeamAccessWorkflow from '../../../Components/Users/UserTeamAccessWorkflow.vue';
import { useAccountSensitivityOptions } from '../../../Composables/useAccountSensitivityOptions';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type {
    AuthorizationAssignmentOption,
    UserTeamAccessAssignment,
    UserTeamAccessCopySource,
    UserTeamAccessPackage,
} from '../../../Types/user-team-access';

defineProps<{
    packages: UserTeamAccessPackage[];
    copySources: UserTeamAccessCopySource[];
    teamOptions: FormSelectOption[];
    roleOptions: AuthorizationAssignmentOption[];
    permissionOptions: AuthorizationAssignmentOption[];
    rolePermissionMap: Record<string, string[]>;
}>();

const { t } = useTranslator();
const accountSensitivity = useAccountSensitivityOptions();
const sensitivityOptions = accountSensitivity.options;
const form = useForm({
    name: '',
    email: '',
    account_sensitivity: 'sensitive',
    team_assignments: [] as UserTeamAccessAssignment[],
});

function addTeamAssignment(teamPublicId: string): void {
    form.team_assignments.push({
        team_public_id: teamPublicId,
        source: 'manual',
        onboarding_package: '',
        copy_authorization_from_user: '',
        role_names: [],
        direct_permission_names: [],
        reason: '',
        removal_reason: '',
    });
}

function removeTeamAssignment(index: number): void {
    form.team_assignments.splice(index, 1);
}

function submit(): void {
    form.post('/admin/users', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.users.create.head_title')" />
    <AdminLayout :title="t('pages.admin.users.create.title')" :title-icon="IconUserPlus">
        <PageStack>
            <AtlasForm class="space-y-5" :processing="form.processing" @submit="submit">
                <SurfaceCard :title="t('pages.admin.users.identity.title')" :icon="IconUserPlus" tone="teal">
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
                </SurfaceCard>

                <UserTeamAccessWorkflow
                    mode="create"
                    :assignments="form.team_assignments"
                    :team-options="teamOptions"
                    :packages="packages"
                    :copy-sources="copySources"
                    :role-options="roleOptions"
                    :permission-options="permissionOptions"
                    :role-permission-map="rolePermissionMap"
                    :root-error="form.errors.team_assignments"
                    :errors="form.errors"
                    @add-team="addTeamAssignment"
                    @remove="removeTeamAssignment($event.index)"
                />

                <FormActions>
                    <FormButton type="submit" :icon="IconUserPlus" :loading="form.processing">
                        {{ form.processing ? t('pages.admin.users.actions.creating') : t('pages.admin.users.actions.create') }}
                    </FormButton>
                    <ActionLink href="/admin/users" :icon="IconArrowLeft">
                        {{ t('pages.admin.users.actions.back_to_users') }}
                    </ActionLink>
                </FormActions>
            </AtlasForm>
        </PageStack>
    </AdminLayout>
</template>
