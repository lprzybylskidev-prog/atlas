<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconPackageExport } from '@tabler/icons-vue';

import OnboardingPackageForm from '../../../../Components/Authorization/OnboardingPackageForm.vue';
import PageStack from '../../../../Components/PageStack.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../../Localization/translator';
import type { AuthorizationAssignmentOption } from '../../../../Types/user-team-access';

interface TeamOption {
    value: string;
    label: string;
}

defineProps<{
    roleOptions: AuthorizationAssignmentOption[];
    permissionOptions: AuthorizationAssignmentOption[];
    rolePermissionMap: Record<string, string[]>;
    teamOptions: TeamOption[];
}>();

const { t } = useTranslator();
const form = useForm({
    team_public_id: '',
    name: '',
    label: '',
    initial_roles: [] as string[],
    direct_permissions: [] as string[],
});

function submit(): void {
    form.post('/admin/authorization/packages', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.packages.create.head_title')" />
    <AdminLayout :title="t('pages.admin.packages.create.title')" :title-icon="IconPackageExport">
        <PageStack>
            <OnboardingPackageForm
                v-model:team-public-id="form.team_public_id"
                v-model:name="form.name"
                v-model:label="form.label"
                v-model:initial-roles="form.initial_roles"
                v-model:direct-permissions="form.direct_permissions"
                mode="create"
                :team-options="teamOptions"
                :role-options="roleOptions"
                :permission-options="permissionOptions"
                :role-permission-map="rolePermissionMap"
                :errors="form.errors"
                :processing="form.processing"
                :submit-label="t('pages.admin.packages.actions.create')"
                :processing-label="t('pages.admin.packages.actions.creating')"
                back-href="/admin/authorization/packages"
                @submit="submit"
            />
        </PageStack>
    </AdminLayout>
</template>
