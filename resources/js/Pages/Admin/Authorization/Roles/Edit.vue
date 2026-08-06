<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconShieldLock } from '@tabler/icons-vue';

import RoleForm from '../../../../Components/Authorization/RoleForm.vue';
import PageStack from '../../../../Components/PageStack.vue';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../../Localization/translator';
import type { AuthorizationAssignmentOption } from '../../../../Types/user-team-access';

const props = defineProps<{
    role: {
        name: string;
        displayName: string;
        guard: string;
        permissions: string[];
    };
    permissionOptions: AuthorizationAssignmentOption[];
}>();

const { t } = useTranslator();
const form = useForm({
    name: props.role.name,
    display_name: props.role.displayName,
    permissions: [...props.role.permissions],
});

function submit(): void {
    form.patch(`/admin/authorization/roles/${encodeURIComponent(props.role.name)}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.roles.edit.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.roles.edit.title')" :title-icon="IconShieldLock">
        <PageStack>
            <RoleForm
                v-model:name="form.name"
                v-model:display-name="form.display_name"
                v-model:permissions="form.permissions"
                :permission-options="permissionOptions"
                :errors="form.errors"
                :processing="form.processing"
                :submit-label="t('pages.admin.roles.actions.save')"
                :processing-label="t('pages.admin.roles.actions.saving')"
                back-href="/admin/authorization/roles"
                @submit="submit"
            />
        </PageStack>
    </AppLayout>
</template>
