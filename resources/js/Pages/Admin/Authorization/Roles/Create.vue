<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconShieldCheck } from '@tabler/icons-vue';

import ActionLink from '../../../../Components/ActionLink.vue';
import CheckboxList from '../../../../Components/CheckboxList.vue';
import FormActions from '../../../../Components/FormActions.vue';
import AtlasForm from '../../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../../Components/Form/FormButton.vue';
import FormInput from '../../../../Components/Form/FormInput.vue';
import PageStack from '../../../../Components/PageStack.vue';
import SurfaceCard from '../../../../Components/SurfaceCard.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../../Localization/translator';

defineProps<{
    permissionOptions: string[];
}>();

const { t } = useTranslator();
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
        <PageStack>
            <AtlasForm class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_minmax(24rem,32rem)]" :processing="form.processing" @submit="submit">
                <SurfaceCard title="Role identity" :icon="IconShieldCheck">
                    <FormInput v-model="form.name" label="Name" :error="form.errors.name" monospace />
                </SurfaceCard>

                <SurfaceCard title="Permissions" :icon="IconShieldCheck">
                    <CheckboxList
                        v-model="form.permissions"
                        :options="permissionOptions"
                        :error="form.errors.permissions"
                        max-height="max-h-[32rem]"
                    />
                </SurfaceCard>

                <FormActions class="xl:col-span-2">
                    <FormButton type="submit" :loading="form.processing">
                        {{ form.processing ? 'Saving...' : 'Save role' }}
                    </FormButton>
                    <ActionLink href="/admin/authorization/roles" :icon="IconArrowLeft"> Back to roles </ActionLink>
                </FormActions>
            </AtlasForm>
        </PageStack>
    </AdminLayout>
</template>
