<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconPackage } from '@tabler/icons-vue';

import OnboardingPackageForm from '../../../../Components/Authorization/OnboardingPackageForm.vue';
import PageStack from '../../../../Components/PageStack.vue';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../../Localization/translator';
import type { AuthorizationAssignmentOption } from '../../../../Types/user-team-access';

const props = defineProps<{
    package: {
        publicId: string;
        teamPublicId: string;
        teamName: string;
        name: string;
        label: string;
        initialRoles: string[];
        directPermissions: string[];
    };
    roleOptions: AuthorizationAssignmentOption[];
    permissionOptions: AuthorizationAssignmentOption[];
    rolePermissionMap: Record<string, string[]>;
}>();

const { t } = useTranslator();
const form = useForm({
    team_public_id: props.package.teamPublicId,
    name: props.package.name,
    label: props.package.label,
    initial_roles: [...props.package.initialRoles],
    direct_permissions: [...props.package.directPermissions],
});

function submit(): void {
    form.patch(`/admin/authorization/packages/${encodeURIComponent(props.package.publicId)}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.packages.edit.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.packages.edit.title')" :title-icon="IconPackage">
        <PageStack>
            <OnboardingPackageForm
                v-model:team-public-id="form.team_public_id"
                v-model:name="form.name"
                v-model:label="form.label"
                v-model:initial-roles="form.initial_roles"
                v-model:direct-permissions="form.direct_permissions"
                mode="edit"
                :team-name="package.teamName"
                :role-options="roleOptions"
                :permission-options="permissionOptions"
                :role-permission-map="rolePermissionMap"
                :errors="form.errors"
                :processing="form.processing"
                :submit-label="t('pages.admin.packages.actions.save')"
                :processing-label="t('pages.admin.packages.actions.saving')"
                back-href="/admin/authorization/packages"
                @submit="submit"
            />
        </PageStack>
    </AppLayout>
</template>
