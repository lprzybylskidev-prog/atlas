<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconShieldPlus } from '@tabler/icons-vue';

import RoleForm from '../../../../Components/Authorization/RoleForm.vue';
import PageStack from '../../../../Components/PageStack.vue';
import AppLayout from '../../../../Layouts/AppLayout.vue';
import { useTranslator } from '../../../../Localization/translator';
import type { AuthorizationAssignmentOption } from '../../../../Types/user-team-access';

defineProps<{
    permissionOptions: AuthorizationAssignmentOption[];
}>();

const { t } = useTranslator();
const form = useForm({
    name: '',
    display_name: '',
    permissions: [] as string[],
});

function submit(): void {
    form.post('/admin/authorization/roles', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.roles.create.head_title')" />
    <AppLayout mode="admin" :title="t('pages.admin.roles.create.title')" :title-icon="IconShieldPlus">
        <PageStack>
            <RoleForm
                v-model:name="form.name"
                v-model:display-name="form.display_name"
                v-model:permissions="form.permissions"
                :permission-options="permissionOptions"
                :errors="form.errors"
                :processing="form.processing"
                :submit-label="t('pages.admin.roles.actions.create')"
                :processing-label="t('pages.admin.roles.actions.creating')"
                back-href="/admin/authorization/roles"
                @submit="submit"
            />
        </PageStack>
    </AppLayout>
</template>
