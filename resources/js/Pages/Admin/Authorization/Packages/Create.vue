<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPackages } from '@tabler/icons-vue';

import ActionLink from '../../../../Components/ActionLink.vue';
import CardHeader from '../../../../Components/CardHeader.vue';
import CheckboxList from '../../../../Components/CheckboxList.vue';
import FormActions from '../../../../Components/FormActions.vue';
import AtlasForm from '../../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../../Components/Form/FormButton.vue';
import FormInput from '../../../../Components/Form/FormInput.vue';
import FormSelect from '../../../../Components/Form/FormSelect.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../../Localization/translator';
import type { FormSelectOption } from '../../../../Components/Form/FormSelect.vue';

defineProps<{
    roleOptions: string[];
    permissionOptions: string[];
    teamOptions: FormSelectOption[];
}>();

const { t } = useTranslator('en');
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
    <AdminLayout :title="t('pages.admin.packages.create.title')" :title-icon="IconPackages">
        <AtlasForm class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,32rem)]" :processing="form.processing" @submit="submit">
            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <div class="grid gap-4 sm:grid-cols-2">
                    <FormSelect
                        v-model="form.team_public_id"
                        label="Team"
                        :options="[{ value: '', label: 'Select team' }, ...teamOptions]"
                        :error="form.errors.team_public_id"
                    />
                    <FormInput
                        v-model="form.name"
                        label="Technical name"
                        placeholder="department.responsibility"
                        :error="form.errors.name"
                        monospace
                    />
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
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <CardHeader title="Direct permissions" :icon="IconPackages" />
                <CheckboxList
                    v-model="form.direct_permissions"
                    class="mt-3"
                    :options="permissionOptions"
                    :error="form.errors.direct_permissions"
                    max-height="max-h-[32rem]"
                />
            </section>

            <FormActions class="xl:col-span-2">
                <FormButton type="submit" :loading="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save preset' }}
                </FormButton>
                <ActionLink href="/admin/authorization/packages" :icon="IconArrowLeft"> Back to presets </ActionLink>
            </FormActions>
        </AtlasForm>
    </AdminLayout>
</template>
