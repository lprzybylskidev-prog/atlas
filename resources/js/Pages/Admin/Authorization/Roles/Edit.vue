<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconShieldCheck } from '@tabler/icons-vue';

import ActionLink from '../../../../Components/ActionLink.vue';
import CheckboxList from '../../../../Components/CheckboxList.vue';
import FormActions from '../../../../Components/FormActions.vue';
import RecordActions from '../../../../Components/RecordActions.vue';
import AtlasForm from '../../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../../Components/Form/FormButton.vue';
import FormInput from '../../../../Components/Form/FormInput.vue';
import PageStack from '../../../../Components/PageStack.vue';
import SurfaceCard from '../../../../Components/SurfaceCard.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../../Localization/translator';

interface RoleFormData {
    name: string;
    guard: string;
    permissions: string[];
}

const props = defineProps<{
    role: RoleFormData;
    permissionOptions: string[];
}>();

const { t } = useTranslator();
const form = useForm({
    name: props.role.name,
    permissions: [...props.role.permissions],
});
const recordActions = [
    { key: 'delete', label: 'Delete', method: 'delete' as const, href: `/admin/authorization/roles/${props.role.name}` },
];

function submit(): void {
    form.patch(`/admin/authorization/roles/${props.role.name}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.roles.edit.head_title')" />
    <AdminLayout :title="t('pages.admin.roles.edit.title')" :title-icon="IconShieldCheck">
        <PageStack>
            <SurfaceCard title="Actions" :icon="IconShieldCheck">
                <RecordActions :actions="recordActions" />
            </SurfaceCard>

            <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">
                <SurfaceCard title="Role permissions" :icon="IconShieldCheck">
                    <AtlasForm :processing="form.processing" @submit="submit">
                        <FormInput v-model="form.name" label="Name" :error="form.errors.name" monospace />

                        <CheckboxList
                            v-model="form.permissions"
                            class="mt-5"
                            label="Permissions"
                            :options="permissionOptions"
                            :error="form.errors.permissions"
                            max-height="max-h-[32rem]"
                        />

                        <FormActions class="mt-5">
                            <FormButton type="submit" :loading="form.processing">
                                {{ form.processing ? 'Saving...' : 'Save changes' }}
                            </FormButton>
                            <ActionLink href="/admin/authorization/roles" :icon="IconArrowLeft"> Back to roles </ActionLink>
                        </FormActions>
                    </AtlasForm>
                </SurfaceCard>

                <SurfaceCard title="Role metadata" :icon="IconShieldCheck">
                    <dl class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-zinc-500 dark:text-zinc-400">Guard</dt>
                            <dd class="font-mono text-xs font-medium text-zinc-900 dark:text-zinc-100">{{ role.guard }}</dd>
                        </div>
                    </dl>
                </SurfaceCard>
            </div>
        </PageStack>
    </AdminLayout>
</template>
