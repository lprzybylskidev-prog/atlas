<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconShieldCheck } from '@tabler/icons-vue';

import ActionLink from '../../../../Components/ActionLink.vue';
import CardHeader from '../../../../Components/CardHeader.vue';
import CheckboxList from '../../../../Components/CheckboxList.vue';
import FormActions from '../../../../Components/FormActions.vue';
import AtlasForm from '../../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../../Components/Form/FormButton.vue';
import FormInput from '../../../../Components/Form/FormInput.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../../Localization/translator';

defineProps<{
    permissionOptions: string[];
}>();

const { t } = useTranslator('en');
const form = useForm({
    name: '',
    permissions: [] as string[],
});

function submit(): void {
    form.post('/admin/authorization/roles', { preserveScroll: true });
}
</script>

<template>
    <Head :title="t('pages.admin.roles.create.head_title')" />
    <AdminLayout :title="t('pages.admin.roles.create.title')" :title-icon="IconShieldCheck">
        <AtlasForm class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,32rem)]" :processing="form.processing" @submit="submit">
            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <FormInput v-model="form.name" label="Name" :error="form.errors.name" monospace />
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <CardHeader title="Permissions" :icon="IconShieldCheck" />
                <CheckboxList
                    v-model="form.permissions"
                    class="mt-3"
                    :options="permissionOptions"
                    :error="form.errors.permissions"
                    max-height="max-h-[32rem]"
                />
            </section>

            <FormActions class="xl:col-span-2">
                <FormButton type="submit" :loading="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save role' }}
                </FormButton>
                <ActionLink href="/admin/authorization/roles" :icon="IconArrowLeft"> Back to roles </ActionLink>
            </FormActions>
        </AtlasForm>
    </AdminLayout>
</template>
