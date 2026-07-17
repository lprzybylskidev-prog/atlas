<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconShieldCheck } from '@tabler/icons-vue';

import AdminActionLink from '../../../../Components/AdminActionLink.vue';
import AdminFormActions from '../../../../Components/AdminFormActions.vue';
import AtlasForm from '../../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../../Components/Form/FormCheckbox.vue';
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
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Permissions</p>
                <div
                    class="mt-2 grid max-h-[32rem] grid-cols-1 gap-2 overflow-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                >
                    <FormCheckbox
                        v-for="permission in permissionOptions"
                        :key="permission"
                        v-model="form.permissions"
                        class="w-full"
                        :value="permission"
                        align="start"
                    >
                        <span class="break-all font-mono text-xs">{{ permission }}</span>
                    </FormCheckbox>
                </div>
                <p v-if="form.errors.permissions" class="mt-1 text-xs text-rose-600 dark:text-rose-300">
                    {{ form.errors.permissions }}
                </p>
            </section>

            <AdminFormActions class="xl:col-span-2">
                <FormButton type="submit" :loading="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save role' }}
                </FormButton>
                <AdminActionLink href="/admin/authorization/roles" :icon="IconArrowLeft"> Back to roles </AdminActionLink>
            </AdminFormActions>
        </AtlasForm>
    </AdminLayout>
</template>
