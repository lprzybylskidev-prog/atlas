<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconUsersGroup } from '@tabler/icons-vue';

import TeamForm from '../../../Components/Teams/TeamForm.vue';
import TeamMemberAccessWorkflow, { type TeamMemberAccessAssignment } from '../../../Components/Teams/TeamMemberAccessWorkflow.vue';
import PageStack from '../../../Components/PageStack.vue';
import AdminLayout from '../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../Localization/translator';
import type { FormSelectOption } from '../../../Components/Form/FormSelect.vue';
import type { AuthorizationAssignmentOption } from '../../../Types/user-team-access';

const props = defineProps<{
    userOptions: FormSelectOption[];
    roleOptions: AuthorizationAssignmentOption[];
    permissionOptions: AuthorizationAssignmentOption[];
    rolePermissionMap: Record<string, string[]>;
}>();

const { t } = useTranslator();
const form = useForm({
    name: '',
    display_name: '',
    user_assignments: [] as TeamMemberAccessAssignment[],
});

function addUser(userPublicId: string): void {
    if (form.user_assignments.some((assignment) => assignment.user_public_id === userPublicId)) {
        return;
    }

    form.user_assignments.push({
        user_public_id: userPublicId,
        role_names: [],
        direct_permission_names: [],
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
    <AdminLayout :title="t('pages.admin.teams.create.title')" :title-icon="IconUsersGroup">
        <PageStack>
            <TeamForm
                v-model:name="form.name"
                v-model:display-name="form.display_name"
                :errors="form.errors"
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
                    :processing="form.processing"
                    :root-error="form.errors.user_assignments"
                    :errors="form.errors"
                    @add-user="addUser"
                    @remove="removeAssignment($event.index)"
                />
            </TeamForm>
        </PageStack>
    </AdminLayout>
</template>
