<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPackages } from '@tabler/icons-vue';

import AtlasForm from '../../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../../Components/Form/FormCheckbox.vue';
import FormInput from '../../../../Components/Form/FormInput.vue';
import AdminLayout from '../../../../Layouts/AdminLayout.vue';
import { useTranslator } from '../../../../Localization/translator';

defineProps<{
    roleOptions: string[];
    permissionOptions: string[];
}>();

const { t } = useTranslator('en');
const form = useForm({
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
                    <FormInput
                        v-model="form.name"
                        label="Technical name"
                        placeholder="department.responsibility"
                        :error="form.errors.name"
                        monospace
                    />
                    <FormInput v-model="form.label" label="Label" :error="form.errors.label" />
                </div>

                <div class="mt-5">
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Initial roles</p>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-2">
                        <FormCheckbox
                            v-for="role in roleOptions"
                            :key="role"
                            v-model="form.initial_roles"
                            class="shrink-0"
                            :value="role"
                            :label="role"
                        />
                    </div>
                    <p v-if="form.errors.initial_roles" class="mt-1 text-xs text-rose-600 dark:text-rose-300">
                        {{ form.errors.initial_roles }}
                    </p>
                </div>
            </section>

            <section class="rounded-lg border border-zinc-200 bg-white p-5 dark:border-zinc-800 dark:bg-zinc-950">
                <p class="text-sm font-medium text-zinc-700 dark:text-zinc-200">Direct permissions</p>
                <div
                    class="mt-2 grid max-h-[32rem] grid-cols-1 gap-2 overflow-auto rounded-lg border border-zinc-200 p-3 dark:border-zinc-800"
                >
                    <FormCheckbox
                        v-for="permission in permissionOptions"
                        :key="permission"
                        v-model="form.direct_permissions"
                        class="w-full"
                        :value="permission"
                        align="start"
                    >
                        <span class="break-all font-mono text-xs">{{ permission }}</span>
                    </FormCheckbox>
                </div>
                <p v-if="form.errors.direct_permissions" class="mt-1 text-xs text-rose-600 dark:text-rose-300">
                    {{ form.errors.direct_permissions }}
                </p>
            </section>

            <div class="flex flex-wrap items-center gap-2 xl:col-span-2">
                <FormButton type="submit" :loading="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save preset' }}
                </FormButton>
                <Link
                    href="/admin/authorization/packages"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-zinc-300 bg-white px-4 text-sm font-medium text-zinc-700 transition hover:border-zinc-400 hover:bg-zinc-100 hover:text-zinc-950 dark:border-zinc-700 dark:bg-zinc-900 dark:text-zinc-200 dark:hover:border-zinc-600 dark:hover:bg-zinc-800 dark:hover:text-zinc-50"
                >
                    <IconArrowLeft aria-hidden="true" class="h-4 w-4" :stroke-width="1.8" />
                    Back to presets
                </Link>
            </div>
        </AtlasForm>
    </AdminLayout>
</template>
