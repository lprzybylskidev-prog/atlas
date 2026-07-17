<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { IconArrowLeft, IconPackages } from '@tabler/icons-vue';

import AdminActionLink from '../../../../Components/AdminActionLink.vue';
import AdminFormActions from '../../../../Components/AdminFormActions.vue';
import AtlasForm from '../../../../Components/Form/AtlasForm.vue';
import FormButton from '../../../../Components/Form/FormButton.vue';
import FormCheckbox from '../../../../Components/Form/FormCheckbox.vue';
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

            <AdminFormActions class="xl:col-span-2">
                <FormButton type="submit" :loading="form.processing">
                    {{ form.processing ? 'Saving...' : 'Save preset' }}
                </FormButton>
                <AdminActionLink href="/admin/authorization/packages" :icon="IconArrowLeft"> Back to presets </AdminActionLink>
            </AdminFormActions>
        </AtlasForm>
    </AdminLayout>
</template>
