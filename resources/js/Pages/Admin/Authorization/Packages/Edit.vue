<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPackages } from '@tabler/icons-vue';

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

interface PackageFormData {
    publicId: string;
    teamPublicId: string;
    teamName: string;
    name: string;
    label: string;
    initialRoles: string[];
    directPermissions: string[];
}

const props = defineProps<{
    package: PackageFormData;
    roleOptions: string[];
    permissionOptions: string[];
}>();

const { t } = useTranslator();
const packageRecord = props.package;
const form = useForm({
    label: packageRecord.label,
    initial_roles: [...packageRecord.initialRoles],
    direct_permissions: [...packageRecord.directPermissions],
});
const recordActions = [
    {
        key: 'deactivate-preset',
        label: 'Deactivate',
        method: 'delete' as const,
        href: `/admin/authorization/packages/${packageRecord.publicId}`,
        tone: 'danger' as const,
    },
];

function submit(): void {
    form.patch(`/admin/authorization/packages/${packageRecord.publicId}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.packages.edit.head_title')" />
    <AdminLayout :title="t('pages.admin.packages.edit.title')" :title-icon="IconPackages">
        <PageStack>
            <SurfaceCard title="Actions" :icon="IconPackages">
                <RecordActions :actions="recordActions" />
            </SurfaceCard>

            <AtlasForm class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,32rem)]" :processing="form.processing" @submit="submit">
                <SurfaceCard title="Preset details" :icon="IconPackages">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <p class="mb-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">Team</p>
                            <p
                                class="min-h-10 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-200"
                            >
                                {{ packageRecord.teamName }}
                            </p>
                        </div>
                        <div>
                            <p class="mb-1 text-sm font-medium text-zinc-700 dark:text-zinc-200">Technical name</p>
                            <p
                                class="min-h-10 rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 font-mono text-sm text-zinc-700 dark:border-zinc-800 dark:bg-zinc-900/60 dark:text-zinc-200"
                            >
                                {{ packageRecord.name }}
                            </p>
                        </div>
                        <FormInput v-model="form.label" label="Label" :error="form.errors.label" />
                    </div>

                    <CheckboxList
                        v-model="form.initial_roles"
                        class="mt-5"
                        label="Initial roles"
                        :options="roleOptions"
                        :error="form.errors.initial_roles"
                        max-height="max-h-40"
                        :item-monospace="false"
                    />
                </SurfaceCard>

                <SurfaceCard title="Direct permissions" :icon="IconPackages">
                    <CheckboxList
                        v-model="form.direct_permissions"
                        :options="permissionOptions"
                        :error="form.errors.direct_permissions"
                        max-height="max-h-[32rem]"
                    />
                </SurfaceCard>

                <FormActions class="xl:col-span-2">
                    <FormButton type="submit" :loading="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save changes' }}
                    </FormButton>
                    <ActionLink href="/admin/authorization/packages" :icon="IconArrowLeft"> Back to presets </ActionLink>
                </FormActions>
            </AtlasForm>
        </PageStack>
    </AdminLayout>
</template>
